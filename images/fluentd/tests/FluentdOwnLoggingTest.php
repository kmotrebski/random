<?php declare(strict_types=1);

namespace KMOtrebski\Infratifacts\Images\Fluentd\Tests;

//todo uspójnić żeby wszystkie logi szły do jednego wspólnego indeksu i dało sie rozdzielic
//todo refactoring np. ESHelper do TestCase we wszystkich Test klasachh
use Fluent\Logger\FluentLogger;
use KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander\FluentdLogWithGivenLevelStander;
use KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander\IndexTemplateStander;
use KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander\UniqueStringInFluentdLogStander;

class FluentdOwnLoggingTest extends TestCase
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
     * @var ESHelper $esHelper
     */
    protected $esHelper;

    /**
     * @var FluentdHelper $fluentdHelper
     */
    protected $fluentdHelper;

    public function setUp()
    {
        parent::setUp();

        $this->waitForElasticsearch();
        $this->waitForFluentd();

        Helper::makeItIsNotCloseToMidnight();
        $this->waitForLogTemplate();
        $this->now = Helper::getCurrentTime();
    }

    private function waitForElasticsearch()
    {
        $esHost = $this->config->elasticsearch->host;
        $esPort = $this->config->elasticsearch->port;

        $this->esHelper = new ESHelper(
            $esHost,
            $esPort
        );

        $this->esHelper->wait();
    }

    private function waitForFluentd()
    {
        $fHost = $this->config->fluentd->host;
        $fPort = $this->config->fluentd->port;

        $this->fluentdHelper = new FluentdHelper(
            $fHost,
            $fPort,
            24444
        );
        $this->fluentdHelper->wait();
    }

    public function testLogsEverythingStartingFromTraceLogsIntoDedicatedElasticsearchIndex()
    {
        //arrange

        //act

        //assert
        $this->assertSomeLogEntriesOnDifferentLevelsHaveBeenLoggedIntoElasticsearch();
    }

    private function assertSomeLogEntriesOnDifferentLevelsHaveBeenLoggedIntoElasticsearch()
    {
        $expectedLevels = ['debug', 'info'];

        foreach ($expectedLevels as $expectedLevel) {

            $stander = new FluentdLogWithGivenLevelStander($this->now, $expectedLevel);
            $this->esHelper->waitForDataInElasticsearch($stander);
        }

        $this->assertTemplateMappingIsAsExpected();
        $this->assertMappingOfIndexIsAsExpected();
    }

    private function waitForLogTemplate()
    {
        $name = 'template_fluentd_logs_001';
        $stander = new IndexTemplateStander($name);
        $this->esHelper->waitForDataInElasticsearch($stander);
    }

    public function testTryingToStreamUnexpectedRecordMakesItLoggedAsError()
    {
        //arrange

        //act
        $this->streamUnexpectedRecord();

        //assert
        $this->assertUnexpectedRecordWasLoggedAsErrorInElasticsearch();
    }

    private function streamUnexpectedRecord()
    {
        $fHost = $this->config->fluentd->host;
        $fPort = $this->config->fluentd->port;
        $logger = new FluentLogger($fHost, $fPort);

        $this->now = Helper::getCurrentTime();

        $this->uniqueString = 'krzeslo' . rand(1, 100000);

        $recordCausingAnError = [
            'nonExistentField' => $this->uniqueString,
        ];

        $logger->post('php_app_metrics001', $recordCausingAnError);
    }

    private function assertUnexpectedRecordWasLoggedAsErrorInElasticsearch()
    {
        $stander = new UniqueStringInFluentdLogStander($this->now, $this->uniqueString);
        $document = $this->esHelper->waitForDataInElasticsearch($stander);

        $this->assertDocumentHasValidTimeZone($document);

        $this->assertTemplateMappingIsAsExpected();
        $this->assertMappingOfIndexIsAsExpected();
    }

    private function assertDocumentHasValidTimeZone(array $document)
    {
        $fromDoc = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s.u',
            $document['timestamp'],
            new \DateTimeZone('UTC')
        );

        //if timezone of time formatted in document is in UTC
        //then the timestamps will be the same (max 1 sec of difference)

        $diff = $fromDoc->getTimestamp() - $this->now->getTimestamp();
        $diff = abs($diff);

        $this->assertLessThanOrEqual(5, $diff);
    }

    private function assertTemplateMappingIsAsExpected()
    {
        $actual = $this->esHelper->getTemplate('template_fluentd_logs_001');
        $actualAsJson = json_encode($actual, JSON_PRETTY_PRINT);

        $fileName = 'template_fluentd_logs.json';
        $expectedAsJson = $this->loadConfigJson($fileName);
        $expectedAsJson = json_encode(json_decode($expectedAsJson, true), JSON_PRETTY_PRINT);
        $this->assertJsonStringEqualsJsonString($expectedAsJson, $actualAsJson);
    }

    private function assertMappingOfIndexIsAsExpected()
    {
        $index = Helper::getFluentdLogsIndexNameForTime($this->now);
        $actual = $this->esHelper->getMapping($index);

        $actualMappings = $actual['mappings'];
        $actualAsJson = json_encode($actualMappings, JSON_PRETTY_PRINT);

        $expectedAsJson = $this->getExpectedIndexMapping();

        $this->assertJsonStringEqualsJsonString($expectedAsJson, $actualAsJson);
    }

    private function getExpectedIndexMapping()
    {
        $expectedAsJson = $this->loadConfigJson('template_fluentd_logs.json');
        $expectedDecoded = json_decode($expectedAsJson, true);
        $mappingsDecoded = $expectedDecoded['mappings'];
        $mappingsAsJson = json_encode($mappingsDecoded, JSON_PRETTY_PRINT);
        return $mappingsAsJson;
    }

    private function loadConfigJson($filename)
    {
        $basepath = $this->config->basepath;
        $path = sprintf('%s/fluentd-config/templates/%s', $basepath, $filename);
        $fileContents = file_get_contents($path);
        return $fileContents;
    }

    public function testsIndexDoesNotContainAnyOtherErrorsApartFromExpected()
    {
        $this->markTestSkipped();
        //todo
        //arrange
        $this->waitTillSystemCalmsDown();

        //act

        //assert
        $this->assertThereIsOnlyOneWarning();
    }

    private function waitTillSystemCalmsDown()
    {
        throw new Exception('todo');
    }

    private function assertThereIsOnlyOneWarning()
    {
        throw new Exception('todo');
    }
}
