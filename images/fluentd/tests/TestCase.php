<?php declare(strict_types=1);

namespace Images\Fluentd\Tests;

use \Phalcon\Config;

class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Config $config
     */
    protected $config;

    /**
     * Initiates configuration component.
     */
    public function setUp()
    {
        $basePath = '/var/www/html';

        $this->config = require $basePath.'/config.php';

        $this->config->basepath = $basePath;
    }
}
