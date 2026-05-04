<?php
declare(strict_types=1);

namespace WisWes\MCP\Service;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Pushes the Magento catalogue to chat_agent for indexing into Qdrant.
 *
 * Auth model is the same shared secret the MCP path validates
 * ({@see \WisWes\MCP\Model\Auth\TokenUserContextResolver}). The secret is
 * minted by the install-handshake controller; this service reads it from
 * `wiswes_mcp/auth/shared_secret`. The chat_agent ingestion endpoint
 * (`POST /api/integrations/magento/products`) compares it constant-time
 * against `commerce_config.commerce_token` so only the matching tenant's
 * catalogue is updated.
 *
 * Pages through enabled, visible products in batches; for each row we ship
 * a compact retrieval payload (sku, name, url, price) plus a metadata blob
 * built from name + short description + searchable attributes. The blob is
 * what chat_agent embeds; the retrieval payload is what the LLM sees verbatim
 * when a result matches.
 */
class ChatAgentPushService
{
    public const CONFIG_PATH_SECRET = 'wiswes_mcp/auth/shared_secret';
    public const CONFIG_PATH_WISWES_URL = 'wiswes_mcp/install/wiswes_url';

    private const DEFAULT_WISWES_URL = 'https://api.wiswes.com/';
    private const PUSH_PATH = '/api/integrations/magento/products';
    private const BATCH_SIZE = 100;
    private const HTTP_TIMEOUT_SECONDS = 30;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
        private readonly Curl $http,
        private readonly UrlInterface $urlBuilder,
        private readonly ResourceConnection $resource,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Push the catalogue. Returns counters:
     *   - pages: number of HTTP batches sent
     *   - sent:  total products serialised and shipped
     *   - upserted / skipped_operator_edited / skipped_over_cap: aggregates
     *     reported by chat_agent so an operator can verify the round trip.
     *
     * @return array<string, int>
     */
    public function push(): array
    {
        $secret = $this->requireSecret();
        $endpoint = $this->resolveEndpoint();
        $totals = $this->emptyTotals();

        $page = 1;
        do {
            $batch = $this->fetchPage($page, self::BATCH_SIZE);
            if ($batch === []) {
                break;
            }
            $this->shipBatch($batch, $endpoint, $secret, $totals);
            $page++;
        } while (count($batch) === self::BATCH_SIZE);

        $this->logger->info('[WisWes_MCP] product push (full) complete: ' . json_encode($totals));
        return $totals;
    }

    /**
     * Push only the listed product entity ids — used by the Magento indexer's
     * incremental path (mview change log → executeList / executeRow). Lets a
     * single product save ship one product instead of the whole catalogue.
     *
     * @param int[] $productIds
     * @return array<string, int>
     */
    public function pushIds(array $productIds): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        $totals = $this->emptyTotals();
        if ($productIds === []) {
            return $totals;
        }

        $secret = $this->requireSecret();
        $endpoint = $this->resolveEndpoint();

        foreach (array_chunk($productIds, self::BATCH_SIZE) as $idChunk) {
            $batch = $this->fetchByIds($idChunk);
            if ($batch === []) {
                continue;
            }
            $this->shipBatch($batch, $endpoint, $secret, $totals);
        }

