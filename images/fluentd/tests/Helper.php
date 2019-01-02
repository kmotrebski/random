<?php declare(strict_types=1);

namespace KMOtrebski\Infratifacts\Images\Fluentd\Tests;

use Elasticsearch\Common\Exceptions\Missing404Exception;
use KMOtrebski\Library\Logging\Logger;
use Phalcon\Config;

class Helper
{
    /**
     * @var string index that all log entries are expected to be indexed by
     * Fluentd.
     */
    const INDEX = 'app_logs';

    /**
     * @param ESHelper $helper
     * @param string $expectedMessage
     * @return bool
     * @throws Exception|Missing404Exception if document cannot be found within timelimit
     */
    public static function logEntryGotIndexedIntoElasticsearch(
        ESHelper $helper,
        string $expectedMessage
    ) : bool {

        //setup
        $waitTime = 10.0;
        $interval = 0.3;

        //max time
        $startAt = microtime(true);
        $waitTill = $startAt + $waitTime;

        while (true) {

            $documents = $helper->getAllDocuments(self::INDEX, self::INDEX);

            foreach ($documents as $document) {

                if ($document['message'] === $expectedMessage) {
                    //document found!
                    return true;
                }

            }

            $now = microtime(true);

            if ($now > $waitTill) {
                // stop trying, takes too long time
                break;
            }

            $intWait = (int) (1000 * 1000 * $interval);
            usleep($intWait);
        }

        $now = microtime(true);
        $actualWaiting = $now - $startAt;

        $msgFmt = 'Document "%s" is not there! Waited %s s with %s s intervals and failed!';
        $msg = sprintf($msgFmt, $expectedMessage, $actualWaiting, $interval);
        throw new Exception($msg);
    }

    /**
     * @param ESHelper $helper
     * @param string $json expected template as JSON
     * @return bool
     * @throws
     */
    public static function indexTemplateForAppLogsIsCreatedAsExpected(
        ESHelper $helper
    ) : bool {

        $name = 'template_app_logs_v1';
        $template = self::readTemplate($helper, $name);

        self::checkIndexPattern($template, 'app_logs*');
        self::checkMappingIsStrict($template, 'app_logs');

        $expected = self::expectedMapping();
        self::checkHasExpectedFields($template, 'app_logs', $expected);

        return true;
    }

    /**
     * @param ESHelper $helper
     * @param string $json expected template as JSON
     * @return bool
     * @throws
     */
    public static function indexTemplateForTracklogIsCreated(
        ESHelper $helper
    ) : bool {

        $name = 'template_tracklog_v1';
        $template = self::readTemplate($helper, $name);

        self::checkIndexPattern($template, 'tracklog*');
        self::checkMappingIsStrict($template, 'raw');

        return true;
    }

    /**
     * @param ESHelper $helper
     * @param string $json expected template as JSON
     * @return bool
     * @throws
     */
    public static function indexTemplateForLatencyMetricsIsCreated(
        ESHelper $helper
    ) : bool {

        $name = 'template_skrynt_v1';
        $template = self::readTemplate($helper, $name);

        self::checkIndexPattern($template, 'skrynt*');
        self::checkMappingIsStrict($template, 'latency');

        return true;
    }

    /**
     * Waits for template to be created and reads it.
     *
     * @param ESHelper $helper
     * @param string $name template name
     * @return array
     * @throws Exception
     */
    public static function readTemplate(
        ESHelper $helper,
        string $name
    ) : array {

        //setup
        $waitTime = 10.0;
        $interval = 0.2;

        //max time
        $waitTill = microtime(true) + $waitTime;

        while (true) {

            //wait some time

            $now = microtime(true);

            if ($now > $waitTill) {
                // stop trying, takes too long time
                break;
            }

            $intWait = (int) (1000 * 1000 * $interval);
            usleep($intWait);

            //evaluate

            $template = $helper->getTemplate($name);

            if (null !== $template) {
                return $template;
            }
        }

        $msgFmt = 'Template "%s" is not there! Waited %s s with %s s intervals and failed!';
        $msg = sprintf($msgFmt, $name, $waitTime, $interval);
        throw new Exception($msg);
    }

    /**
     * @param array $template
     * @param string $expected expected pattern
     * @return bool
     * @throws InvalidTemplateException
     */
    public static function checkIndexPattern(
        array $template,
        string $expected
    ) : bool {

        if ($expected === $template['template']) {
            return true;
        }

        $msgFmt = '"%s" is unexpected template!';
        $msg = sprintf($msgFmt, $template['template']);
        throw new InvalidTemplateException($msg);
    }

    /**
     * @param array $template
     * @return bool
     * @throws InvalidTemplateException
     */
    public static function checkMappingIsStrict(
        array $template,
        string $type
    ) : bool {
        $mapperDynamic = $template['settings']['index']['mapper']['dynamic'];

        if ('false' !== $mapperDynamic) {
            $msgFmt = 'Dynamic maping is different than disabled! %s passed!';
            $msg = sprintf($msgFmt, $mapperDynamic);
            throw new InvalidTemplateException($msg);
        }

        $mappingDynamic = $template['mappings'][$type]['dynamic'];

        if ('strict' !== $mappingDynamic) {
            $msgFmt = 'Dynamic in "mapping" is different than "strict", %s passed!';
            $msg = sprintf($msgFmt, $mappingDynamic);
            throw new InvalidTemplateException($msg);
        }

        return true;
    }

