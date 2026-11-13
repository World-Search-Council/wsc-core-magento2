<?php

declare(strict_types=1);

namespace Wsc\Analytics\Observer;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use Wsc\Analytics\Model\CoreService;

/**
 * Opt-in IndexNow instant URL push when a published, visible catalog product is saved.
 */
class ProductSaveAfterObserver implements ObserverInterface
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

    public function execute(Observer $observer): void
    {
        if (!$this->coreService->isIndexNowEnabled()) {
            return;
        }

        try {
            /** @var Product|null $product */
            $product = $observer->getEvent()->getProduct();
            if (!$product instanceof Product || !$product->getId()) {
                return;
            }

            if ((int) $product->getStatus() !== Status::STATUS_ENABLED) {
                return;
            }

            if ((int) $product->getVisibility() === Visibility::VISIBILITY_NOT_VISIBLE) {
                return;
            }

            $productUrl = trim((string) $product->getProductUrl());
            if ($productUrl !== '') {
                $storeId = $product->getStoreId() !== null ? (int) $product->getStoreId() : null;
                $this->coreService->submitIndexNowUrls([$productUrl], 'product_save', 5, null, $storeId);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('WSC Magento Product IndexNow Observer Error: ' . $e->getMessage());
        }
    }
}
