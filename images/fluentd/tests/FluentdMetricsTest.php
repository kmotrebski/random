<?php declare(strict_types=1);

namespace KMOtrebski\Infratifacts\Images\Fluentd\Tests;

use KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander\AllFluentdMetricsUseCustomPluginIdStander;
use KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander\FluentdMetricsEveryTwoSecondsStander;
use KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander\FluentdValidMetrisStander;
use KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander\IndexTemplateStander;

class FluentdMetricsTest extends TestCase
{
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

    public function testLogsInternalFluentdMetricsIntoDedicatedElasticsearchIndex()
    {
        //arrange

        //act

        //assert
        $this->assertSomeMetricsHaveBeenIndexedIntoElasticsearch();
    }

    private function assertSomeMetricsHaveBeenIndexedIntoElasticsearch()
    {
        $this->waitForMetricsTemplate();

        $stander = new FluentdValidMetricsStander($this->now);
        $docs = $this->esHelper->waitForDataInElasticsearch($stander);

        $this->assertTemplateMappingIsAsExpected();
        $this->assertMappingOfIndexIsAsExpected();

        $this->assertGreaterThanOrEqual(1, count($docs));
    }

    private function waitForMetricsTemplate()
    {
        $name = 'template_fluentd_metrics_001';
        $stander = new IndexTemplateStander($name);
        $this->esHelper->waitForDataInElasticsearch($stander);
    }

    public function testEveryPluginReceivesMetricEntryEveryTwoSecondsOnAverage()
    {
        //arrange

        //act

        //assert
        $this->assertPluginGetsMetricsEveryTwoSeconds();
    }

    private function assertPluginGetsMetricsEveryTwoSeconds()
    {
        $stander = new FluentdMetricsEveryTwoSecondsStander();
        $this->esHelper->waitForDataInElasticsearch($stander);
        $this->assertTrue(true);
    }

    public function testReceivesPluginMetricsOmcsSummary()
    {
        //arrange

        //act

        //assert
        $this->assertReceivesPluginMetricsOmcsSummary();
    }

    private function assertReceivesPluginMetricsOmcsSummary()
    {
        $stander = new FluentdMetricsOwnOmcsSummaryStander();
        $this->esHelper->waitForDataInElasticsearch($stander);
        $this->assertTrue(true);
    }

    private function assertTemplateMappingIsAsExpected()
    {
        $actual = $this->esHelper->getTemplate('template_fluentd_metrics_001');
        $actualAsJson = json_encode($actual, JSON_PRETTY_PRINT);

        $fileName = 'template_fluentd_metrics.json';
        $expectedAsJson = $this->loadConfigJson($fileName);
        $expectedAsJson = json_encode(json_decode($expectedAsJson, true), JSON_PRETTY_PRINT);
        $this->assertJsonStringEqualsJsonString($expectedAsJson, $actualAsJson);
    }

    private function assertMappingOfIndexIsAsExpected()
    {
        $index = Helper::getFluentdMetricsIndexName($this->now);
        $actual = $this->esHelper->getMapping($index);

        $actualMappings = $actual['mappings'];
        $actualAsJson = json_encode($actualMappings, JSON_PRETTY_PRINT);

        $expectedAsJson = $this->getExpectedIndexMapping();

        $this->assertJsonStringEqualsJsonString($expectedAsJson, $actualAsJson);
    }

    private function getExpectedIndexMapping()
    {
        $expectedAsJson = $this->loadConfigJson('template_fluentd_metrics.json');
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

    public function testAllMetricsDocumentsHasCustomPluginIds()
    {
        //arrange

        //act

        //assert
        $this->assertAllMetricsDocumentsHasCustomPluginId();
    }

    private function assertAllMetricsDocumentsHasCustomPluginId()
    {
        $stander = new AllFluentdMetricsUseCustomPluginIdStander($this->now);
        $this->esHelper->waitForDataInElasticsearch($stander);
        $this->assertTrue(true);
    }
}
