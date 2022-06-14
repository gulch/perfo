<?php

namespace Perfo\Helpers;

use function count, sort, is_float, intval;

class StatHelper
{
    public function calculateMedian(array $items): int|float
    {
        sort($items);

        $middleIndex = count($items) / 2;

        if (is_float($middleIndex)) {
            return $items[intval($middleIndex)];
        }

        return ($items[$middleIndex] + $items[$middleIndex - 1]) / 2;
    }

    public function calculateAverage(array $items): int|float
    {
        $count = 1;

        $sum = 0;

        foreach ($items as $item) {
            if ($item <= 0) continue;

            $sum += $item;

            ++$count;
        }

        return $sum / $count;
    }

    public function calculatePercentile(int $percentile, array $items): int|float
    {
        sort($items);

        $index = ($percentile / 100) * count($items);

        if (intval($index) == $index) {
            return ($items[$index - 1] + $items[$index]) / 2;
        }

        return $items[intval($index)];
    }
}
