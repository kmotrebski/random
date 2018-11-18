<?php declare(strict_types=1);

namespace Images\Fluentd\Tests;

class AppLogsIntegrationTest extends TestCase
{
    /**
     * @var ESHelper $esHelper
     */
    protected $esHelper;

    /**
     * @throws \Exception
     */
    public function setUp()
    {
        parent::setUp();

        //wait for Fluentd

        $fHost = $this->config->fluentd->host;
        $fPort = $this->config->fluentd->port;

        $fluentdHelper = new FluentdHelper(
            $fHost,
            $fPort
        );
        $fluentdHelper->wait();

        //wait for ES

        $esHost = $this->config->elasticsearch->host;
        $esPort = $this->config->elasticsearch->port;

        $this->esHelper = new ESHelper(
            $esHost,
            $esPort
        );
        $this->esHelper->wait();
    }

    /**
     * @param string $tagEnding
     * @throws
     * @dataProvider differentPossibleTagEndings
     */
    public function testLogEntryGotIndexedIntoElasticsearchWithDifferentTagEndings(string $tagEnding)
    {
        //arrange
        $uniqueMessage = sprintf('super good and unique (%s) news!', rand(1, 100000));
        $logger = Helper::constructLoggerInstance($this->config, $tagEnding);

        //act
        $logger->notice($uniqueMessage);

        //assert
        $this->assertTrue(Helper::logEntryGotIndexedIntoElasticsearch(
            $this->esHelper,
            $uniqueMessage
        ));
    }

    public function differentPossibleTagEndings()
    {
        return [
            ['.tag'],
            ['.tag2'],
            [''],
        ];
    }

    /**
     * @throws Exception
     */
    public function testFluentdCreatesIndexTemplateForAppLogsSameAsInTheJsonFile()
    {
        //arrange
        $logger = Helper::constructLoggerInstance($this->config);

        //act
        $logger->notice('Some message');

        //assert
        $isCreated = Helper::indexTemplateForAppLogsIsCreatedAsExpected($this->esHelper);
        $this->assertTrue($isCreated);
    }
}
