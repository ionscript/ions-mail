<?php

namespace Ions\Mail\Protocol;

/**
 * Class Smtp
 * @package Ions\Mail\Protocol
 */
class Smtp extends AbstractProtocol
{
    /**
     * @var string
     */
    protected $transport = 'tcp';

    /**
     * @var string
     */
    protected $secure;

    /**
     * @var bool
     */
    protected $sess = false;

    /**
     * @var bool
     */
    protected $auth = false;

    /**
     * @var bool
     */
    protected $mail = false;

    /**
     * @var bool
     */
    protected $rcpt = false;

    /**
     * @var
     */
    protected $data;

    /**
     * @var bool
     */
    protected $useCompleteQuit = true;

    /**
     * Smtp constructor.
     * @param string $host
     * @param null $port
     * @param array|null $config
     * @throws \InvalidArgumentException
     */
    public function __construct($host = '127.0.0.1', $port = null, array $config = null)
    {
        if (is_array($host)) {
            if (is_array($config)) {
                $config = array_replace_recursive($host, $config);
            } else {
                $config = $host;
            }
            if (isset($config['host'])) {
                $host = $config['host'];
            } else {
                $host = '127.0.0.1';
            }
            if (isset($config['port'])) {
                $port = $config['port'];
            } else {
                $port = null;
            }
        }
        if (null === $config) {
            $config = [];
        }
        if (isset($config['ssl'])) {
            switch (strtolower($config['ssl'])) {
                case 'tls':
                    $this->secure = 'tls';
                    break;
                case 'ssl':
                    $this->transport = 'ssl';
                    $this->secure = 'ssl';
                    if ($port === null) {
                        $port = 465;
                    }
                    break;
                default:
                    throw new \InvalidArgumentException($config['ssl'] . ' is unsupported SSL type');
            }
        }
        if (array_key_exists('use_complete_quit', $config)) {
            $this->setUseCompleteQuit($config['use_complete_quit']);
        }
        if ($port === null) {
            if (($port = ini_get('smtp_port')) == '') {
                $port = 25;
            }
        }
        parent::__construct($host, $port);
    }

    /**
     * @param $useCompleteQuit
     * @return bool
     */
    public function setUseCompleteQuit($useCompleteQuit)
    {
        return $this->useCompleteQuit = (bool)$useCompleteQuit;
    }

    /**
     * @return bool
     */
    public function useCompleteQuit()
    {
        return $this->useCompleteQuit;
    }

    /**
     * @return bool
     */
    public function connect()
    {
        return $this->_connect($this->transport . '://' . $this->host . ':' . $this->port);
    }

    /**
     * @param string $host
     * @throws \RuntimeException
     */
    public function helo($host = '127.0.0.1')
    {
        if ($this->sess === true) {
            throw new \RuntimeException('Cannot issue HELO to existing session');
        }
        if (!$this->validHost->isValid($host)) {
            throw new \RuntimeException(implode(', ', $this->validHost->getMessages()));
        }
        $this->_expect(220, 300);
        $this->ehlo($host);
        if ($this->secure === 'tls') {
            $this->_send('STARTTLS');
            $this->_expect(220, 180);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('Unable to connect via TLS');
            }
            $this->ehlo($host);
        }
        $this->startSession();
        $this->auth();
    }

    /**
     * @return bool
     */
    public function hasSession()
    {
        return $this->sess;
    }

    /**
     * @param $host
     * @throws \Exception
     */
    protected function ehlo($host)
    {
        try {
            $this->_send('EHLO ' . $host);
            $this->_expect(250, 300);
        } catch (\Exception $e) {
            $this->_send('HELO ' . $host);
            $this->_expect(250, 300);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @param $from
     * @throws \RuntimeException
     */
    public function mail($from)
    {
        if ($this->sess !== true) {
            throw new \RuntimeException('A valid session has not been started');
        }
        $this->_send('MAIL FROM:<' . $from . '>');
        $this->_expect(250, 300);
        $this->mail = true;
        $this->rcpt = false;
        $this->data = false;
    }

    /**
     * @param $to
     * @throws \RuntimeException
     */
    public function rcpt($to)
    {
        if ($this->mail !== true) {
            throw new \RuntimeException('No sender reverse path has been supplied');
        }
        $this->_send('RCPT TO:<' . $to . '>');
        $this->_expect([250, 251], 300);
        $this->rcpt = true;
    }

    /**
     * @param $data
     * @throws \RuntimeException
     */
    public function data($data)
    {
        if ($this->rcpt !== true) {
            throw new \RuntimeException('No recipient forward path has been supplied');
        }
        $this->_send('DATA');
        $this->_expect(354, 120);
        if (($fp = fopen('php://temp', 'rb+')) === false) {
            throw new \RuntimeException('cannot fopen');
        }
        if (fwrite($fp, $data) === false) {
            throw new \RuntimeException('cannot fwrite');
        }
        unset($data);
        rewind($fp);
        while (($line = stream_get_line($fp, 1000, "\n")) !== false) {
            $line = rtrim($line, "\r");
            if (isset($line[0]) && $line[0] === '.') {
                $line = '.' . $line;
            }
            $this->_send($line);
        }
        fclose($fp);
        $this->_send('.');
        $this->_expect(250, 600);
        $this->data = true;
    }

    /**
     * @return void
     */
    public function rset()
    {
        $this->_send('RSET');
        $this->_expect([250, 220]);
        $this->mail = false;
        $this->rcpt = false;
        $this->data = false;
    }

    /**
     * @return void
     */
    public function noop()
    {
        $this->_send('NOOP');
        $this->_expect(250, 300);
    }

    /**
     * @param $user
     */
    public function vrfy($user)
    {
        $this->_send('VRFY ' . $user);
        $this->_expect([250, 251, 252], 300);
    }

    /**
     * @return void
     */
    public function quit()
    {
        if ($this->sess) {
            $this->auth = false;
            if ($this->useCompleteQuit()) {
                $this->_send('QUIT');
                $this->_expect(221, 300);
            }
            $this->stopSession();
        }
    }

    /**
     * @return void
     * @throws \RuntimeException
     */
    public function auth()
    {
        if ($this->auth === true) {
            throw new \RuntimeException('Already authenticated for this session');
        }
    }

    /**
     * @return void
     */
    public function disconnect()
    {
        $this->_disconnect();
    }

    /**
     * @return void
     */
    protected function _disconnect()
    {
        $this->quit();
        parent::_disconnect();
    }

    /**
     * @return void
     */
    protected function startSession()
    {
        $this->sess = true;
    }

    /**
     * @return void
     */
    protected function stopSession()
    {
        $this->sess = false;
    }
}
