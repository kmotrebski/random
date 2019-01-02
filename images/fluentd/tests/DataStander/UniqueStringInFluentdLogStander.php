<?php declare(strict_types=1);

namespace KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander;

use KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander;
use KMOtrebski\Infratifacts\Images\Fluentd\Tests\ESHelper;
use KMOtrebski\Infratifacts\Images\Fluentd\Tests\Exception;
use KMOtrebski\Infratifacts\Images\Fluentd\Tests\Helper;

class UniqueStringInFluentdLogStander implements DataStander
{
    /**
     * @var string $uniqueString
     */
    private $uniqueString;

    /**
     * @var \DateTimeImmutable $now
     */
    private $now;

    /**
     * @var null|array $document null of no data found yet or an document as an
     * array.
     */
    protected $document;

    public function __construct(
        \DateTimeImmutable $now,
        string $uniqueString
    ) {
        $this->now = $now;
        $this->uniqueString = $uniqueString;
        $this->document = null;
    }

    public function isDataThere(ESHelper $helper): bool
    {
        $this->document = null;

        $index = Helper::getFluentdLogsIndexNameForTime($this->now);
        $type = 'logs';

        $docs = $helper->getAllDocuments($index, $type);

        foreach ($docs as $doc) {

            Helper::checkIfValidFluentdLogDocument($doc);

            if (self::isStringInsideDocument($doc)) {
                $this->document = $doc;
                return true;
            }
        }

        return false;
    }

    private function isStringInsideDocument(array $doc) : bool
    {
        $isThere = strpos($doc['message'], $this->uniqueString, 0);

        if (is_int($isThere) && $isThere >= 0) {
            return true;
        }

        return false;
    }

    public function getData(ESHelper $helper)
    {
        if (null !== $this->document) {
            return $this->document;
        }

        $fmt = 'Have not found a document with unique string="%s" for date=%s yet.';
        $msg = sprintf(
            $fmt,
            $this->uniqueString,
            $this->now->format('Y-m-d H:i:s')
        );
        throw new Exception($msg);
    }
}
