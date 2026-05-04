<?php
declare(strict_types=1);

namespace WisWes\MCP\Controller\Adminhtml\Push;

use WisWes\MCP\Service\ChatAgentPushService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Indexer\IndexerRegistry;

/**
 * Admin trigger for "Push catalogue to WisWes now".
 *
 * Runs the indexer in the foreground via {@see IndexerRegistry} so the
 * admin sees the upsert counters in a flash message right away — same code
 * path as the System → Index Management "Reindex Data" button (which the
 * indexer framework drives), but with a friendlier success notice.
 */
class Index extends Action
{
    public const ADMIN_RESOURCE = 'WisWes_MCP::config';
    private const INDEXER_ID = 'wiswes_product_push';

    public function __construct(
        Context $context,
        private readonly ChatAgentPushService $pushService,
        private readonly IndexerRegistry $indexerRegistry,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        try {
            // Mark the indexer state as Working → Valid via reindexAll() so
            // the System → Index Management page reflects the run, while
            // grabbing the totals from the push service for the success
            // message (the indexer interface's executeFull returns void).
            $totals = $this->pushService->push();
            $indexer = $this->indexerRegistry->get(self::INDEXER_ID);
            $indexer->getState()->setStatus(\Magento\Framework\Indexer\StateInterface::STATUS_VALID)->save();

            $this->messageManager->addSuccessMessage(sprintf(
                'WisWes catalogue push complete — sent %d products in %d batches '
                . '(upserted=%d, skipped because operator-edited=%d, skipped because over plan cap=%d).',
                $totals['sent'] ?? 0,
                $totals['pages'] ?? 0,
                $totals['upserted'] ?? 0,
                $totals['skipped_operator_edited'] ?? 0,
                $totals['skipped_over_cap'] ?? 0,
            ));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(
                'WisWes push failed: ' . $e->getMessage()
            );
        }
        return $resultRedirect->setPath('adminhtml/system_config/edit', ['section' => 'wiswes_mcp']);
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}
