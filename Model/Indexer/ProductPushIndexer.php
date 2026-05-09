<?php
declare(strict_types=1);

namespace WisWes\MCP\Model\Indexer;

use WisWes\MCP\Service\ChatAgentPushService;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Psr\Log\LoggerInterface;

/**
 * Magento indexer that pushes the catalogue snapshot to chat_agent (WisWes)
 * for vector indexing.
 *
 * Implements both indexer and mview action interfaces:
 *   - indexer: full / list / row reindex operations driven by
 *     `bin/magento indexer:reindex`, the System → Index Management button,
 *     and the cron job `indexer_reindex_all_invalid` when set to "Update by
 *     Schedule".
 *   - mview: triggered by Magento's change-log mechanism (etc/mview.xml
 *     subscriptions) so a single product save fires `execute([$id])` and
 *     ships exactly that product.
 *
 * Skips silently when the WisWes shared secret hasn't been minted yet (i.e.
 * the merchant hasn't clicked Install). Surfaces real errors via logs so
 * indexer status reflects "Ready" rather than "Reindex Required" forever.
 */
class ProductPushIndexer implements IndexerActionInterface, MviewActionInterface
{
    public function __construct(
        private readonly ChatAgentPushService $pushService,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function executeFull(): void
    {
        if (!$this->isInstalled()) {
            return;
        }
        try {
            $this->pushService->push();
        } catch (\Throwable $e) {
            $this->logger->warning('[WisWes_MCP] full reindex failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param int[] $ids
     */
    public function executeList(array $ids): void
    {
        $this->execute($ids);
    }

    /**
     * @param int $id
     */
    public function executeRow($id): void
    {
        $this->execute([(int) $id]);
    }

    /**
     * Mview entry point — receives a list of changed product entity ids.
     *
     * @param int[] $ids
     */
    public function execute($ids): void
    {
        if (!$this->isInstalled()) {
            return;
        }
        $idList = array_values(array_filter(array_map('intval', (array) $ids)));
        if ($idList === []) {
            return;
        }
        try {
            $this->pushService->pushIds($idList);
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf(
                '[WisWes_MCP] partial reindex (ids=%d) failed: %s',
                count($idList),
                $e->getMessage(),
            ));
            throw $e;
        }
    }

    private function isInstalled(): bool
    {
        $secret = (string) $this->scopeConfig->getValue(ChatAgentPushService::CONFIG_PATH_SECRET);
        return $secret !== '';
    }
}
