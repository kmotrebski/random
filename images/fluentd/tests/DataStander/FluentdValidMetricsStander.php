<?php declare(strict_types=1);

namespace Omcs\Infratifacts\Images\Fluentd\Tests\DataStander;

use Omcs\Infratifacts\Images\Fluentd\Tests\DataStander;
use Omcs\Infratifacts\Images\Fluentd\Tests\ESHelper;
use Omcs\Infratifacts\Images\Fluentd\Tests\Exception;
use Omcs\Infratifacts\Images\Fluentd\Tests\Helper;

class FluentdValidMetricsStander implements DataStander
{
    /**
     * @var \DateTimeImmutable $time time to use to find target index name.
     */
    private $time;

    /**
     * @var null|array $docs null if no data found yet or list of documents
     * if found any.
     */
    private $docs;

    public function __construct(\DateTimeImmutable $time)
    {
        $this->time = $time;
        $this->docs = null;
    }

    public function isDataThere(ESHelper $helper): bool
    {
        $this->docs = null;
        $index = Helper::getFluentdMetricsIndexName($this->time);
        $type = 'docs';
        $docs = $helper->getAllDocuments($index, $type);

        if (0 === count($docs)) {
            return false;
        }

        foreach ($docs as $doc) {
            self::checkDocument($doc);
        }

        $this->docs = $docs;
        return true;
    }

    public function getData(ESHelper $helper) : array
    {
        if (null === $this->docs) {
            throw new NotYetException('Data are not yet there!');
        }

        return $this->docs;
    }

    private static function checkDocument(array $doc) : bool
    {
        if (true === Helper::isValidFluentdMetricDocument($doc)) {
            return true;
        }

        $fmt = 'Encountered invalid document: %s';
        $docAsJson = json_encode($doc, JSON_PRETTY_PRINT);
        $msg = sprintf($fmt, $docAsJson);
        throw new Exception($msg);
    }
}
