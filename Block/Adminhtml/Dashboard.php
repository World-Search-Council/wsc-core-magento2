<?php

declare(strict_types=1);

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
        return $this->coreService->getProjectCredentials(false);
    }

    public function getMembership(): array
    {
        return $this->coreService->getMembershipState();
    }

    /**
     * Returns featured partners ONLY when unlicensed (100% ad-free UI for active WSC Members).
     */
    public function getFeaturedPartners(): array
    {
        $membership = $this->getMembership();
        if (!empty($membership['is_licensed'])) {
            return [];
        }
        return $this->coreService->getFeaturedPartners(3);
    }

    public function getBenchmarkReport(): array
    {
        return $this->coreService->getBenchmarkReport(7, false);
    }

    public function getBenchmarkVerticals(): array
    {
        return CoreService::BENCHMARK_TAXONOMY;
    }

    public function isLlmsTxtEnabled(): bool
    {
        return $this->coreService->isLlmsTxtEnabled();
    }

    public function isIndexNowEnabled(): bool
    {
        return $this->coreService->isIndexNowEnabled();
    }

    public function getIndexNowKey(): string
    {
        return $this->coreService->getIndexNowKey();
    }

    public function getIndexNowLogs(): array
    {
        return $this->coreService->getIndexNowLogs();
    }

    public function hasData(): bool
    {
        $cards = $this->getCards();
        return !empty($cards['card1'])
            || !empty($cards['card1_traffic'])
            || !empty($cards['card2'])
            || !empty($cards['card3'])
            || !empty($cards['card_engagement'])
            || !empty($cards['card_outbound'])
            || !empty($cards['card_ecommerce'])
            || !empty($cards['domain_health']);
    }

    public function getDispatchUrl(): string
    {
        return $this->getUrl('wsc_admin/api/dispatch');
    }

    public function getRefreshUrl(): string
    {
        return $this->getUrl('wsc_admin/api/dispatch', ['wsc_action' => 'refresh']);
    }

    public function getCardsUrl(): string
    {
        return $this->getUrl('wsc_admin/api/dispatch', ['wsc_action' => 'cards']);
    }

    public function getPagespeedUrl(): string
    {
        return $this->getUrl('wsc_admin/api/dispatch', ['wsc_action' => 'pagespeed']);
    }

    public function getSentinelUrl(): string
    {
        return $this->getUrl('wsc_admin/api/dispatch', ['wsc_action' => 'sentinel']);
    }

    public function getSiteUrl(): string
    {
        return $this->coreService->resolveSiteBaseUrl();
    }

    public function getSiteHost(): string
    {
        return (string) (parse_url($this->getSiteUrl(), PHP_URL_HOST) ?: '');
    }
}
