<?php

declare(strict_types=1);

namespace Wsc\Analytics\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Wsc\Analytics\Model\CoreService;

class IndustryVertical implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        $options = [];
        foreach (CoreService::BENCHMARK_TAXONOMY as $path => $meta) {
            $options[] = [
                'value' => $path,
                'label' => $meta['icon'] . ' ' . $meta['label'],
            ];
        }
        return $options;
    }
}
