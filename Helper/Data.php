<?php

declare(strict_types=1);

namespace Wsc\Analytics\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    public const XML_PATH_TRACKING_ACTIVE     = 'wsc_analytics/general/tracking_active';
    public const XML_PATH_TRACKING_MODE       = 'wsc_analytics/general/tracking_mode';
    public const XML_PATH_EXCLUDE_ADMINS      = 'wsc_analytics/general/exclude_admins';

    public const XML_PATH_HYBRID_ENABLED      = 'wsc_analytics/gateway/hybrid_enabled';
    public const XML_PATH_GATEWAY_URL         = 'wsc_analytics/gateway/gateway_url';
    public const XML_PATH_PROJECT_ID          = 'wsc_analytics/gateway/project_id';
    public const XML_PATH_API_SECRET          = 'wsc_analytics/gateway/api_secret';
    public const XML_PATH_LOCAL_KEY           = 'wsc_analytics/gateway/local_key';

    public const XML_PATH_LICENSE_KEY         = 'wsc_analytics/membership/license_key';
    public const XML_PATH_LICENSE_STATUS      = 'wsc_analytics/membership/license_status';
    public const XML_PATH_LICENSE_TIER        = 'wsc_analytics/membership/license_tier';
    public const XML_PATH_LICENSE_HOLDER      = 'wsc_analytics/membership/license_holder';
    public const XML_PATH_LICENSE_EXPIRES     = 'wsc_analytics/membership/license_expires';
    public const XML_PATH_LICENSE_IS_DEMO     = 'wsc_analytics/membership/license_is_demo';
    public const XML_PATH_CO_BRANDING         = 'wsc_analytics/membership/co_branding';

    public const XML_PATH_LLMS_TXT_ENABLED    = 'wsc_analytics/ai_indexnow/llms_txt_enabled';
    public const XML_PATH_INDUSTRY_VERTICAL   = 'wsc_analytics/ai_indexnow/industry_vertical';
    public const XML_PATH_INDEXNOW_ENABLED    = 'wsc_analytics/ai_indexnow/indexnow_enabled';
    public const XML_PATH_INDEXNOW_KEY        = 'wsc_analytics/ai_indexnow/indexnow_key';

    public const XML_PATH_BOT_LEVEL           = 'wsc_analytics/bot_shield/bot_tracking_level';
    public const XML_PATH_TRACK_SERVER_ORDERS = 'wsc_analytics/ecommerce/track_server_orders';
    public const XML_PATH_AUTO_DOWNLOADS      = 'wsc_analytics/ecommerce/auto_downloads';
    public const XML_PATH_AUTO_TIME_ON_PAGE   = 'wsc_analytics/ecommerce/auto_time_on_page';
    public const XML_PATH_RETENTION_DAYS      = 'wsc_analytics/maintenance/retention_days';

    public function isTrackingActive(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_TRACKING_ACTIVE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getTrackingMode(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_TRACKING_MODE, ScopeInterface::SCOPE_STORE, $storeId) ?: 'cookieless';
    }

    public function isHybridEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_HYBRID_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getGatewayUrl(?int $storeId = null): string
    {
        return rtrim((string) $this->scopeConfig->getValue(self::XML_PATH_GATEWAY_URL, ScopeInterface::SCOPE_STORE, $storeId) ?: 'https://api.worldsearchcouncil.org', '/');
    }

    public function getProjectId(?int $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(self::XML_PATH_PROJECT_ID, ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function getApiSecret(?int $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(self::XML_PATH_API_SECRET, ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function getLocalKey(?int $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(self::XML_PATH_LOCAL_KEY, ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function getLicenseKey(?int $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(self::XML_PATH_LICENSE_KEY, ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function getLicenseStatus(?int $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(self::XML_PATH_LICENSE_STATUS, ScopeInterface::SCOPE_STORE, $storeId)) ?: 'unregistered';
    }

    public function getLicenseTier(?int $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(self::XML_PATH_LICENSE_TIER, ScopeInterface::SCOPE_STORE, $storeId)) ?: 'community';
    }

    public function getLicenseHolder(?int $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(self::XML_PATH_LICENSE_HOLDER, ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function getLicenseExpires(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(self::XML_PATH_LICENSE_EXPIRES, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isLicenseDemo(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_LICENSE_IS_DEMO, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getCoBrandingRaw(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_CO_BRANDING, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isLlmsTxtEnabled(?int $storeId = null): bool
    {
        $val = $this->scopeConfig->getValue(self::XML_PATH_LLMS_TXT_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
        return $val === null ? true : ((string) $val !== '0' && $val !== false);
    }

    public function getIndustryVertical(?int $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(self::XML_PATH_INDUSTRY_VERTICAL, ScopeInterface::SCOPE_STORE, $storeId)) ?: '/Shopping/Apparel';
    }

    public function isIndexNowEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_INDEXNOW_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getIndexNowKey(?int $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(self::XML_PATH_INDEXNOW_KEY, ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function getBotTrackingLevel(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_BOT_LEVEL, ScopeInterface::SCOPE_STORE, $storeId) ?: 'search_ai';
    }

    public function isTrackServerOrders(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_TRACK_SERVER_ORDERS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isAutoDownloads(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_AUTO_DOWNLOADS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isAutoTimeOnPage(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_AUTO_TIME_ON_PAGE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getRetentionDays(?int $storeId = null): int
    {
        return max(1, (int) ($this->scopeConfig->getValue(self::XML_PATH_RETENTION_DAYS, ScopeInterface::SCOPE_STORE, $storeId) ?: 30));
    }
}
