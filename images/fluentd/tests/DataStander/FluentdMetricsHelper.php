<?php declare(strict_types=1);

namespace Omcs\Infratifacts\Images\Fluentd\Tests\DataStander;

use Omcs\Infratifacts\Images\Fluentd\Tests\Exception;
use Omcs\Infratifacts\Images\Fluentd\Tests\Helper;

class FluentdMetricsHelper
{
    public static function checkIfDocumentsAreValid(array $docs): bool
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

    public static function filterDocsWithinGivenLastSeconds(
        array $docs,
        \DateTimeImmutable $now,
        int $seconds
    ): array {
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

    /**
     * @param array $docs
     * @return int[] plugin ID as key and integers of number of occurrences.
     */
    public static function countOccurencesPerPlugin(
        array $docs
    ): array {
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

    public static function checkIfStatsAreNotEmpty(
        array $stats
    ): bool {
        if (0 === count($stats)) {
            throw new NotYetException('Stats are empty!');
        }

        return true;
    }

    public static function getDocsForPluginId(
        array $docs,
        string $pluginId
    ): array {
        $filtered = [];

        foreach ($docs as $doc) {

            if ($pluginId === $doc['plugin_id']) {
                $filtered[] = $doc;
            }
        }

        return $filtered;
    }
}
