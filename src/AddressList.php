<?php

namespace Ions\Mail;

use Countable;
use Iterator;

/**
 * Class AddressList
 * @package Ions\Mail
 */
class AddressList implements Countable, Iterator
{
    /**
     * @var array
     */
    protected $addresses = [];

    /**
     * @param $emailOrAddress
     * @param null $name
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function add($emailOrAddress, $name = null)
    {
        if (is_string($emailOrAddress)) {
            $emailOrAddress = $this->createAddress($emailOrAddress, $name);
        } elseif (!$emailOrAddress instanceof AddressInterface) {
            throw new \InvalidArgumentException(sprintf('%s expects an email address or %s\Address object as its first argument; received "%s"', __METHOD__, __NAMESPACE__, (is_object($emailOrAddress) ? get_class($emailOrAddress) : gettype($emailOrAddress))));
        }
        $email = strtolower($emailOrAddress->getEmail());
        if ($this->has($email)) {
            return $this;
        }
        $this->addresses[$email] = $emailOrAddress;
        return $this;
    }

    /**
     * @param array $addresses
     * @return $this
     * @throws \RuntimeException
     */
    public function addMany(array $addresses)
    {
        foreach ($addresses as $key => $value) {
            if (is_int($key) || is_numeric($key)) {
                $this->add($value);
            } elseif (is_string($key)) {
                $this->add($key, $value);
            } else {
                throw new \RuntimeException(sprintf('Invalid key type in provided addresses array ("%s")', (is_object($key) ? get_class($key) : var_export($key, 1))));
            }
        }
        return $this;
    }

    /**
     * @param $address
     * @return AddressList
     * @throws \InvalidArgumentException
     */
    public function addFromString($address)
    {
        if (!preg_match('/^((?P<name>.*?)<(?P<namedEmail>[^>]+)>|(?P<email>.+))$/', $address, $matches)) {
            throw new \InvalidArgumentException('Invalid address format');
        }

        $name = null;
        if (isset($matches['name'])) {
            $name = trim($matches['name']);
        }

        if (empty($name)) {
            $name = null;
        }

        if (isset($matches['namedEmail'])) {
            $email = $matches['namedEmail'];
        }

        if (isset($matches['email'])) {
            $email = $matches['email'];
        }

        $email = trim($email);
        return $this->add($email, $name);
    }

    /**
     * @param AddressList $addressList
     * @return $this
     */
    public function merge(AddressList $addressList)
    {
        foreach ($addressList as $address) {
            $this->add($address);
        }

        return $this;
    }

    /**
     * @param $email
     * @return bool
     */
    public function has($email)
    {
        $email = strtolower($email);
        return isset($this->addresses[$email]);
    }

    /**
     * @param $email
     * @return bool|mixed
     */
    public function get($email)
    {
        $email = strtolower($email);
        if (!isset($this->addresses[$email])) {
            return false;
        }

        return $this->addresses[$email];
    }

    /**
     * @param $email
     * @return bool
     */
    public function delete($email)
    {
        $email = strtolower($email);
        if (!isset($this->addresses[$email])) {
            return false;
        }

        unset($this->addresses[$email]);

        return true;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->addresses);
    }

    /**
     * @return mixed
     */
    public function rewind()
    {
        return reset($this->addresses);
    }

    /**
     * @return mixed
     */
    public function current()
    {
        return current($this->addresses);
    }

    /**
     * @return int|null|string
     */
    public function key()
    {
        return key($this->addresses);
    }

    /**
     * @return mixed
     */
    public function next()
    {
        return next($this->addresses);
    }

    /**
     * @return bool
     */
    public function valid()
    {
        $key = key($this->addresses);
        return ($key !== null && $key !== false);
    }

    /**
     * @param $email
     * @param $name
     * @return Address
     */
    protected function createAddress($email, $name)
    {
        return new Address($email, $name);
    }
}
