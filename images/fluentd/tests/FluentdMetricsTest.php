<?php declare(strict_types=1);

namespace KMOtrebski\Infratifacts\Images\Fluentd\Tests;

use KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander\FluentdMetricsEveryTwoSecondsStander;
use KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander\FluentdMetricsStander;
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

        $stander = new FluentdMetricsStander($this->now);
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

    public function testMetricsEntriesIndexedHaveDedicatedPluginIdData()
    {
        $this->markTestSkipped();
        //arrange

        //act

        //assert
        $this->assertAllMetricsIndexedHaveDedicatedPluginId();
    }

    private function assertAllMetricsIndexedHaveDedicatedPluginId()
    {
        //todo tu powinno być explicite czekanie maks np. 10 sekund?
        $stander = new FluentdMetricsStander($this->now);
        $metrics = $this->esHelper->waitForDataInElasticsearch($stander);

        $this->assertGreaterThan(0, count($metrics));

        foreach ($metrics as $document) {

            $id = $document['plugin_id'];

            if (strlen($id) < 1 || is_int(strpos($id, 'object:', 0))) {

                $fmt = 'Found document with generic document id! Document: %s';
                $msg = sprintf($fmt, json_encode($document, JSON_PRETTY_PRINT));
                throw new \Exception($msg);
            }

        }

        $this->assertTrue(true);
    }
}
