<?php

namespace Ions\Mail\Header;

use Ions\Mail;

/**
 * Class Sender
 * @package Ions\Mail\Header
 */
class Sender implements HeaderInterface
{
    /**
     * @var
     */
    protected $address;

    /**
     * @var
     */
    protected $encoding;

    /**
     * @param $headerLine
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function fromString($headerLine)
    {
        list($name, $value) = GenericHeader::splitHeaderLine($headerLine);
        $value = HeaderWrap::mimeDecodeValue($value);
        if (strtolower($name) !== 'sender') {
            throw new \InvalidArgumentException('Invalid header name for Sender string');
        }
        $header = new static();
        $hasMatches = preg_match('/^(?:(?P<name>.+)\s)?(?(name)<|<?)(?P<email>[^\s]+?)(?(name)>|>?)$/', $value, $matches);
        if ($hasMatches !== 1) {
            throw new \InvalidArgumentException('Invalid header value for Sender string');
        }
        $senderName = trim($matches['name']);
        if (empty($senderName)) {
            $senderName = null;
        }
        $header->setAddress($matches['email'], $senderName);
        return $header;
    }

    /**
     * @return string
     */
    public function getFieldName()
    {
        return 'Sender';
    }

    /**
     * @param bool $format
     * @return string
     */
    public function getFieldValue($format = HeaderInterface::FORMAT_RAW)
    {
        if (!$this->address instanceof Mail\AddressInterface) {
            return '';
        }

        $email = sprintf('<%s>', $this->address->getEmail());
        $name = $this->address->getName();

        if (!empty($name)) {
            if ($format == HeaderInterface::FORMAT_ENCODED) {
                $encoding = $this->getEncoding();
                if ('ASCII' !== $encoding) {
                    $name = HeaderWrap::mimeEncodeValue($name, $encoding);
                }
            }

            $email = sprintf('%s %s', $name, $email);
        }

        return $email;
    }

    /**
     * @param $encoding
     * @return $this
     */
    public function setEncoding($encoding)
    {
        $this->encoding = $encoding;
        return $this;
    }

    /**
     * @return string
     */
    public function getEncoding()
    {
        if (!$this->encoding) {
            $this->encoding = Mail\Message\Mime::isPrintable($this->getFieldValue(HeaderInterface::FORMAT_RAW)) ? 'ASCII' : 'UTF-8';
        }

        return $this->encoding;
    }

    /**
     * @return string
     */
    public function toString()
    {
        return 'Sender: ' . $this->getFieldValue(HeaderInterface::FORMAT_ENCODED);
    }

    /**
     * @param $emailOrAddress
     * @param null $name
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setAddress($emailOrAddress, $name = null)
    {
        if (is_string($emailOrAddress)) {
            $emailOrAddress = new Mail\Address($emailOrAddress, $name);
        } elseif (!$emailOrAddress instanceof Mail\AddressInterface) {
            throw new \InvalidArgumentException(sprintf('%s expects a string or AddressInterface object; received "%s"', __METHOD__, (is_object($emailOrAddress) ? get_class($emailOrAddress) : gettype($emailOrAddress))));
        }

        $this->address = $emailOrAddress;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAddress()
    {
        return $this->address;
    }
}
