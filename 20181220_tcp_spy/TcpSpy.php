<?php declare(strict_types=1);

namespace Library\Tests\Logging;

class TcpSpy
{
    /**
     * @var string $host
     */
    protected $host;

    /**
     * @var string $port
     */
    protected $port;

    /**
     * @var resource $socket
     */
    protected $socket;

    /**
     * @var string|null $entry
     */
    protected $entry;

    public function __construct(string $host, int $port)
    {
        $this->host = $host;
        $this->port = $port;
        $this->entry = null;
    }

    public function start() : self
    {
        $this->entry = null;

        $url = sprintf('tcp://%s:%s', $this->host, $this->port);
        $this->socket = stream_socket_server($url, $errno, $errstr);
        stream_set_timeout($this->socket, 0, 10);
        return $this;
    }

    public function readLogRecord() : string
    {
        if (null === $this->entry) {
            $this->entry = $this->readLogEntryFromStream();
        }

        return $this->entry;
    }

    protected function readLogEntryFromStream() : string
    {
        $connection = stream_socket_accept($this->socket, 0.1);
        $data = fgets($connection);
        fclose($connection);
        return $data;
    }

    public function close() : self
    {
        $this->entry = null;
        fclose($this->socket);
        return $this;
    }
}
