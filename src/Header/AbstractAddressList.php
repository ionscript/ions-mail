<?php

namespace Ions\Mail\Header;

use Ions\Mail\AddressList;
use Ions\Mail\Headers;

/**
 * Class AbstractAddressList
 * @package Ions\Mail\Header
 */
abstract class AbstractAddressList implements HeaderInterface
{
    /**
     * @var
     */
    protected $addressList;

    /**
     * @var
     */
    protected $fieldName;

    /**
     * @var string
     */
    protected $encoding = 'ASCII';

    /**
     * @var
     */
    protected static $type;

    /**
     * @param $headerLine
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function fromString($headerLine)
    {
        list($fieldName, $fieldValue) = GenericHeader::splitHeaderLine($headerLine);

        if (strtolower($fieldName) !== static::$type) {
            throw new \InvalidArgumentException(sprintf('Invalid header line for "%s" string', __CLASS__));
        }

        $fieldValue = str_replace(Headers::FOLDING, ' ', $fieldValue);

        $fieldValue = preg_replace('/[^:]+:([^;]*);/', '$1,', $fieldValue);
        $values = str_getcsv($fieldValue, ',');

        $wasEncoded = false;
        array_walk($values, function (&$value) use (&$wasEncoded) {
            $decodedValue = HeaderWrap::mimeDecodeValue($value);
            $wasEncoded = $wasEncoded || ($decodedValue !== $value);
            $value = trim($decodedValue);
            $value = self::stripComments($value);
            $value = preg_replace(['#(?<!\\\)"(.*)(?<!\\\)"#', '#\\\([\x01-\x09\x0b\x0c\x0e-\x7f])#'], ['\\1', '\\1'], $value);
        });

        $header = new static();

        if ($wasEncoded) {
            $header->setEncoding('UTF-8');
        }

        $values = array_filter($values);

        $addressList = $header->getAddressList();

        foreach ($values as $address) {
            $addressList->addFromString($address);
        }

        return $header;
    }

    /**
     * @return mixed
     */
    public function getFieldName()
    {
        return $this->fieldName;
    }

    /**
     * @param $domainName
     * @return mixed
     */
    protected function idnToAscii($domainName)
    {
        if (extension_loaded('intl')) {
            return (idn_to_ascii($domainName) ?: $domainName);
        }
        return $domainName;
    }

    /**
     * @param bool $format
     * @return string
     */
    public function getFieldValue($format = HeaderInterface::FORMAT_RAW)
    {
        $emails = [];
        $encoding = $this->getEncoding();

        foreach ($this->getAddressList() as $address) {
            $email = $address->getEmail();
            $name = $address->getName();
            if (!empty($name) && false !== strstr($name, ',')) {
                $name = sprintf('"%s"', $name);
            }
            if ($format === HeaderInterface::FORMAT_ENCODED && 'ASCII' !== $encoding) {
                if (!empty($name)) {
                    $name = HeaderWrap::mimeEncodeValue($name, $encoding);
                }
                if (preg_match('/^(.+)@([^@]+)$/', $email, $matches)) {
                    $localPart = $matches[1];
                    $hostname = $this->idnToAscii($matches[2]);
                    $email = sprintf('%s@%s', $localPart, $hostname);
                }
            }
            if (empty($name)) {
                $emails[] = $email;
            } else {
                $emails[] = sprintf('%s <%s>', $name, $email);
            }
        }

        if ($format !== HeaderInterface::FORMAT_RAW) {
            foreach ($emails as $email) {
                HeaderValue::assertValid($email);
            }
        }

        return implode(',' . Headers::FOLDING, $emails);
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
        return $this->encoding;
    }

    /**
     * @param AddressList $addressList
     */
    public function setAddressList(AddressList $addressList)
    {
        $this->addressList = $addressList;
    }

    /**
     * @return mixed
     */
    public function getAddressList()
    {
        if (null === $this->addressList) {
            $this->setAddressList(new AddressList());
        }
        return $this->addressList;
    }

    /**
     * @return string
     */
    public function toString()
    {
        $name = $this->getFieldName();
        $value = $this->getFieldValue(HeaderInterface::FORMAT_ENCODED);
        return (empty($value)) ? '' : sprintf('%s: %s', $name, $value);
    }

    /**
     * @param $value
     * @return mixed
     */
    protected static function stripComments($value)
    {
        return preg_replace('/\\(
                (
                    \\\\.|
                    [^\\\\)]
                )+
            \\)/x', '', $value);
    }
}
