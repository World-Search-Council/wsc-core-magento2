<?php

namespace Wsc\Analytics\Controller\Api;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Wsc\Analytics\Model\CoreService;

class Refresh implements HttpPostActionInterface
{
    private JsonFactory $jsonFactory;
    private CoreService $coreService;

    public function __construct(
        JsonFactory $jsonFactory,
        CoreService $coreService
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->coreService = $coreService;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $synced = $this->coreService->processBatchExport(500);

        return $result->setData([
            'ok' => true,
            'synced_events' => $synced,
            'last_sync' => date('Y-m-d H:i:s'),
        ]);
    }
}