    /**
     * @param array $template
     * @param array $expected
     * @return bool
     * @throws InvalidTemplateException
     */
    public static function checkHasExpectedFields(array $template, string $type, array $expected)
    {
        $actual = $template['mappings'][$type]['properties'];

        if (true === self::areFieldsVerySame($actual, $expected)) {
            return true;
        }

        $msgFmt = 'Fields properties different then expected, template evaluated: %s, expected: %s.';
        $prettyEvaluated = json_encode($template, JSON_PRETTY_PRINT);
        $prettyExpected = json_encode($expected, JSON_PRETTY_PRINT);
        $msg = sprintf($msgFmt, $prettyEvaluated, $prettyExpected);
        throw new InvalidTemplateException($msg);
    }

    protected static function expectedMapping()
    {
        return [
            'microservice' =>
                [
                    'type' => 'keyword',
                ],
            'process' =>
                [
                    'type' => 'keyword',
                ],
            'severity' =>
                [
                    'type' => 'keyword',
                ],
            'severity_int' =>
                [
                    'type' => 'integer',
                ],
            'message' =>
                [
                    'type' => 'text',
                ],
            'timestamp' =>
                [
                    'format' => 'yyyy-MM-dd HH:mm:ss.SSSSSS',
                    'type' => 'date',
                ],
            'epoch_micros' =>
                [
                    'type' => 'long',
                ],
        ];
    }

    /**
     * Makes sure array values on 1st and 2nd dimentions are the same.
     *
     * @param array $actual
     * @param array $expected
     * @return bool
     * @throws InvalidTemplateException
     */
    protected static function areFieldsVerySame(
        array $actual,
        array $expected
    ) : bool {

        self::allFieldsInFooArePresentInBoo($expected, $actual);
        self::allFieldsInFooArePresentInBoo($actual, $expected);

        return true;
    }

    /**
     * Compares values (without order) on 1st and 2nd dimensions.
     *
     * @param array $foo
     * @param array $boo
     * @return bool
     * @throws InvalidTemplateException
     */
    protected static function allFieldsInFooArePresentInBoo(
        array $foo,
        array $boo
    ) : bool {

        foreach ($foo as $key1 => $value1) {
            foreach ($value1 as $key2 => $value2) {

                if (false === isset($boo[$key1][$key2])) {

                    $fmt = 'Value "%s" for %s/%s is missing in boo.';
                    $msg = sprintf($fmt, $value2, $key1, $key2);
                    throw new InvalidTemplateException($msg);
                }

                if ($boo[$key1][$key2] !== $value2) {

                    $fmt = 'Value "%s" for %s/%s is different in boo.';
                    $msg = sprintf($fmt, $value2, $key1, $key2);
                    throw new InvalidTemplateException($msg);
                }
            }
        }

        return true;
    }

    public static function constructLoggerInstance(
        Config $config,
        string $tagEnding = ''
    ): Logger {
        $tag = $config->fluentd->tagbase . $tagEnding;

        $instance = Logger::constructDefault(
            $config->fluentd->host,
            $config->fluentd->port,
            $tag,
            'awesomeApp',
            'process'
        );

        return $instance;
    }

    public static function makeItIsNotCloseToMidnight()
    {
        $halfOfSecond = (int) (1000 * 1000 * 0.5);

        while (true) {
            if (false === self::isCloseToMidnight()) {
                return;
            }

            usleep($halfOfSecond);
        }
    }

    private static function isCloseToMidnight()
    {
        $now = self::getCurrentTime();

        $hour = (int) $now->format('H');
        $minute = (int) $now->format('i');
        $second = (int) $now->format('s');

        if (23 === $hour && 59 === $minute && $second >= 30) {
            return true;
        }

        if (0 === $hour && 0 === $minute && $second <= 2) {
            //wait at least till 00:00:03
            return true;
        }

        return false;
    }

    public static function getCurrentTime() : \DateTimeImmutable
    {
        $zone = new \DateTimeZone('UTC');
        return new \DateTimeImmutable('now', $zone);
    }

    public static function getFluentdLogsIndexNameForTime(\DateTimeImmutable $time) : string
    {
        $format = 'fluentd_logs_001_%s';
        $dateSuffix = $time->format('Ymd');
        $index = sprintf($format, $dateSuffix);
        return $index;
    }

    public static function getFluentdMetricsIndexName(\DateTimeImmutable $time) : string
    {
        $format = 'fluentd_metrics_001_%s';
        $dateSuffix = $time->format('Ymd');
        $index = sprintf($format, $dateSuffix);
        return $index;
    }

    public static function isValidFluentdLogDocument(array $doc) : bool
    {
        if (true === isset($doc['severity'])) {
            return true;
        }

        if (true === isset($doc['timestamp'])) {
            return true;
        }

        if (true === isset($doc['message'])) {
            return true;
        }

        return false;
    }

    public static function checkIfValidFluentdLogDocument(array $doc) : bool
    {
        if (true === self::isValidFluentdLogDocument($doc)) {
            return true;
        }

        $fmt = 'Encountered invalid document: %s';
        $msg = sprintf($fmt, json_encode($doc));
        throw new Exception($msg);
    }

    public static function isValidFluentdMetricDocument(array $doc) : bool
    {
        $fields = [
            'plugin_id',
            'plugin_category',
            'type',
            'output_plugin',
            'buffer_queue_length',
            'buffer_total_queued_size',
            'retry_count',
            'timestamp',
            'debug',
        ];

        //return true of there is any unexpected field
        foreach ($doc as $field => $value) {
            if (false === in_array($field, $fields, true)) {
                return false;
            }
        }

        return true;
    }
}
