<?php

namespace Wsc\Analytics\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Wsc\Analytics\Helper\Data as HelperData;

class Tracker extends Template
{
    private HelperData $helper;

    public function __construct(
        Context $context,
        HelperData $helper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->helper = $helper;
    }

    public function isTrackingActive(): bool
    {
        return $this->helper->isTrackingActive();
    }

    public function getJsonConfig(): string
    {
        $config = [
            'endpoint' => $this->getUrl('wsc/api/track'),
            'mode' => $this->helper->getTrackingMode(),
            'consentKey' => 'wsc_consent',
            'auto' => [
                'downloads' => false,
                'timeOnPage' => false,
            ],
        ];

        return (string) json_encode($config);
    }
}
