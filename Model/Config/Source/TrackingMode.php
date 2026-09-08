<?php

namespace Wsc\Analytics\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class TrackingMode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'cookieless', 'label' => __('Cookieless Privacy-First (100% GDPR compliant without cookie banner)')],
            ['value' => 'consent', 'label' => __('Consent-Required (Only tracks upon cookie consent)')],
        ];
    }
}
