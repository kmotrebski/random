<?php declare(strict_types=1);

namespace Images\Fluentd\Tests;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\Missing404Exception;

class ESHelper
{
    /**
     * @var Client $client
     */
    protected $client;

    /**
     * Stores info if instance is already up and running.
     *
     * So that helper don't waste time for multiple checking during same
     * helper instance lifetime (during execution of test suite).
     *
     * @var bool $instanceAlreadyUp
     */
    protected $instanceAlreadyUp;

    public function __construct(string $host, int $port)
    {
        $hosts = [
            'host' => $host,
            'port' => $port,
        ];

        $this->client = ClientBuilder::create()
            ->setHosts([$hosts])
            ->allowBadJSONSerialization()
            ->build();

        $this->instanceAlreadyUp = false;
    }

    /**
     * @param int
     * @return void
     * @throws Exception in case wait for more then max allowed time
     */
    public function wait(int $max = 100)
    {
        if (true === $this->instanceAlreadyUp) {
            //already up and running
            return;
        }

        $start = microtime(true);
        $waitTill = $start + ((float) $max);

        while (true) {

            if ($this->client->ping()) {
                $this->instanceAlreadyUp = true;
                return;
            }

            $oneSecond = 1 * 1000 * 1000;
            $oneTenth = $oneSecond / 10;
            usleep($oneTenth);

            //finish waiting if exceeded max time
            $now = microtime(true);

            if ($now > $waitTill) {
                $msgFmt = 'Trying to wait for Elasticsearch but it took %s s already!';
                $exMsg = sprintf($msgFmt, $max);
                throw new Exception($exMsg);
            }
        }
    }

    /**
     * @param string $index
     * @param string $type
     * @return mixed[]
     * @throws Missing404Exception
     */
    public function getAllDocuments(string $index, string $type)
    {
        $offset = 0;
        $size = 50;

        $documents = [];

        try {

            while (true) {

                $new = $this->getDocuments($index, $type, $offset, $size);

                if (0 === count($new)) {
                    return $documents;
                }

                $documents = array_merge($documents, $new);

                $offset = $offset + $size;
            }

        } catch (Missing404Exception $e) {

            $decoded = json_decode($e->getMessage());

            if (isset($decoded->error->root_cause[0]->type) && 'index_not_found_exception' === $decoded->error->root_cause[0]->type) {
                return [];
            }

            throw $e;
        }
    }

    /**
     * @param string $index
     * @param string $type
     * @param int $offset
     * @param int $size
     * @return mixed[]
     * @throws Missing404Exception
     */
    public function getDocuments(
        string $index,
        string $type,
        int $offset,
        int $size
    ) {
        $this->wait();

        $params = [
            'index' => $index,
            'type' => $type,
            'from' => $offset,
            'size' => $size,
            'body' => [
                'query' => [
                    'match_all' => (object) [],
                ],
            ],
        ];

        $response = $this->client->search($params);

        return self::readSourcesFromResponse($response);
    }

    protected static function readSourcesFromResponse(array $response) : array
    {
        if (0 === count($response['hits']['hits'])) {
            return [];
        }

        $sources = [];

        foreach ($response['hits']['hits'] as $hit) {

            $sources[] = $hit['_source'];
        }

        return $sources;
    }

    public function getTemplate(string $name)
    {
        try {
            $this->wait();

            $x = $this->client->indices()->getTemplate([
                'name' => $name,
            ]);

            if (isset($x[$name])) {
                return $x[$name];
            }

            return $x;

        } catch (Missing404Exception $t) {

            return null;


        } catch (\Throwable $t) {
            echo (string) $t;

            return null;
        }

    }

    /**
     * @param DataStander $stander
     * @param float $maxWaitTime
     * @param float $waitInt
     * @return mixed
     * @throws Exception
     */
    public function waitForDataInElasticsearch(
        DataStande $stander,
        float $maxWaitTime = 10.0,
        float $waitInt = 0.2
    ) {

        $startAt = microtime(true);
        $waitTill = $startAt + $maxWaitTime;
        $waitIntInMicroSecs = (int) ($waitInt * 1000 * 1000);

        while (true) {

            if ($stander->isDataThere($this)) {
                return $stander->getData($this);
            }

            $now = microtime(true);

            if ($waitTill < $now) {
                break;
            }

            usleep($waitIntInMicroSecs);
        }

        $fmt = 'Waited "%s" s with "%s" s intervals using "%s" stander and '.
            'data are still not there!';
        $msg = sprintf($fmt, $maxWaitTime, $waitInt, get_class($stander));
        throw new Exception($msg);
    }

}
