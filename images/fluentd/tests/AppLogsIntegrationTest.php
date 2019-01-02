<?php declare(strict_types=1);

namespace KMOtrebski\Infratifacts\Images\Fluentd\Tests;

//todo wyabstrachować helpery do podfunkcji bo teraz jest za dlugi setUp()

//todo przeanalizowac standardowy config z strony fluentd https://raw.githubusercontent.com/fluent/fluentd-docker-image/master/1.1/debian-onbuild/fluent.conf
//todo ogarnac todos z fluentd loggera gdzies je ladnie umiejscic?
//todo nie musze robic refreshow przeciez w ES helperze! moge skorzystac z trików które są w Selenium tj. implicite waiting! ale refreshe są szybsze!
//todo wszystkie testy w tym suite są od siebie ZALEŻNE, co jest pewnego rodzaju anti pattern no ale póki są odpalane tylko i wyłączne bez paralelizacji to można z tym żyć. Nawet można żyć z paralelizacja jeśli stostuje unique values np. których aktywnie poszukuje. Czyli testy są niezależne od drugich nawet jak są odpalane symultanicznie. Są zależne bo tak na prawdę cały mapping a konkretnie templates są tworzone gdy jest odpalany Fluentd, więc tak na prawdę testy tylko muszą sprawdzić czy wszystkie mappingi są na miejscu. Rozwiązaniem jest dodanie do FluentdHelpera refreshowania konfiguracji Fluentd
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
            $fPort,
            24444
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
            ['.cleo'],
            ['.bojcora'],
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

    public function testFluentdCreatesIndexTemplateForTracklogs()
    {
        //arrange
        $logger = Helper::constructLoggerInstance($this->config);

        //act
        $logger->notice('Some message');

        //assert
        $this->assertTemplateForTracklogsIsCreated();
    }

    private function assertTemplateForTracklogsIsCreated()
    {
        $isCreated = Helper::indexTemplateForTracklogIsCreated($this->esHelper);
        $this->assertTrue($isCreated);
    }

    //curl -XPOST 'localhost:9200/tracklog/raw?pretty' -H 'Content-Type: application/json' -d'
    //{"annotation":"schizix","latitude":50.152964,"longitude":19.259979,"altitude":12.2,"bearing":270,"speed":0,"accuracy":8,"satellites":18,"vehicle_id":1,"provider":"gps","battery":78.8,"android":"39ds","serial":"38scd8s","track_time":"2017-02-17 06:20:30","request_time":"2017-02-17 06:20:31","apikey":"fBCskNRy1695tWhHFsuVbLotKgKYBzA9QXKc1Emp"}
    //'

    //curl -X POST -d 'json={"speed": 13.3}' http://localhost:24224/tracklog;
    //
    //{"annotation":"schizix","latitude":50.1526,"longitude":19.260126,"altitude":12.2,"bearing":270,"speed":0,"accuracy":8,"satellites":18,"vehicle_id":1,"provider":"gps","battery":78.8,"android":"39ds","serial":"38scd8s","track_time":"2017-02-17 06:20:26","request_time":"2017-02-17 06:20:27","apikey":"fBCskNRy1695tWhHFsuVbLotKgKYBzA9QXKc1Emp"}
    //{"annotation":"schizix","latitude":50.165873,"longitude":19.246999,"altitude":12.2,"bearing":270,"speed":17.9,"accuracy":8,"satellites":18,"vehicle_id":2,"provider":"gps","battery":78.8,"android":"39ds","serial":"38scd8s","track_time":"2017-02-17 06:20:27","request_time":"2017-02-17 06:20:27","apikey":"Zt4AzMkwcBVywM3VZ4GmdcVhRx8CHNw5dQsuMZTj"}
}
