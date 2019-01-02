<?php declare(strict_types=1);

namespace KMOtrebski\Infratifacts\Images\Fluentd\Tests;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Elasticsearch\Common\Exceptions\ServerErrorResponseException;
use Elasticsearch\ConnectionPool\SniffingConnectionPool;
use KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander\NotYetException;

//todo moglby byc np. funkcjonalnosc waitujaca a przekazowaloby sie spec co ma byc szukane
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

        //settings for pinging every single second
        $pool = SniffingConnectionPool::class;
        $poolConfig = ['sniffingInterval' => 1];

        $this->client = ClientBuilder::create()
            ->setHosts([$hosts])
            ->setConnectionPool($pool, $poolConfig)
            ->allowBadJSONSerialization()
            ->build();

        $this->instanceAlreadyUp = false;
    }

    /**
     * @param int
     * @return void
     * @throws Exception in case wait for more then max allowed time
     */
    public function wait(int $max = 60)
    {
        if (true === $this->instanceAlreadyUp) {
            //already up and running
            return;
        }

        $start = microtime(true);
        $waitTill = $start + ((float)$max);

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
     * @todo ServerErrorResponseException catch should not be here,
     * instead the wait function should wait that the whole cluster is up
     * and in the green state
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

        } catch (ServerErrorResponseException $e) {

            $decoded = json_decode($e->getMessage());
            $reason = $decoded->error->reason;

            if ('all shards failed' === $reason) {
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


    /**
     * @param string $name
     * @return array|null
     * @deprecated should migrate everything into getTemplate
     */
    public function getTemplate(string $name)
    {
        try {
            $this->wait();

            $response = $this->client->indices()->getTemplate([
                'name' => $name,
            ]);

            if (isset($response[$name])) {
                return $response[$name];
            }

            return $response;
        } catch (Missing404Exception $t) {
            return null;
        } catch (\Throwable $t) {
            return null;
        }
    }

    /**
     * @param string $name
     * @return bool true if template exists false otherwise
     * @throws \Exception in case template is missing
     */
    public function hasTemplate(string $name)
    {
        $this->wait();

        $response = $this->client->indices()->existsTemplate([
            'name' => $name,
        ]);

        if (is_bool($response)) {
            return $response;
        }

        $fmt = 'Unexpected response: %s';
        $msg = sprintf($fmt, json_encode($response));
        throw new Exception($msg);
    }

    /**
     * @param string $name
     * @return array
     * @throws \Exception in case template is missing
     */
    public function getTemplate2(string $name) : array
    {
        $this->wait();

        $response = $this->client->indices()->getTemplate([
            'name' => $name,
        ]);

        if (isset($response[$name])) {
            return $response[$name];
        }

        $fmt = 'Template "%s" is missing!';
        $msg = sprintf($fmt, $name);
        throw new Exception($msg);
    }

    /**
     * @param DataStander $stander
     * @param float $maxWaitTime
     * @param float $waitInt
     * @return mixed
     * @throws Exception
     */
    public function waitForDataInElasticsearch(
        DataStander $stander,
        float $maxWaitTime = 10.0,
        float $waitInt = 0.2
    ) {
        $startAt = microtime(true);
        $waitTill = $startAt + $maxWaitTime;
        $waitIntInMicroSecs = (int) ($waitInt * 1000 * 1000);

        $lastException = '';

        while (true) {

            try {
                if ($stander->isDataThere($this)) {
                    return $stander->getData($this);
                }
            } catch (NotYetException $n) {

                $lastException = (string) $n;
            }

            $now = microtime(true);

            if ($waitTill < $now) {
                break;
            }

            usleep($waitIntInMicroSecs);
        }

        $fmt = 'Waited "%s" s with "%s" s intervals using "%s" stander and ' .
               'data are still not there! Failure description: %s';
        $msg = sprintf($fmt, $maxWaitTime, $waitInt, get_class($stander), $lastException);
        throw new Exception($msg);
    }

    public function flush()
    {
        $this->deleteAllTemplates();
        $this->deleteAllIndices();
        $this->refresh();
    }

    private function deleteAllTemplates()
    {
        $params = [
            'name' => '*',
        ];
        $response = $this->client->indices()->deleteTemplate($params);

        if (isset($response['acknowledged']) && true === $response['acknowledged']) {
            return true;
        }

        $this->throwException($response);
    }

    private function deleteAllIndices()
    {
        $params = [
            'index' => '*',
        ];
        $response = $this->client->indices()->delete($params);

        if (isset($response['acknowledged']) && true === $response['acknowledged']) {
            return true;
        }

        $this->throwException($response);
    }

    private function refresh()
    {
        $params = [
            'index' => '*',
        ];
        $response = $this->client->indices()->refresh($params);

        if (false === isset($response['_shards']['failed'])) {
            $this->throwException($response);
        }

        if (0 !== $response['_shards']['failed']) {
            $this->throwException($response);
        }

        return true;
    }

    public function getMapping(string $indexName)
    {
        $response = $this->client->indices()->getMapping([
            'index' => $indexName,
        ]);

        if (isset($response[$indexName]) && is_array($response[$indexName])) {
            return $response[$indexName];
        }

        $fmt = 'Cannot find mapping for index "%s", response: %s.';
        $responseAsJson = json_encode($response, JSON_PRETTY_PRINT);
        $msg = sprintf($fmt, $indexName, $responseAsJson);
        throw new Exception($msg);
    }

    private function throwException($response)
    {
        $asJson = json_encode($response);
        $fmt = 'Request failed, response: %s';
        $msg = sprintf($fmt, $asJson);
        throw new Exception($msg);
    }
}
