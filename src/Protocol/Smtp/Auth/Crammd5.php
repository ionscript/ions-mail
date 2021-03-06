<?php

namespace Ions\Mail\Protocol\Smtp\Auth;

use Ions\Mail\Protocol\Smtp;

/**
 * Class Crammd5
 * @package Ions\Mail\Protocol\Smtp\Auth
 */
class Crammd5 extends Smtp
{
    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;

    /**
     * Crammd5 constructor.
     * @param string $host
     * @param null $port
     * @param null $config
     */
    public function __construct($host = '127.0.0.1', $port = null, $config = null)
    {
        $origConfig = $config;
        if (is_array($host)) {
            if (is_array($config)) {
                $config = array_replace_recursive($host, $config);
            } else {
                $config = $host;
            }
        }
        if (is_array($config)) {
            if (isset($config['username'])) {
                $this->setUsername($config['username']);
            }
            if (isset($config['password'])) {
                $this->setPassword($config['password']);
            }
        }
        parent::__construct($host, $port, $origConfig);
    }

    /**
     * @return void
     */
    public function auth()
    {
        parent::auth();
        $this->_send('AUTH CRAM-MD5');
        $challenge = $this->_expect(334);
        $challenge = base64_decode($challenge);
        $digest = $this->hmacMd5($this->getPassword(), $challenge);
        $this->_send(base64_encode($this->getUsername() . ' ' . $digest));
        $this->_expect(235);
        $this->auth = true;
    }

    /**
     * @param $username
     * @return $this
     */
    public function setUsername($username)
    {
        $this->username = $username;
        return $this;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @param $password
     * @return $this
     */
    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param $key
     * @param $data
     * @return string
     */
    protected function hmacMd5($key, $data)
    {
        return hash_hmac('md5', $data, $key);
    }
}