        $this->logger->info(sprintf(
            '[WisWes_MCP] product push (ids=%d) complete: %s',
            count($productIds),
            json_encode($totals),
        ));
        return $totals;
    }

    /** @return array<string, int> */
    private function emptyTotals(): array
    {
        return ['pages' => 0, 'sent' => 0, 'upserted' => 0, 'skipped_operator_edited' => 0, 'skipped_over_cap' => 0];
    }

    private function requireSecret(): string
    {
        $secret = $this->resolveSecret();
        if ($secret === '') {
            throw new \RuntimeException(
                'WisWes shared secret is missing — open the WisWes config in Stores → '
                . 'Configuration and click Install to mint one.'
            );
        }
        return $secret;
    }

    /**
     * @param ProductInterface[] $batch
     * @param array<string, int>  $totals counters mutated in place
     */
    private function shipBatch(array $batch, string $endpoint, string $secret, array &$totals): void
    {
        // Magento's getList()->getItems() returns rows keyed by entity_id;
        // strip the keys with array_values() so the JSON payload is a proper
        // array, not an object — chat_agent's pydantic schema requires
        // `products` to be a list.
        $priceMap = $this->loadIndexedPrices($batch);
        $payload = [
            'products' => array_values(array_map(
                fn (ProductInterface $p) => $this->serialiseProduct($p, $priceMap),
                $batch,
            )),
        ];
        $response = $this->postJson($endpoint, $payload, $secret);

        $totals['pages']++;
        $totals['sent'] += count($batch);
        foreach (['upserted', 'skipped_operator_edited', 'skipped_over_cap'] as $key) {
            if (isset($response[$key]) && is_int($response[$key])) {
                $totals[$key] += $response[$key];
            }
        }
    }

    /**
     * @param int[] $ids
     * @return ProductInterface[]
     */
    private function fetchByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $criteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $ids, 'in')
            ->addFilter('status', 1)
            ->addFilter('visibility', [
                Visibility::VISIBILITY_BOTH,
                Visibility::VISIBILITY_IN_CATALOG,
                Visibility::VISIBILITY_IN_SEARCH,
            ], 'in')
            ->setPageSize(count($ids))
            ->setCurrentPage(1)
            ->create();
        return $this->productRepository->getList($criteria)->getItems();
    }

    /**
     * Fetch one page of enabled, catalog-visible products. Disabled and
     * "not visible individually" products are excluded — they're not
     * something a customer can land on, so indexing them just adds noise.
     *
     * @return ProductInterface[]
     */
    private function fetchPage(int $page, int $size): array
    {
        $criteria = $this->searchCriteriaBuilder
            ->addFilter('status', 1)
            ->addFilter('visibility', [
                Visibility::VISIBILITY_BOTH,
                Visibility::VISIBILITY_IN_CATALOG,
                Visibility::VISIBILITY_IN_SEARCH,
            ], 'in')
            ->setCurrentPage($page)
            ->setPageSize($size)
            ->create();

        return $this->productRepository->getList($criteria)->getItems();
    }

    /**
     * Build the wire payload for a single product. `metadata` is the
     * embed-target — concatenate every field the LLM should be able to find
     * the product *by*. `retrieval_*` fields are what the LLM sees back.
     *
     * @param array<int, float> $priceMap entity_id → indexed minimum price
     * @return array<string, string>
     */
    private function serialiseProduct(ProductInterface $product, array $priceMap = []): array
    {
        $name = (string) $product->getName();
        $sku = (string) $product->getSku();

        // Configurable / bundle / grouped products store $0 on the parent
        // entity — the actual price lives on child variants and is
        // pre-aggregated into catalog_product_index_price.min_price by
        // Magento's price indexer. Read from there first, fall back to the
        // entity price for simples and any rows the index hasn't covered.
        $entityId = (int) $product->getId();
        $minPrice = '';
        if (isset($priceMap[$entityId]) && $priceMap[$entityId] > 0) {
            $minPrice = (string) (float) $priceMap[$entityId];
        } else {
            $price = $product->getPrice();
            if ($price !== null && (float) $price > 0) {
                $minPrice = (string) (float) $price;
            }
        }

        // Use getProductUrl() so the URL respects:
        //   - the configured catalog/seo/product_url_suffix (typically .html)
        //   - url_rewrite entries (canonical paths, store-scoped overrides)
        //   - the active store's base URL.
        // Building the URL from `url_key` directly via UrlInterface::_direct
        // bypasses all three and yields a 404 on stores with a non-empty
        // suffix.
        $url = '';
        try {
            $candidate = (string) $product->getProductUrl();
            if ($candidate !== '') {
                $url = $candidate;
            }
        } catch (\Throwable) {
            // getProductUrl() can throw when run outside a store context
            // (admin area, non-default scope). Fall back to constructing
            // from url_key + suffix so we still ship something usable.
            $urlKey = $product->getCustomAttribute('url_key')?->getValue();
            if (is_string($urlKey) && $urlKey !== '') {
                $suffix = (string) $this->scopeConfig->getValue('catalog/seo/product_url_suffix');
                $url = $this->urlBuilder->getUrl('', ['_direct' => $urlKey . $suffix]);
            }
        }

        $shortDescription = $this->stripText($product->getCustomAttribute('short_description')?->getValue());
        $description = $this->stripText($product->getCustomAttribute('description')?->getValue());
        $metaKeyword = $this->stripText($product->getCustomAttribute('meta_keyword')?->getValue());

        // Cap each section so a Magento store with novel-length descriptions
        // doesn't blow past the chat_agent metadata column limit (20000).
        $metadataParts = array_filter([
            $name,
            $sku !== '' ? "SKU $sku" : '',
            $this->truncate($shortDescription, 2000),
            $this->truncate($description, 6000),
            $this->truncate($metaKeyword, 500),
        ]);
        $metadata = $this->truncate(implode("\n", $metadataParts), 19000);

        return [
            'sku' => $sku,
            'name' => $this->truncate($name, 500),
            'url' => $this->truncate($url, 1000),
            'min_price' => $minPrice,
            'metadata' => $metadata !== '' ? $metadata : ($name !== '' ? $name : $sku),
        ];
    }

    /**
     * Look up indexed minimum prices for a batch of products.
     *
     * `catalog_product_index_price` is the price indexer's flat output —
     * one row per (entity_id, customer_group_id, website_id). For
     * configurables / bundles it holds the cheapest variant's price; for
     * simples it mirrors the entity price. We pick customer_group_id=0
     * (NOT_LOGGED_IN) since the chat widget addresses anonymous shoppers,
     * and the current store's website so multi-website installs ship the
     * right currency snapshot.
     *
     * @param ProductInterface[] $batch
     * @return array<int, float> entity_id → min_price
     */
    private function loadIndexedPrices(array $batch): array
    {
        $ids = array_filter(array_map(static fn (ProductInterface $p) => (int) $p->getId(), $batch));
        if ($ids === []) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('catalog_product_index_price');
        $websiteId = (int) $this->storeManager->getStore()->getWebsiteId();

        $select = $connection->select()
            ->from($table, ['entity_id', 'min_price'])
            ->where('entity_id IN (?)', $ids)
            ->where('customer_group_id = ?', 0)
            ->where('website_id = ?', $websiteId);

        $rows = $connection->fetchAll($select);
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['entity_id']] = (float) $row['min_price'];
        }
        return $map;
    }

    private function stripText(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $stripped = trim(strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        return preg_replace('/\s+/u', ' ', $stripped) ?? '';
    }

    private function truncate(string $value, int $max): string
    {
        if ($max <= 0 || $value === '') {
            return '';
        }
        return mb_strlen($value) <= $max ? $value : mb_substr($value, 0, $max);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function postJson(string $url, array $payload, string $secret): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->http->setTimeout(self::HTTP_TIMEOUT_SECONDS);
        $this->http->setHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $secret,
        ]);
        $this->http->post($url, $body);

        $status = $this->http->getStatus();
        $rawBody = $this->http->getBody();

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(sprintf(
                'chat_agent push to %s failed with HTTP %d: %s',
                $url,
                $status,
                $this->truncate((string) $rawBody, 500),
            ));
        }

        try {
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\JsonException) {
            return [];
        }
    }

    private function resolveSecret(): string
    {
        $stored = (string) $this->scopeConfig->getValue(self::CONFIG_PATH_SECRET);
        if ($stored === '') {
            return '';
        }
        $decrypted = $this->encryptor->decrypt($stored);
        return $decrypted !== '' ? $decrypted : '';
    }

    private function resolveEndpoint(): string
    {
        $base = (string) $this->scopeConfig->getValue(self::CONFIG_PATH_WISWES_URL);
        if ($base === '') {
            $base = self::DEFAULT_WISWES_URL;
        }
        return rtrim($base, '/') . self::PUSH_PATH;
    }
}
