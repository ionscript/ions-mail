<?php

namespace Ions\Mail\Header;

/**
 * Class Date
 * @package Ions\Mail\Header
 */
class Date implements HeaderInterface
{
    /**
     * @var
     */
    protected $value;

    /**
     * @param $headerLine
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function fromString($headerLine)
    {
        list($name, $value) = GenericHeader::splitHeaderLine($headerLine);

        $value = HeaderWrap::mimeDecodeValue($value);

        if (strtolower($name) !== 'date') {
            throw new \InvalidArgumentException('Invalid header line for Date string');
        }

        return new static($value);
    }

    /**
     * Date constructor.
     * @param $value
     * @throws \InvalidArgumentException
     */
    public function __construct($value)
    {
        if (!HeaderValue::isValid($value)) {
            throw new \InvalidArgumentException('Invalid Date header value detected');
        }
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getFieldName()
    {
        return 'Date';
    }

    /**
     * @param bool $format
     * @return mixed
     */
    public function getFieldValue($format = HeaderInterface::FORMAT_RAW)
    {
        return $this->value;
    }

    /**
     * @param $encoding
     * @return $this
     */
    public function setEncoding($encoding)
    {
        return $this;
    }

    /**
     * @return string
     */
    public function getEncoding()
    {
        return 'ASCII';
    }

    /**
     * @return string
     */
    public function toString()
    {
        return 'Date: ' . $this->getFieldValue();
    }
}
