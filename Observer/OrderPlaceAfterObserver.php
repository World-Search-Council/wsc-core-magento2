<?php

namespace Wsc\Analytics\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Wsc\Analytics\Helper\Data as HelperData;
use Wsc\Analytics\Model\CoreService;
use Psr\Log\LoggerInterface;

/**
 * Server-side order capture observer for 100% ad-blocker immune e-commerce tracking.
 */
class OrderPlaceAfterObserver implements ObserverInterface
{
    private HelperData $helper;
    private CoreService $coreService;
    private LoggerInterface $logger;

    public function __construct(
        HelperData $helper,
        CoreService $coreService,
        LoggerInterface $logger
    ) {
        $this->helper = $helper;
        $this->coreService = $coreService;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        if (!$this->helper->isTrackingActive() || !$this->helper->isTrackServerOrders()) {
            return;
        }

        try {
            /** @var Order $order */
            $order = $observer->getEvent()->getOrder();
            if (!$order || !$order->getId()) {
                return;
            }

            $items = [];
            foreach ($order->getAllVisibleItems() as $item) {
                $items[] = [
                    'item_id'    => $item->getItemId(),
                    'sku'        => $item->getSku(),
                    'name'       => $item->getName(),
                    'qty'        => (float) $item->getQtyOrdered(),
                    'price'      => (float) $item->getPrice(),
                    'row_total'  => (float) $item->getRowTotal(),
                ];
            }

            $meta = [
                'order_id'       => (string) $order->getId(),
                'increment_id'   => $order->getIncrementId(),
                'grand_total'    => (float) $order->getGrandTotal(),
                'base_subtotal'  => (float) $order->getBaseSubtotal(),
                'shipping_amount'=> (float) $order->getShippingAmount(),
                'tax_amount'     => (float) $order->getTaxAmount(),
                'currency'       => $order->getOrderCurrencyCode() ?: 'USD',
                'items_count'    => count($items),
                'items'          => $items,
                'source'         => 'server_side_magento2',
            ];

            $this->coreService->trackEvent('purchase', '/checkout/onepage/success', '', $meta);
        } catch (\Throwable $e) {
            $this->logger->error('WSC Magento Order Tracking Error: ' . $e->getMessage());
        }
    }
}
