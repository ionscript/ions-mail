<?php

namespace Ions\Mail;

/**
 * Class Address
 * @package Ions\Mail
 */
class Address implements AddressInterface
{
    /**
     * @var string
     */
    protected $email;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $localPart;

    /**
     * @var string
     */
    protected $hostname;

    /**
     * Address constructor.
     * @param $email
     * @param null $name
     * @throws \InvalidArgumentException
     */
    public function __construct($email, $name = null)
    {
        if (!is_string($email) || empty($email)) {
            throw new \InvalidArgumentException('Email must be a valid email address');
        }

        if (preg_match("/[\r\n]/", $email)) {
            throw new \InvalidArgumentException('CRLF injection detected');
        }

        if (!$this->isValid($email)) {
            throw new \InvalidArgumentException('Email must be a valid email address');
        }

        if (null !== $name) {
            if (!is_string($name)) {
                throw new \InvalidArgumentException('Name must be a string');
            }
            if (preg_match("/[\r\n]/", $name)) {
                throw new \InvalidArgumentException('CRLF injection detected');
            }
            $this->name = $name;
        }

        $this->email = $email;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function toString()
    {
        $string = '<' . $this->getEmail() . '>';
        $name = $this->getName();
        if (null === $name) {
            return $string;
        }
        return $name . ' ' . $string;
    }

    // Validator

    /**
     * @param $host
     * @return bool
     */
    protected function isReserved($host)
    {
        if (!preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', $host)) {
            $host = gethostbynamel($host);
        } else {
            $host = [$host];
        }

        if (empty($host)) {
            return false;
        }

        foreach ($host as $server) {
            // Search for 0.0.0.0/8, 10.0.0.0/8, 127.0.0.0/8
            if (!preg_match('/^(0|10|127)(\.([0-9]|[1-9][0-9]|1([0-9][0-9])|2([0-4][0-9]|5[0-5]))){3}$/', $server) &&
                // Search for 100.64.0.0/10
                !preg_match('/^100\.(6[0-4]|[7-9][0-9]|1[0-1][0-9]|12[0-7])(\.([0-9]|[1-9][0-9]|1([0-9][0-9])|2([0-4][0-9]|5[0-5]))){2}$/', $server) &&
                // Search for 172.16.0.0/12
                !preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])(\.([0-9]|[1-9][0-9]|1([0-9][0-9])|2([0-4][0-9]|5[0-5]))){2}$/', $server) &&
                // Search for 198.18.0.0/15
                !preg_match('/^198\.(1[8-9])(\.([0-9]|[1-9][0-9]|1([0-9][0-9])|2([0-4][0-9]|5[0-5]))){2}$/', $server) &&
                // Search for 169.254.0.0/16, 192.168.0.0/16
                !preg_match('/^(169\.254|192\.168)(\.([0-9]|[1-9][0-9]|1([0-9][0-9])|2([0-4][0-9]|5[0-5]))){2}$/', $server) &&
                // Search for 192.0.2.0/24, 192.88.99.0/24, 198.51.100.0/24, 203.0.113.0/24
                !preg_match('/^(192\.0\.2|192\.88\.99|198\.51\.100|203\.0\.113)\.([0-9]|[1-9][0-9]|1([0-9][0-9])|2([0-4][0-9]|5[0-5]))$/', $server) &&
                // Search for 224.0.0.0/4, 240.0.0.0/4
                !preg_match('/^(2(2[4-9]|[3-4][0-9]|5[0-5]))(\.([0-9]|[1-9][0-9]|1([0-9][0-9])|2([0-4][0-9]|5[0-5]))){3}$/', $server)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return bool
     */
    protected function validateLocalPart()
    {
        $atext = 'a-zA-Z0-9\x21\x23\x24\x25\x26\x27\x2a\x2b\x2d\x2f\x3d\x3f\x5e\x5f\x60\x7b\x7c\x7d\x7e';

        if (preg_match('/^[' . $atext . ']+(\x2e+[' . $atext . ']+)*$/', $this->idnToAscii($this->localPart))) {
            $result = true;
        } else {
            $qtext      = '\x20-\x21\x23-\x5b\x5d-\x7e';
            $quotedPair = '\x20-\x7e';
            if (preg_match('/^"(['. $qtext .']|\x5c[' . $quotedPair . '])*"$/', $this->localPart)) {
                $result = true;
            } else {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * @return array|bool
     */
    protected function validateMXRecords()
    {
        $mxHosts = [];
        $weight  = [];
        $result = getmxrr($this->idnToAscii($this->hostname), $mxHosts, $weight);
        if (!empty($mxHosts) && !empty($weight)) {
            $mxRecord = array_combine($mxHosts, $weight) ?: [];
        } else {
            $mxRecord = [];
        }

        arsort($mxRecord);

        if (!$result) {
            $result = gethostbynamel($this->hostname);
            if (is_array($result)) {
                $mxRecord = array_flip($result);
            }
        }

        if (!$result) {
            return $result;
        }

        $validAddress = false;
        foreach ($mxRecord as $hostname => $weight) {
            $res = $this->isReserved($hostname);

            if (!$res
                && (checkdnsrr($hostname, "A")
                    || checkdnsrr($hostname, "AAAA")
                    || checkdnsrr($hostname, "A6"))
            ) {
                $validAddress = true;
                break;
            }
        }

        if (!$validAddress) {
            $result = false;
        }

        return $result;
    }

    /**
     * @param $value
     * @return bool
     */
    protected function splitEmailParts($value)
    {
        $value = is_string($value) ? $value : '';

        if (strpos($value, '..') !== false
            || ! preg_match('/^(.+)@([^@]+)$/', $value, $matches)
        ) {
            return false;
        }

        $this->localPart = $matches[1];
        $this->hostname  = $matches[2];

        return true;
    }

    /**
     * @param $value
     * @return bool
     */
    public function isValid($value)
    {
        if (!is_string($value)) {
            return false;
        }

        $length  = true;
        $value = $this->idnToUtf8($value);

        if (!$this->splitEmailParts($value)) {
            return false;
        }

        if (strlen($this->localPart) > 64 || strlen($this->hostname) > 255) {
            $length = false;
        }

        $local = $this->validateLocalPart();

        return ($local && $length);
    }

    /**
     * @param $email
     * @return mixed
     */
    protected function idnToAscii($email)
    {
        if (extension_loaded('intl')) {
            return (idn_to_ascii($email) ?: $email);
        }
        return $email;
    }

    /**
     * @param $email
     * @return mixed
     */
    protected function idnToUtf8($email)
    {
        if (extension_loaded('intl')) {
            return idn_to_utf8($email) ?: $email;
        }
        return $email;
    }
}
