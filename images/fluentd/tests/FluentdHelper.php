<?php declare(strict_types=1);

namespace Images\Fluentd\Tests;

class FluentdHelper
{
    /**
     * @var string $host
     */
    protected $host;

    /**
     * @var int $port
     */
    protected $port;

    /**
     * Stores info if instance is already up and running.
     *
     * So that helper don't waste time for multiple checking during same
     * helper instance lifetime (during execution of test suite).
     *
     * @var bool $instanceAlreadyUp
     */
    protected $instanceAlreadyUp;

    public function __construct(string $host, int $port)
    {
        $this->host = $host;
        $this->port = $port;
        $this->instanceAlreadyUp = false;
    }

    /**
     * @param float $maxTime
     * @return void
     * @throws \Exception
     */
    public function wait(float $maxTime = 60.0)
    {
        if (true === $this->instanceAlreadyUp) {
            //already up and running
            return;
        }

        $start = microtime(true);

        while (true) {

            if ($this->ping()) {
                $this->instanceAlreadyUp = true;
                return;
            }

            //wait 0.1 second
            usleep((1000 * 1000 / 10));

            $waitingTime = microtime(true) - $start;

            if ($waitingTime > $maxTime) {
                $msgFmt = 'Waited too long (%s s) for fluentd!';
                $msg = sprintf($msgFmt, $maxTime);
                throw new \Exception($msg);
            }
        }
    }

    public function ping() : bool
    {
        $domain = sprintf('tcp://%s:%s', $this->host, $this->port);

        $socket = @stream_socket_client($domain, $errno, $errstr, 0.1);

        if (is_resource($socket)) {
            fclose($socket);
            return true;
        }

        return false;
    }
}
