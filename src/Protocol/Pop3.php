<?php

namespace Ions\Mail\Protocol;

/**
 * Class Pop3
 * @package Ions\Mail\Protocol
 */
class Pop3
{
    const TIMEOUT_CONNECTION = 30;

    /**
     * @var
     */
    public $hasTop;

    /**
     * @var
     */
    protected $socket;

    /**
     * @var
     */
    protected $timestamp;

    /**
     * Pop3 constructor.
     * @param string $host
     * @param null $port
     * @param bool $ssl
     */
    public function __construct($host = '', $port = null, $ssl = false)
    {
        if ($host) {
            $this->connect($host, $port, $ssl);
        }
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        $this->logout();
    }

    /**
     * @param $host
     * @param null $port
     * @param bool $ssl
     * @return string
     * @throws \RuntimeException
     */
    public function connect($host, $port = null, $ssl = false)
    {
        $isTls = false;
        if ($ssl) {
            $ssl = strtolower($ssl);
        }
        switch ($ssl) {
            case 'ssl':
                $host = 'ssl://' . $host;
                if (!$port) {
                    $port = 995;
                }
                break;
            case 'tls':
                $isTls = true;
            default:
                if (!$port) {
                    $port = 110;
                }
        }

        $this->socket = fsockopen($host, $port, $errno, $errstr, self::TIMEOUT_CONNECTION);

        if (!$this->socket) {
            throw new \RuntimeException('cannot connect to host');
        }

        $welcome = $this->readResponse();
        strtok($welcome, '<');
        $this->timestamp = strtok('>');
        if (!strpos($this->timestamp, '@')) {
            $this->timestamp = null;
        } else {
            $this->timestamp = '<' . $this->timestamp . '>';
        }
        if ($isTls) {
            $this->request('STLS');
            $result = stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$result) {
                throw new \RuntimeException('cannot enable TLS');
            }
        }
        return $welcome;
    }

    /**
     * @param $request
     * @throws \RuntimeException
     */
    public function sendRequest($request)
    {
        $result = fwrite($this->socket, $request . "\r\n");

        if (!$result) {
            throw new \RuntimeException('send failed - connection closed?');
        }
    }

    /**
     * @param bool $multiline
     * @return string
     * @throws \RuntimeException
     */
    public function readResponse($multiline = false)
    {
        $result = fgets($this->socket);

        if (!is_string($result)) {
            throw new \RuntimeException('read failed - connection closed?');
        }
        $result = trim($result);
        if (strpos($result, ' ')) {
            list($status, $message) = explode(' ', $result, 2);
        } else {
            $status = $result;
            $message = '';
        }
        if ($status !== '+OK') {
            throw new \RuntimeException('last request failed');
        }
        if ($multiline) {
            $message = '';
            $line = fgets($this->socket);
            while ($line && rtrim($line, "\r\n") !== '.') {
                if ($line[0] === '.') {
                    $line = substr($line, 1);
                }
                $message .= $line;
                $line = fgets($this->socket);
            };
        }
        return $message;
    }

    /**
     * @param $request
     * @param bool $multiline
     * @return string
     */
    public function request($request, $multiline = false)
    {
        $this->sendRequest($request);
        return $this->readResponse($multiline);
    }

    /**
     * @return void
     */
    public function logout()
    {
        if ($this->socket) {
            try {
                $this->request('QUIT');
            } catch (\Exception $e) {
            }
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * @return array
     */
    public function capa()
    {
        $result = $this->request('CAPA', true);
        return explode("\n", $result);
    }

    /**
     * @param $user
     * @param $password
     * @param bool $tryApop
     */
    public function login($user, $password, $tryApop = true)
    {
        if ($tryApop && $this->timestamp) {
            try {
                $this->request("APOP $user " . md5($this->timestamp . $password));
                return;
            } catch (\Exception $e) {
            }
        }
        $this->request("USER $user");
        $this->request("PASS $password");
    }

    /**
     * @param $messages
     * @param $octets
     */
    public function status(&$messages, &$octets)
    {
        $messages = 0;
        $octets = 0;
        $result = $this->request('STAT');
        list($messages, $octets) = explode(' ', $result);
    }

    /**
     * @param null $msgno
     * @return array|int
     */
    public function getList($msgno = null)
    {
        if ($msgno !== null) {
            $result = $this->request("LIST $msgno");
            list(, $result) = explode(' ', $result);
            return (int)$result;
        }
        $result = $this->request('LIST', true);
        $messages = [];
        $line = strtok($result, "\n");
        while ($line) {
            list($no, $size) = explode(' ', trim($line));
            $messages[(int)$no] = (int)$size;
            $line = strtok("\n");
        }
        return $messages;
    }

    /**
     * @param null $msgno
     * @return array|string
     */
    public function uniqueid($msgno = null)
    {
        if ($msgno !== null) {
            $result = $this->request("UIDL $msgno");
            list(, $result) = explode(' ', $result);
            return $result;
        }
        $result = $this->request('UIDL', true);
        $result = explode("\n", $result);
        $messages = [];
        foreach ($result as $line) {
            if (!$line) {
                continue;
            }
            list($no, $id) = explode(' ', trim($line), 2);
            $messages[(int)$no] = $id;
        }
        return $messages;
    }

    /**
     * @param $msgno
     * @param int $lines
     * @param bool $fallback
     * @return string
     * @throws \RuntimeException|\Exception
     */
    public function top($msgno, $lines = 0, $fallback = false)
    {
        if ($this->hasTop === false) {
            if ($fallback) {
                return $this->retrieve($msgno);
            } else {
                throw new \RuntimeException('top not supported and no fallback wanted');
            }
        }
        $this->hasTop = true;
        $lines = (!$lines || $lines < 1) ? 0 : (int)$lines;
        try {
            $result = $this->request("TOP $msgno $lines", true);
        } catch (\Exception $e) {
            $this->hasTop = false;
            if ($fallback) {
                $result = $this->retrieve($msgno);
            } else {
                throw $e;
            }
        }
        return $result;
    }

    /**
     * @param $msgno
     * @return string
     */
    public function retrieve($msgno)
    {
        return $this->request("RETR $msgno", true);
    }

    /**
     * @return void
     */
    public function noop()
    {
        $this->request('NOOP');
    }

    /**
     * @param $msgno
     */
    public function delete($msgno)
    {
        $this->request("DELE $msgno");
    }

    /**
     * @return void
     */
    public function undelete()
    {
        $this->request('RSET');
    }
}
