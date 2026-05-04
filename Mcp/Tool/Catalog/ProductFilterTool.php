<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Catalog;

use Magento\Catalog\Model\Product as ProductModel;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as SearchCollectionFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Store\Model\StoreManagerInterface;
use PhpMcp\Server\Attributes\McpTool;

class ProductFilterTool
{
    private const FLAT_ATTRIBUTES = ['color', 'size'];

    // The CatalogSearch fulltext collection is exposed via a Magento virtualType
    // (`Magento\CatalogSearch\Model\ResourceModel\Fulltext\CollectionFactory`)
    // which has no PHP class file, so it can't be used as a constructor type
    // hint — reflection fails when the DI cache is rebuilt. We type-hint the
    // parent CollectionFactory class and let di.xml inject the virtualType
    // (which is bound to the search-aware Fulltext\Collection at runtime).
    public function __construct(
        private readonly SearchCollectionFactory $searchCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Visibility $visibility,
        private readonly ConfigurableType $configurableType,
        private readonly EavConfig $eavConfig,
    ) {}

    /**
     * @param array<int, array{field: string, value: mixed}> $filters
     * @param array<int, array{field: string, direction?: string}> $sort
     * @return array{items: list<array<string, mixed>>}
     */
    #[McpTool(
        name: 'product-filter',
        description: <<<'DESC'
Lists up to 5 products matching simple equality filters (field = value) and/or a free-text phrase. Backed by the storefront catalog search index (Opensearch), so attribute filters work for configurable parents — child variant attributes (color, size, etc.) are aggregated onto the parent at index time.

PREFER THIS TOOL over product:search whenever the customer mentions ANY product characteristic — color, size, gender, manufacturer, brand, material, style, price range, or any other attribute. Filtering by characteristics is faster, more accurate, and lets the customer keep narrowing the result set.

Recommended workflow:
1. If the customer mentioned any characteristic (e.g. "red shoes for men", "size M shirt", "Nike sneakers"), call product-filter-options FIRST to discover the valid field names and value_index values for color/size/gender/manufacturer (and any others). Always probe at minimum: color, size, gender, manufacturer.
2. Pick the matching value_index for each characteristic the customer named, and call product-filter with filters = [{field: <attribute_code>, value: <value_index>}, ...]. Use phrase only for the free-text portion (e.g. "summer shirt" → phrase="summer shirt").
3. Only fall back to product:search when the customer's request is purely descriptive/semantic with no concrete characteristics.

Arguments: filters (array of {field, value} — combined as AND; pass option_id / value_index for select attributes, raw string/number otherwise); phrase (string, optional — free-text fragment matched by the search engine against name/sku/description); sort (array of {field, direction?} — direction ASC or DESC).

Returns compact rows: id, sku, name, type_id, price, url_key, color, size. For configurable products, color/size are comma-joined labels of all variants (e.g. "Red, Blue, Green"); for simple products, the single label.
DESC
    )]
    public function filter(array $filters = [], string $phrase = '', array $sort = []): array
    {
        $requestName = $phrase !== '' ? 'quick_search_container' : 'catalog_view_container';

        $collection = $this->searchCollectionFactory->create([
            'searchRequestName' => $requestName,
        ]);
        $collection->setStoreId((int) $this->storeManager->getStore()->getId());
        $collection->setVisibility($this->visibility->getVisibleInSiteIds());
        $collection->addAttributeToSelect(['name', 'price', 'url_key', 'status']);

        if ($phrase !== '') {
            $collection->addSearchFilter($phrase);
        }

        foreach ($filters as $f) {
            $collection->addFieldToFilter($f['field'], $f['value']);
        }

        foreach ($sort as $s) {
            $direction = strtoupper($s['direction'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
            $collection->setOrder($s['field'], $direction);
        }

        $collection->setPageSize(5);
        $collection->setCurPage(1);

        $items = [];
        foreach ($collection as $product) {
            $row = [
                'id'      => (int) $product->getId(),
                'sku'     => (string) $product->getSku(),
                'name'    => (string) $product->getName(),
                'type_id' => (string) $product->getTypeId(),
                'price'   => (float) $product->getPrice(),
                'url_key' => (string) ($product->getUrlKey() ?? $product->getCustomAttribute('url_key')?->getValue() ?? ''),
            ];
            foreach (self::FLAT_ATTRIBUTES as $code) {
                $row[$code] = $this->attributeLabels($product, $code);
            }
            $items[] = $row;
        }

        return ['items' => $items];
    }

    /**
     * Resolve human-readable labels for an attribute on a product. For
     * configurable parents, aggregates the unique labels across variants
     * (e.g. "Red, Blue"); for simple products, returns the single label.
     * Returns "" if the attribute has no value or doesn't exist.
     */
    private function attributeLabels($product, string $code): string
    {
        $attribute = $this->eavConfig->getAttribute(ProductModel::ENTITY, $code);
        if (!$attribute || !$attribute->getId()) {
            return '';
        }

        $values = [];
        if ($product->getTypeId() === ConfigurableType::TYPE_CODE) {
            foreach ($this->configurableType->getUsedProducts($product) as $child) {
                $v = $child->getData($code);
                if ($v !== null && $v !== '') {
                    $values[(int) $v] = true;
                }
            }
        } else {
            $v = $product->getData($code);
            if ($v !== null && $v !== '') {
                $values[(int) $v] = true;
            }
        }

        if ($values === []) {
            return '';
        }

        $labels = [];
        foreach ($attribute->getSource()->getAllOptions(false) as $option) {
            if (isset($values[(int) $option['value']])) {
                $labels[] = (string) $option['label'];
            }
        }
        return implode(', ', $labels);
    }
}
