<?php

namespace Wsc\Analytics\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    public const XML_PATH_TRACKING_ACTIVE    = 'wsc_analytics/general/tracking_active';
    public const XML_PATH_TRACKING_MODE      = 'wsc_analytics/general/tracking_mode';
    public const XML_PATH_EXCLUDE_ADMINS     = 'wsc_analytics/general/exclude_admins';
    public const XML_PATH_HYBRID_ENABLED     = 'wsc_analytics/gateway/hybrid_enabled';
    public const XML_PATH_GATEWAY_URL        = 'wsc_analytics/gateway/gateway_url';
    public const XML_PATH_PROJECT_ID         = 'wsc_analytics/gateway/project_id';
    public const XML_PATH_LOCAL_KEY          = 'wsc_analytics/gateway/local_key';
    public const XML_PATH_BOT_LEVEL          = 'wsc_analytics/bot_shield/bot_tracking_level';
    public const XML_PATH_TRACK_SERVER_ORDERS= 'wsc_analytics/ecommerce/track_server_orders';
    public const XML_PATH_AUTO_DOWNLOADS     = 'wsc_analytics/ecommerce/auto_downloads';
    public const XML_PATH_AUTO_TIME_ON_PAGE  = 'wsc_analytics/ecommerce/auto_time_on_page';
    public const XML_PATH_RETENTION_DAYS     = 'wsc_analytics/maintenance/retention_days';

    public function isTrackingActive(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_TRACKING_ACTIVE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getTrackingMode(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_TRACKING_MODE, ScopeInterface::SCOPE_STORE, $storeId) ?: 'cookieless';
    }

    public function getGatewayUrl(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_GATEWAY_URL, ScopeInterface::SCOPE_STORE, $storeId) ?: 'https://api.worldsearchcouncil.org';
    }

    public function getProjectId(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_PROJECT_ID, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getLocalKey(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_LOCAL_KEY, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getBotTrackingLevel(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_BOT_LEVEL, ScopeInterface::SCOPE_STORE, $storeId) ?: 'search_ai';
    }

    public function isTrackServerOrders(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_TRACK_SERVER_ORDERS, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
