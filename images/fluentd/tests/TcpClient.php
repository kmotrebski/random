<?php declare(strict_types=1);

namespace KMOtrebski\Infratifacts\Images\Fluentd\Tests;

class TcpClient
{
    /**
     * @var resource $socket
     */
    private $socket;

    public function __construct(string $host, int $port)
    {
        $this->setUpSocket($host, $port);
    }

    private function setUpSocket(string $host, int $port)
    {
        $url = sprintf('tcp://%s:%s', $host, $port);
        $flags = \STREAM_CLIENT_CONNECT | \STREAM_CLIENT_PERSISTENT;
        $socket = stream_socket_client($url, $errno, $errstr, 0.5, $flags);

        if (false === is_resource($socket) || false === $socket) {
            $fmt = 'Error code=%s, msg=%s';
            $msg = sprintf($fmt, $errno, $errstr);
            throw new \RuntimeException($msg);
        }

        $this->socket = $socket;
    }

    public function stream(string $data)
    {
        $dataWithEnding = $data . "\n";
        $length = strlen($dataWithEnding);
        $result = fwrite($this->socket, $dataWithEnding, $length);

        if (self::isStreamingSuccess($result, $length)) {
            return;
        }

        $fmt = 'Streaming data failed, data: %s';
        $msg = sprintf($fmt, $dataWithEnding);
        throw new Exception($msg);
    }

    private static function isStreamingSuccess($result, int $length) : bool
    {
        if ($result !== $length) {
            return false;
        }

        if (false === is_int($result)) {
            return false;
        }

        if (false === $result) {
            return false;
        }

        return true;
    }

    public function close()
    {
        $result = fclose($this->socket);

        if (true === $result) {
            return;
        }

        $msg = 'Failed to close socket!';
        throw new \RuntimeException($msg);
    }
}
