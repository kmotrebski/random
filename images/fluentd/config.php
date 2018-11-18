<?php

return new \Phalcon\Config([
    'elasticsearch' => [
        'host' => 'elastic',
        'port' => 9200,
    ],
    'fluentd' => [
        'host' => 'fluentd',
        'port'  => 24224,
        'tagbase' => 'php.app.logs',
    ],
]);
