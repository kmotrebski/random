<?php declare(strict_types=1);

namespace KMOtrebski\Infratifacts\Images\Fluentd\Tests;

use KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander\FloatMetricDataStander;
use KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander\IntMetricDataStander;
use KMOtrebski\Library\Clock\MockTime;
use KMOtrebski\Library\Metrics\FluentdCollector;

class PhpAppMetricsCollectionTest extends TestCase
{
    /**
     * @var string
     */
    const ES_TYPE = 'metrics';

    /**
     * @var string
     */
    const TEMPLATE_NAME = 'template_php_app_metrics001';

    /**
     * @var string
     */
    const INDEX_PATTERN = 'php_app_metrics001*';

    /**
     * @var string
     */
    const INDEX_PREFIX = 'php_app_metrics001';

    /**
     * @var string
     */
    const FLUENTD_TAG = 'php_app_metrics001';

    /**
     * @var ESHelper $esHelper
     */
    protected $esHelper;

    /**
     * @var FluentdCollector $collector
     */
    private $collector;

    /**
     * @var MockTime $clock
     */
    private $clock;

    /**
     * @var string $key
     */
    private $key;

    /**
     * @var int|float $value value to be streamed
     */
    private $value;

    /**
     * @throws
     */
    public function setUp()
    {
        parent::setUp();

        $this->waitForFluentd();
        $this->setUpElasticsearchHelper();
        $this->esHelper->wait();

        $this->clock = MockTime::constructFromDateFormatted('2018-12-08 21:00:00.000001');

        $this->collector = new FluentdCollector(
            $this->clock,
            $this->config->fluentd->host,
            $this->config->fluentd->port,
            self::FLUENTD_TAG,
            'someService',
            'someProcess'
        );

        $this->key = 'key' . rand(1,10000);
        $this->value = null;
    }

    private function waitForFluentd()
    {
        $fHost = $this->config->fluentd->host;
        $fPort = $this->config->fluentd->port;

        $fluentdHelper = new FluentdHelper(
            $fHost,
            $fPort,
            24444
        );
        $fluentdHelper->wait();
    }

    private function setUpElasticsearchHelper()
    {
        $esHost = $this->config->elasticsearch->host;
        $esPort = $this->config->elasticsearch->port;

        $this->esHelper = new ESHelper(
            $esHost,
            $esPort
        );
    }

    /**
     * @throws
     */
    public function testFloatMetricEntryGotIndexedIntoElasticsearch()
    {
        //arrange
        $uniqueValue = $this->getRandomFloat();

        //act
        $this->collector->gatherFloat($this->key, $uniqueValue);

        //assert
        $this->assertFloatMetricEntryGotIndexedIntoElasticsearch($uniqueValue);
    }

    private function getRandomFloat() : float
    {
        $asInt = rand(0,9999);
        $asFloat = $asInt / 10000;
        return $asFloat;
    }

    private function assertFloatMetricEntryGotIndexedIntoElasticsearch(
        float $expectedValue
    ) {
        $index = $this->getExpectedIndexName();
        $stander = new FloatMetricDataStander($expectedValue, $index);
        $metricDocument = $this->esHelper->waitForDataInElasticsearch($stander);
        $this->assertIsValidMetricDocument($metricDocument);
    }

    private function getExpectedIndexName() : string
    {
        $now = $this->clock->getNow();
        $dateSuffix = $now->format('Ymd_H');
        return $this->getIndexForDateSuffix($dateSuffix);
    }

    private function getIndexForDateSuffix(string $dateSuffix) : string
    {
        return sprintf('%s_%s', self::INDEX_PREFIX, $dateSuffix);
    }

    private function assertIsValidMetricDocument(array $document)
    {
        $this->assertEquals('someService', $document['microservice']);
        $this->assertEquals('someProcess', $document['process']);
        $this->assertEquals($this->key, $document['key']);
        $this->assertArrayHasKey('intValue', $document);
        $this->assertArrayHasKey('floatValue', $document);
        $this->assertIsInt($document['epoch_micros'], 'Epoch in microseconds missing');
    }

