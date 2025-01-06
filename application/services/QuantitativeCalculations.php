<?php

class Application_Service_QuantitativeCalculations
{
    private static function isDataSetInvalid(array $dataSet): bool
    {
        return empty($dataSet) || empty(array_filter($dataSet));
    }

    public static function calculateMean(array $dataSet): float
    {
        if (self::isDataSetInvalid($dataSet)) {
            return 0.0;
        }
        return array_sum($dataSet) / count($dataSet);
    }

    public static function calculateMedian(array $dataSet): float
    {
        if (self::isDataSetInvalid($dataSet)) {
            return 0.0;
        }
        sort($dataSet);
        $count = count($dataSet);
        $middle = floor(($count - 1) / 2);
        return ($count % 2)
            ? (float) $dataSet[$middle]
            : ($dataSet[$middle] + $dataSet[$middle + 1]) / 2.0;
    }

    public static function calculateStandardDeviation(array $dataSet, ?float $mean = null): float
    {
        if (self::isDataSetInvalid($dataSet)) {
            return 0.0;
        }

        // Standard deviation of a single-element dataset is always 0
        if (count($dataSet) === 1) {
            return 0.0;
        }

        $mean ??= self::calculateMean($dataSet);
        $sumOfSquares = array_reduce($dataSet, fn($carry, $item) => $carry + pow($item - $mean, 2), 0.0);
        return sqrt($sumOfSquares / count($dataSet));
    }

    public static function calculateQuantile(array $dataSet, float $quantile): float
    {
        if (self::isDataSetInvalid($dataSet)) {
            return 0.0;
        }
        if ($quantile < 0 || $quantile > 1) {
            throw new InvalidArgumentException('Quantile must be between 0 and 1.');
        }
        sort($dataSet);
        $count = count($dataSet);
        $index = ($count - 1) * $quantile;
        $lower = floor($index);
        $upper = ceil($index);
        $weight = $index - $lower;
        return ($lower == $upper)
            ? (float) $dataSet[$lower]
            : $dataSet[$lower] * (1 - $weight) + $dataSet[$upper] * $weight;
    }

    public static function calculateZScore(float $value, array $dataSet, ?float $mean = null, ?float $stdDev = null): float
    {
        if (self::isDataSetInvalid($dataSet)) {
            return 0.0;
        }
        $mean ??= self::calculateMean($dataSet);
        $stdDev ??= self::calculateStandardDeviation($dataSet, $mean);
        return ($stdDev == 0) ? 0.0 : ($value - $mean) / $stdDev;
    }

    public static function calculateStandardUncertainty(array $dataSet, ?float $stdDev = null): float
    {
        if (self::isDataSetInvalid($dataSet)) {
            return 0.0;
        }
        $stdDev ??= self::calculateStandardDeviation($dataSet);
        return $stdDev / sqrt(count($dataSet));
    }

    public static function calculateCoefficientOfVariation(array $dataSet, ?float $mean = null, ?float $stdDev = null): float
    {
        if (self::isDataSetInvalid($dataSet)) {
            return 0.0;
        }
        $mean ??= self::calculateMean($dataSet);
        $stdDev ??= self::calculateStandardDeviation($dataSet);
        return ($mean == 0) ? 0.0 : $stdDev / $mean;
    }
}
