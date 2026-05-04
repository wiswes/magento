<?php
declare(strict_types=1);

namespace WisWes\MCP\Console\Command;

use WisWes\MCP\Service\ChatAgentPushService;
use Magento\Framework\App\Area;
use Magento\Framework\App\State as AppState;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `bin/magento wiswes:products:push` — runs the same catalogue push the cron
 * job does. Useful for verifying the round-trip during onboarding without
 * waiting for the cron to fire.
 */
class PushProductsCommand extends Command
{
    public function __construct(
        private readonly AppState $appState,
        private readonly ChatAgentPushService $pushService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('wiswes:products:push')
            ->setDescription('Push the Magento catalogue to chat_agent for Qdrant indexing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Many catalogue calls fail without an area code (URL builder, store
        // resolution, etc.) — match cron's "crontab" area.
        try { $this->appState->setAreaCode(Area::AREA_CRONTAB); } catch (\Throwable) {}

        try {
            $totals = $this->pushService->push();
        } catch (\Throwable $e) {
            $output->writeln('<error>Push failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            'Pushed %d products in %d batches (upserted=%d, skipped_operator=%d, skipped_cap=%d)',
            $totals['sent'] ?? 0,
            $totals['pages'] ?? 0,
            $totals['upserted'] ?? 0,
            $totals['skipped_operator_edited'] ?? 0,
            $totals['skipped_over_cap'] ?? 0,
        ));
        return Command::SUCCESS;
    }
}
