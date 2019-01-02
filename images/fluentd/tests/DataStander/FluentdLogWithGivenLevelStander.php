<?php declare(strict_types=1);

namespace KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander;

use KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander;
use KMOtrebski\Infratifacts\Images\Fluentd\Tests\ESHelper;
use KMOtrebski\Infratifacts\Images\Fluentd\Tests\Helper;

class FluentdLogWithGivenLevelStander implements DataStander
{
    /**
     * @var string $level
     */
    private $level;

    /**
     * @var \DateTimeImmutable
     */
    private $time;

    /**
     * @var null|array $docs null of no data found yet or array of docs.
     */
    private $docs;

    public function __construct(\DateTimeImmutable $time, string $level)
    {
        $this->level = $level;
        $this->time = $time;
        $this->docs = null;
    }

    public function isDataThere(ESHelper $helper): bool
    {
        $this->docs = null;

        $index = Helper::getFluentdLogsIndexNameForTime($this->time);
        $type = 'logs';
        $docs = $helper->getAllDocuments($index, $type);

        foreach ($docs as $doc) {

            Helper::checkIfValidFluentdLogDocument($doc);

            if ($this->level === $doc['severity']) {
                $this->docs = $docs;
                return true;
            }
        }

        return false;
    }

    public function getData(ESHelper $helper)
    {
        // TODO: Implement getData() method.
    }
}
