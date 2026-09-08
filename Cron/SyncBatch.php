<?php

namespace Wsc\Analytics\Cron;

use Wsc\Analytics\Model\CoreService;
use Psr\Log\LoggerInterface;

class SyncBatch
{
    private CoreService $coreService;
    private LoggerInterface $logger;

    public function __construct(
        CoreService $coreService,
        LoggerInterface $logger
    ) {
        $this->coreService = $coreService;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        try {
            $synced = $this->coreService->processBatchExport(500);
            if ($synced > 0) {
                $this->logger->info("WSC Magento 2 Cron: Synced $synced micro-batch events to Gateway.");
            }

            // Routine cleanup
            $this->coreService->cleanupOldData();
        } catch (\Throwable $e) {
            $this->logger->error('WSC Magento 2 Cron Error: ' . $e->getMessage());
        }
    }
}
