<?php declare(strict_types=1);

namespace Omcs\Infratifacts\Images\Fluentd\Tests\DataStander;

use Omcs\Infratifacts\Images\Fluentd\Tests\DataStander;
use Omcs\Infratifacts\Images\Fluentd\Tests\ESHelper;
use Omcs\Infratifacts\Images\Fluentd\Tests\Helper;

class AllFluentdMetricsUseCustomPluginIdStander implements DataStander
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
            self::assertDocUsesCustomPluginId($doc);
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

    private static function assertDocUsesCustomPluginId(array $doc) : bool
    {
        if (self::hasDocCustomPluginId($doc)) {
            return true;
        }

        $fmt = 'Encountered document with generic plugin id! Doc: %s';
        $docAsJson = json_encode($doc, JSON_PRETTY_PRINT);
        $msg = sprintf($fmt, $docAsJson);
        throw new InvalidDataException($msg);
    }

    private static function hasDocCustomPluginId(array $doc) : bool
    {
        $lookupResult = strpos($doc['plugin_id'], 'object:', 0);

        if (is_int($lookupResult)) {
            //found generic "object:" plugin id
            return false;
        }

        if (false !== $lookupResult) {
            //lookup result different than expected "false"
            return false;
        }

        return true;
    }
}