    public function testIntegerMetricEntryGotIndexedIntoElasticsearch()
    {
        //arrange

        //act
        $this->gatherIntMetric();

        //assert
        $this->assertIntegerMetricEntryGotIndexedIntoElasticsearch($this->value);
    }

    private function gatherIntMetric()
    {
        $this->value = rand(0,999999);
        $this->collector->gatherInt($this->key, $this->value);
    }

    private function assertIntegerMetricEntryGotIndexedIntoElasticsearch(
        int $expectedValue
    ) {
        $index = $this->getExpectedIndexName();
        $stander = new IntMetricDataStander($expectedValue, $index);
        $document = $this->esHelper->waitForDataInElasticsearch($stander);
        $this->assertIsValidMetricDocument($document);
    }

    /**
     * @param string $time time in UTC "Y-m-d H:i:s.u" format
     * @param string expected index date suffix
     * @throws
     * @dataProvider listOfTimesWhenRecordIsStreamedAndExpectedIndexNames
     */
    public function testMetricsAreIndexedIntoHourlyIndexBasedOnLogTimeField(
        string $time,
        string $indexDateSuffix
    ) {
        //arrange
        $this->arrangeTime($time);

        //act
        $this->gatherIntMetric();

        //assert
        $this->assertMetricGotIndexedIntoHourlyIndex($this->value, $indexDateSuffix);
    }

    private function arrangeTime(string $time)
    {
        $now = new \DateTimeImmutable($time, new \DateTimeZone('UTC'));
        $this->clock->changeNow($now);
    }

    public function listOfTimesWhenRecordIsStreamedAndExpectedIndexNames()
    {
        return [
            [
                'time' => '2013-11-10 23:59.59.999999',
                'indexSuffix' => '20131110_23',
            ],
            [
                'time' => '2013-11-11 00:00.00.000000',
                'indexSuffix' => '20131111_00',
            ],
            [
                'time' => '2013-11-11 00:00.00.000001',
                'indexSuffix' => '20131111_00',
            ],
            [
                'time'  => '2018-01-10 12:59.59.999999',
                'indexSuffix' => '20180110_12',
            ],
            [
                'time'  => '2018-01-10 13:00.00.000000',
                'indexSuffix' => '20180110_13',
            ],
            [
                'time'  => '2018-01-10 13:00.00.000001',
                'indexSuffix' => '20180110_13',
            ],
            [
                'time'  => '2020-06-05 05:59.59.999999',
                'indexSuffix' => '20200605_05',
            ],
            [
                'time'  => '2020-06-05 06:00.00.000000',
                'indexSuffix' => '20200605_06',
            ],
            [
                'time'  => '2020-06-05 06:00.00.000001',
                'indexSuffix' => '20200605_06',
            ],
        ];
    }

    private function assertMetricGotIndexedIntoHourlyIndex(
        int $expectedValue,
        string $dateSuffix
    ) {
        $index = $this->getIndexForDateSuffix($dateSuffix);
        $stander = new IntMetricDataStander($expectedValue, $index);
        $document = $this->esHelper->waitForDataInElasticsearch($stander);
        $this->assertIsValidMetricDocument($document);
    }

    public function testFluentdCreatesIndexTemplateForMetrics()
    {
        //arrange

        //act
        $this->gatherIntMetric();

        //assert
        $this->assertValidTemplateForMetricsIsCreated();
    }

    private function assertValidTemplateForMetricsIsCreated()
    {
        $template = Helper::readTemplate($this->esHelper, self::TEMPLATE_NAME);
        $isPatternOk = Helper::checkIndexPattern($template, self::INDEX_PATTERN);
        $this->assertTrue($isPatternOk);

        $isMappingStrict = Helper::checkMappingIsStrict($template, self::ES_TYPE);
        $this->assertTrue($isMappingStrict);

        $expectedMapping = ExpectedMappings::metrics();
        $hasExpectedFields = Helper::checkHasExpectedFields($template, self::ES_TYPE, $expectedMapping);
        $this->assertTrue($hasExpectedFields);
    }

    public function tearDown()
    {
        unset($this->esHelper);
        $this->collector->close();
        unset($this->collector);
        unset($this->clock);
        parent::tearDown();
    }
}
