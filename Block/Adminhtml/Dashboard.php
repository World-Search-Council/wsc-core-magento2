<?php

namespace Wsc\Analytics\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Wsc\Analytics\Model\CoreService;

class Dashboard extends Template
{
    private CoreService $coreService;
    private StoreManagerInterface $storeManager;

    public function __construct(
        Context $context,
        CoreService $coreService,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->coreService = $coreService;
        $this->storeManager = $storeManager;
    }

    public function getCards(): array
    {
        return $this->coreService->getDashboardCards();
    }

    public function getCredentials(): array
    {
        return $this->coreService->getProjectCredentials();
    }

    public function hasData(): bool
    {
        $cards = $this->getCards();
        return !empty($cards['card1'])
            || !empty($cards['card2'])
            || !empty($cards['card3'])
            || !empty($cards['card_engagement'])
            || !empty($cards['card_outbound'])
            || !empty($cards['card_ecommerce'])
            || !empty($cards['domain_health']);
    }

    public function getRefreshUrl(): string
    {
        return $this->getUrl('wsc/api/refresh');
    }

    public function getCardsUrl(): string
    {
        return $this->getUrl('wsc/api/cards');
    }

    public function getPagespeedUrl(): string
    {
        return $this->getUrl('wsc/api/pagespeed');
    }

    public function getSentinelUrl(): string
    {
        return $this->getUrl('wsc/api/sentinel');
    }

    public function getSiteUrl(): string
    {
        return $this->storeManager->getStore()->getBaseUrl();
    }

    public function getSiteHost(): string
    {
        return parse_url($this->getSiteUrl(), PHP_URL_HOST) ?: '';
    }
}
