<?php declare(strict_types=1);

/*
 * This file is executed by Fluentd every 5s to get all plugins metrics and sum
 * them up into single entry so that the chart in Grafana is more clear.
 */

#todo rewrite to ruby
$url = 'http://localhost:24220/api/plugins.json';
$expected = 9;

function fail(string $message)
{
    $format = "%s\n";
    echo sprintf($format, $message);
    exit(3);
}

function loadData(string $url) : array
{
    $contents = file_get_contents($url);

    if ($contents === false) {
        fail('Cannot load Fluentd metrics.');
    }

    $decoded = json_decode($contents);
    $error = json_last_error();

    if ($error !== JSON_ERROR_NONE) {
        $fmt = sprintf('Cannot JSON decode: %s, error: %s');
        $msg = sprintf($fmt, $contents, $error);
        fail($msg);
    }

    if (isset($decoded->plugins) === false) {
        fail('Cannot find plugins');
    }

    return $decoded->plugins;
}

function filterOnlyOutput(array $pluginsData) : array
{
    $output = [];

    foreach ($pluginsData as $pluginData) {

        if ($pluginData->output_plugin === true) {
            $output[] = $pluginData;
        }
    }

    return $output;
}

function sumValues(string $key, array $metrics) : int
{
    $sum = 0;

    foreach ($metrics as $metric) {

        $sum = $sum + $metric->{$key};
    }

    return $sum;
}

function prepareSummary(array $outputPlugins) : \stdClass
{
    $buffer_queue_length = sumValues('buffer_queue_length', $outputPlugins);
    $buffer_total_queued_size = sumValues('buffer_total_queued_size', $outputPlugins);
    $retry_count = sumValues('retry_count', $outputPlugins);

    $output = [
        'plugin_id' => 'omcs_summary',
        'plugin_category' => 'output',
        'type' => 'summary',
        'output_plugin' => true,
        'buffer_queue_length' => $buffer_queue_length,
        'buffer_total_queued_size' => $buffer_total_queued_size,
        'retry_count' => $retry_count,
    ];

    return (object) $output;
}

$pluginsData = loadData($url);
$outputPlugins = filterOnlyOutput($pluginsData);

if (count($outputPlugins) !== $expected) {
    $msg = sprintf(
        'Expected %s, got %s.',
        $expected,
        count($outputPlugins)
    );
    fail($msg);
}

$summary = prepareSummary($outputPlugins);

echo json_encode($summary, JSON_PRETTY_PRINT);
