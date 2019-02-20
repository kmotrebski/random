<?php declare(strict_types=1);

namespace KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander;

use KMOtrebski\Infratifacts\Images\Fluentd\Tests\DataStander;
use KMOtrebski\Infratifacts\Images\Fluentd\Tests\ESHelper;
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

        FluentdMetricsHelper::checkIfDocumentsAreValid($docs);
        $recentDocs = FluentdMetricsHelper::filterDocsWithinGivenLastSeconds($docs, $now, 10);
        $stats = FluentdMetricsHelper::countOccurencesPerPlugin($recentDocs);
        $statsWithoutSummary = self::filterOutOwnSummary($stats);
        FluentdMetricsHelper::checkIfStatsAreNotEmpty($statsWithoutSummary);

        foreach ($statsWithoutSummary as $pluginId => $times) {

            if (2 <= $times && $times <= 6) {
                continue;
            }

            $pluginDocs = FluentdMetricsHelper::getDocsForPluginId($recentDocs, $pluginId);
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

    public static function filterOutOwnSummary(
        array $notFiltered
    ) : array {

        $filtered = [];

        foreach ($notFiltered as $pluginId => $value) {

            if ($pluginId !== 'omcs_summary') {
                $filtered[$pluginId] = $value;
            }

        }

        return $filtered;
    }

    public function getData(ESHelper $helper)
    {
        return null;
    }

}
