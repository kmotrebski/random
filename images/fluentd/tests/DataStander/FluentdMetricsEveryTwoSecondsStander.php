<?php declare(strict_types=1);

namespace KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander;

use KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander;
use KMOtrebski\Infratifacts\Images\Fluentd\Tests\ESHelper;
use KMOtrebski\Infratifacts\Images\Fluentd\Tests\Exception;
use KMOtrebski\Infratifacts\Images\Fluentd\Tests\Helper;

class FluentdMetricsEveryTwoSecondsStander implements DataStander
{
    public function __construct()
    {

    }

    public function isDataThere(ESHelper $helper): bool
    {
        $now = Helper::getCurrentTime();
        $index = Helper::getFluentdMetricsIndexName($now);
        $type = 'docs';
        $docs = $helper->getAllDocuments($index, $type);

        return self::areMetricsEveryTwoSecondForEveryPlugin($docs, $now);
    }

    private static function areMetricsEveryTwoSecondForEveryPlugin(
        array $docs,
        \DateTimeImmutable $now
    ) : bool {

        self::checkIfDocumentsAreValid($docs);
        $recentDocs = self::filterDocsWithinGivenLastSeconds($docs, $now, 10);
        $stats = self::countOccurencesPerPlugin($recentDocs);
        self::checkIfStatsAreNotEmpty($stats);

        foreach ($stats as $pluginId => $times) {
            if (2 <= $times && $times <= 6) {
                continue;
            }

            $pluginDocs = self::getDocsForPluginId($recentDocs, $pluginId);
            $pluginDocsAsJson = json_encode($pluginDocs, JSON_PRETTY_PRINT);

            $fmt = 'Found %s occurances from last 10 sec at %s for plugin_id=%s. Docs: %s';
            $msg = sprintf(
                $fmt,
                $times,
                $now->format('Y-m-d H:i:s.u'),
                $pluginId,
                $pluginDocsAsJson
            );
            throw new NotYetException($msg);
        }

        return true;
    }

    private static function checkIfStatsAreNotEmpty(
        array $stats
    ) : bool {
        if (0 === count($stats)) {
            throw new NotYetException('Stats are empty!');
        }

        return true;
    }

    private static function checkIfDocumentsAreValid(array $docs) : bool
    {
        foreach ($docs as $doc) {
            if (false === Helper::isValidFluentdMetricDocument($doc)) {
                $fmt = 'Encountered invalid document: %s';
                $docAsJson = json_encode($doc, JSON_PRETTY_PRINT);
                $msg = sprintf($fmt, $docAsJson);
                throw new Exception($msg);
            }
        }

        return true;
    }

    public function getData(ESHelper $helper)
    {
        return null;
    }

    /**
     * @param array $docs
     * @return int[] plugin ID as key and integers of number of occurrences.
     */
    private static function countOccurencesPerPlugin(
        array $docs
    ) : array {
        $stats = [];

        foreach ($docs as $doc) {

            $pluginId = $doc['plugin_id'];

            if (false === isset($stats[$pluginId])) {
                $stats[$pluginId] = 0;
            }

            $stats[$pluginId]++;
        }

        return $stats;
    }

    private static function filterDocsWithinGivenLastSeconds(
        array $docs,
        \DateTimeImmutable $now,
        int $seconds
    ) : array {
        $spec = sprintf('PT%sS', $seconds);
        $someSecsAgo = $now->sub(new \DateInterval($spec));

        $filtered = [];

        foreach ($docs as $doc) {

            $docTime = \DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s.u',
                $doc['timestamp'],
                new \DateTimeZone('UTC')
            );

            if ($someSecsAgo <= $docTime) {
                $filtered[] = $doc;
            }
        }

        return $filtered;
    }

    private static function getDocsForPluginId(
        array $docs,
        string $pluginId
    ) : array {
        $filtered = [];

        foreach ($docs as $doc) {

            if ($pluginId === $doc['plugin_id']) {
                $filtered[] = $doc;
            }
        }

        return $filtered;
    }
}
