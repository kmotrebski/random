<?php declare(strict_types=1);

namespace Images\Fluentd\Tests;

class FloatMetricDataStander extends MetricDataStander
{
    public function __construct(float $expectedValue, string $index)
    {
        $this->value = $expectedValue;
        $this->index = $index;
    }

    protected function isDocWithValue(
        array $document
    ) : bool {

        $actualValue = $document['floatValue'];

        if (is_float($actualValue) === false) {
            return false;
        }

        $diff = abs($actualValue - $this->value);

        if ($diff <= 0.000001) {
            return true;
        }

        return false;
    }
}
