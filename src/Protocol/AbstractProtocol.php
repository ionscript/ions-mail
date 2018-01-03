<?php

namespace Ions\Mail\Protocol;

use Ions\Uri\Http as HttpUri;

/**
 * Class AbstractProtocol
 * @package Ions\Mail\Protocol
 */
abstract class AbstractProtocol
{
    const EOL = "\r\n";
    const TIMEOUT_CONNECTION = 30;

    /**
     * @var int
     */
    protected $maximumLog = 64;

    /**
     * @var string
     */
    protected $host;

    /**
     * @var int
     */
    protected $port;

    /**
     * @var
     */
    protected $validHost;

    /**
     * @var
     */
    protected $socket;

    /**
     * @var
     */
    protected $request;

    /**
     * @var
     */
    protected $response;

    /**
     * @var array
     */
    private $log = [];

    /**
     * AbstractProtocol constructor.
     * @param string $host
     * @param null $port
     * @throws \RuntimeException
     */
    public function __construct($host = '127.0.0.1', $port = null)
    {
        if (! HttpUri::validateHost($host)) {
            throw new \RuntimeException(implode(', ', $this->validHost->getMessages()));
        }

        $this->host = $host;
        $this->port = $port;
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        $this->_disconnect();
    }

    /**
     * @param $maximumLog
     */
    public function setMaximumLog($maximumLog)
    {
        $this->maximumLog = (int)$maximumLog;
    }

    /**
     * @return int
     */
    public function getMaximumLog()
    {
        return $this->maximumLog;
    }

    /**
     * @return mixed
     */
    abstract public function connect();

    /**
     * @return mixed
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return mixed
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return string
     */
    public function getLog()
    {
        return implode('', $this->log);
    }

    /**
     * @return void
     */
    public function resetLog()
    {
        $this->log = [];
    }

    /**
     * @param $value
     */
    protected function _addLog($value)
    {
        if ($this->maximumLog >= 0 && count($this->log) >= $this->maximumLog) {
            array_shift($this->log);
        }
        $this->log[] = $value;
    }

    /**
     * @param $remote
     * @return bool
     * @throws \RuntimeException
     */
    protected function _connect($remote)
    {
        $errorNum = 0;
        $errorStr = '';
        set_error_handler(function ($error, $message = '') {
            throw new \RuntimeException(sprintf('Could not open socket: %s', $message), $error);
        }, E_WARNING);
        $this->socket = stream_socket_client($remote, $errorNum, $errorStr, self::TIMEOUT_CONNECTION);
        restore_error_handler();
        if ($this->socket === false) {
            if ($errorNum === 0) {
                $errorStr = 'Could not open socket';
            }
            throw new \RuntimeException($errorStr);
        }
        if (($result = stream_set_timeout($this->socket, self::TIMEOUT_CONNECTION)) === false) {
            throw new \RuntimeException('Could not set stream timeout');
        }
        return $result;
    }

    /**
     * @return void
     */
    protected function _disconnect()
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
    }

    /**
     * @param $request
     * @return bool|int
     * @throws \RuntimeException
     */
    protected function _send($request)
    {
        if (!is_resource($this->socket)) {
            throw new \RuntimeException('No connection has been established to ' . $this->host);
        }
        $this->request = $request;
        $result = fwrite($this->socket, $request . self::EOL);
        $this->_addLog($request . self::EOL);
        if ($result === false) {
            throw new \RuntimeException('Could not send request to ' . $this->host);
        }
        return $result;
    }

    /**
     * @param null $timeout
     * @return bool|string
     * @throws \RuntimeException
     */
    protected function _receive($timeout = null)
    {
        if (!is_resource($this->socket)) {
            throw new \RuntimeException('No connection has been established to ' . $this->host);
        }
        if ($timeout !== null) {
            stream_set_timeout($this->socket, $timeout);
        }
        $response = fgets($this->socket, 1024);
        $this->_addLog($response);
        $info = stream_get_meta_data($this->socket);
        if (!empty($info['timed_out'])) {
            throw new \RuntimeException($this->host . ' has timed out');
        }
        if ($response === false) {
            throw new \RuntimeException('Could not read from ' . $this->host);
        }
        return $response;
    }

    /**
     * @param $code
     * @param null $timeout
     * @return mixed
     * @throws \RuntimeException
     */
    protected function _expect($code, $timeout = null)
    {
        $this->response = [];
        $errMsg = '';
        if (!is_array($code)) {
            $code = [$code];
        }
        do {
            $this->response[] = $result = $this->_receive($timeout);
            list($cmd, $more, $msg) = preg_split('/([\s-]+)/', $result, 2, PREG_SPLIT_DELIM_CAPTURE);
            if ($errMsg !== '') {
                $errMsg .= ' ' . $msg;
            } elseif ($cmd === null || !in_array($cmd, $code)) {
                $errMsg = $msg;
            }
        } while (strpos($more, '-') === 0);
        if ($errMsg !== '') {
            throw new \RuntimeException($errMsg);
        }
        return $msg;
    }
}
