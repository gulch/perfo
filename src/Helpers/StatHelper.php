<?php

declare(strict_types=1);

namespace Perfo\Helpers;

use function count, floatval, intval, is_float, sort;

class StatHelper
{
    public static function calculateMedian(array $items): int|float
    {
        // sort items by value
        sort($items, \SORT_NUMERIC);

        $middleIndex = count($items) / 2;

        if (is_float($middleIndex)) {
            return $items[intval($middleIndex)];
        }

        return ($items[$middleIndex] + $items[$middleIndex - 1]) / 2;
    }

    public static function calculateAverage(array $items): int|float
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

    public static function calculatePercentile(int $percentile, array $items): int|float
    {
        // sort items by value
        sort($items, \SORT_NUMERIC);

        // get index of item at percentile position 
        $index = ($percentile / 100) * count($items);

        if (intval($index) === $index) {
            return ($items[$index - 1] + $items[$index]) / 2;
        }

        return $items[intval($index)];
    }
}
