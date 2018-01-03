<?php

namespace Ions\Mail\Transport;

use Ions\Std\AbstractOptions;

class SmtpOptions extends AbstractOptions
{
    protected $name = 'localhost';
    protected $connectionClass = 'smtp';
    protected $connectionConfig = [];
    protected $host = '127.0.0.1';
    protected $port = 25;

    // TODO: ADD TIMEOUT FUNCIONALITY FOR SMTP PROTOCOL
    protected $timeout = 5;

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        if (!is_string($name) && $name !== null) {
            throw new \InvalidArgumentException(sprintf('Name must be a string or null; argument of type "%s" provided', (is_object($name) ? get_class($name) : gettype($name))));
        }
        $this->name = $name;
        return $this;
    }

    public function getConnectionClass()
    {
        return $this->connectionClass;
    }

    public function setConnectionClass($connectionClass)
    {
        if (!is_string($connectionClass) && $connectionClass !== null) {
            throw new \InvalidArgumentException(sprintf('Connection class must be a string or null; argument of type "%s" provided', (is_object($connectionClass) ? get_class($connectionClass) : gettype($connectionClass))));
        }
        $this->connectionClass = $connectionClass;
        return $this;
    }

    public function getConnectionConfig()
    {
        return $this->connectionConfig;
    }

    public function setConnectionConfig(array $connectionConfig)
    {
        $this->connectionConfig = $connectionConfig;
        return $this;
    }

    public function getHost()
    {
        return $this->host;
    }

    public function setHost($host)
    {
        $this->host = (string)$host;
        return $this;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function setPort($port)
    {
        $port = (int)$port;
        if ($port < 1) {
            throw new \InvalidArgumentException(sprintf('Port must be greater than 1; received "%d"', $port));
        }
        $this->port = $port;
        return $this;
    }

    public function getTimeout()
    {
        return $this->timeout;
    }

    public function setTimeout(array $timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }
}
