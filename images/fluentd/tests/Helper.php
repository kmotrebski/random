<?php declare(strict_types=1);

namespace Images\Fluentd\Tests;

use Elasticsearch\Common\Exceptions\Missing404Exception;
use Library\Logging\Logger;
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
        $waitTill = microtime(true) + $waitTime;

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

        $msgFmt = 'Document "%s" is not there! Waited %s s with %s s intervals and failed!';
        $msg = sprintf($msgFmt, $expectedMessage, $waitTime, $interval);
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
        self::checkHasExpectedFields($template);

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
    private static function readTemplate(
        ESHelper $helper,
        string $name
    ) : array {

        //setup
        $waitTime = 90.0;
        $interval = 0.5;

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
    protected static function checkIndexPattern(
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
    private static function checkMappingIsStrict(
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
     * @return bool
     * @throws InvalidTemplateException
     */
    protected static function checkHasExpectedFields(array $template)
    {
        $expected = self::expectedMapping();

        $actual = $template['mappings']['app_logs']['properties'];

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
}
