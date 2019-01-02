<?php declare(strict_types=1);

namespace KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander;

use KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander;
use KMOtrebski\Infratifacts\Images\Fluentd\Tests\ESHelper;

abstract class MetricDataStander implements DataStander
{
    const ES_TYPE = 'metrics';

    /**
     * @var string|null $index
     */
    protected $index;

    /**
     * @var float|int $value
     */
    protected $value;

    public function isDataThere(ESHelper $helper) : bool
    {
        $documents = $helper->getAllDocuments($this->index, self::ES_TYPE);

        return $this->hasDocWithValue($documents);
    }

    /**
     * @param ESHelper $helper
     * @return array expected document
     * @throws
     */
    public function getData(ESHelper $helper)
    {
        $documents = $helper->getAllDocuments($this->index, self::ES_TYPE);

        return $this->getDocWithValue($documents);
    }

    private function hasDocWithValue(
        array $documents
    ) : bool {
        foreach ($documents as $document) {

            if ($this->isDocWithValue($document)) {
                return true;
            }
        }

        return false;
    }

    private function getDocWithValue(
        array $documents
    ) : array {

        foreach ($documents as $document) {

            if ($this->isDocWithValue($document)) {
                return $document;
            }
        }

        throw new \Exception('Cannot find document with value');
    }

    abstract protected function isDocWithValue(
        array $document
    ) : bool;
}
