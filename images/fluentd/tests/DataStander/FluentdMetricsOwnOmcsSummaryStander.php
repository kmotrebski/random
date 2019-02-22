<?php declare(strict_types=1);

namespace Omcs\Infratifacts\Images\Fluentd\Tests\DataStander;

use Omcs\Infratifacts\Images\Fluentd\Tests\DataStander;
use Omcs\Infratifacts\Images\Fluentd\Tests\ESHelper;
use Omcs\Infratifacts\Images\Fluentd\Tests\Helper;

class FluentdMetricsOwnOmcsSummaryStander implements DataStander
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

        return self::areMetricsFromOmcsOwnSummaryThere($docs, $now);
    }

    private static function areMetricsFromOmcsOwnSummaryThere(
        array $docs,
        \DateTimeImmutable $now
    ) : bool {

        FluentdMetricsHelper::checkIfDocumentsAreValid($docs);
        $recentDocs = FluentdMetricsHelper::filterDocsWithinGivenLastSeconds($docs, $now, 10);
        $stats = FluentdMetricsHelper::countOccurencesPerPlugin($recentDocs);
        FluentdMetricsHelper::checkIfStatsAreNotEmpty($stats);

        $pluginId = 'omcs_summary';

        if (isset($stats[$pluginId]) === false) {
            return false;
        }

        $times = $stats[$pluginId];

        if (1 <= $times && $times <= 3) {
            return true;
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

    public function getData(ESHelper $helper)
    {
        return null;
    }
}
