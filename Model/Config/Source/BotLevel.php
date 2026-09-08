<?php

namespace Wsc\Analytics\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class BotLevel implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'off', 'label' => __('Disabled')],
            ['value' => 'ai_only', 'label' => __('AI / LLM Bots only (GPTBot, Claude, Perplexity)')],
            ['value' => 'search_ai', 'label' => __('Search Engines + AI Bots (Recommended)')],
            ['value' => 'all', 'label' => __('All known crawlers & SEO tools')],
        ];
    }
}
