<?php declare(strict_types=1);

namespace Images\Fluentd\Tests;

class IntMetricDataStander extends MetricDataStander
{
    public function __construct(int $expectedValue, string $index)
    {
        $this->value = $expectedValue;
        $this->index = $index;
    }

    protected function isDocWithValue(
        array $document
    ) : bool {

        $actualValue = $document['intValue'];

        if (is_int($actualValue) === false) {
            return false;
        }

        if ($actualValue === $this->value) {
            return true;
        }

        return false;
    }
}
