<?php

namespace Wsc\Analytics\Controller\Api;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Wsc\Analytics\Model\CoreService;

class Cards implements HttpGetActionInterface
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
        $cards = $this->coreService->getDashboardCards();

        return $result->setData([
            'ok' => true,
            'cards' => $cards,
        ]);
    }
}
