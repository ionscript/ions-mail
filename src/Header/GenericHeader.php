<?php

namespace Ions\Mail\Header;

/**
 * Class GenericHeader
 * @package Ions\Mail\Header
 */
class GenericHeader implements HeaderInterface, UnstructuredInterface
{
    /**
     * @var string
     */
    protected $fieldName;
    /**
     * @var string
     */
    protected $fieldValue;
    /**
     * @var string
     */
    protected $encoding;

    /**
     * @param $headerLine
     * @return static
     */
    public static function fromString($headerLine)
    {
        list($name, $value) = self::splitHeaderLine($headerLine);

        $value = HeaderWrap::mimeDecodeValue($value);

        return new static($name, $value);
    }

    /**
     * @param $headerLine
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function splitHeaderLine($headerLine)
    {
        $parts = explode(':', $headerLine, 2);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Header must match with the format "name:value"');
        }
        if (!HeaderName::isValid($parts[0])) {
            throw new \InvalidArgumentException('Invalid header name detected');
        }
        if (!HeaderValue::isValid($parts[1])) {
            throw new \InvalidArgumentException('Invalid header value detected');
        }

        $parts[0] = rtrim($parts[0]);

        $parts[1] = ltrim($parts[1]);

        return $parts;
    }

    /**
     * GenericHeader constructor.
     * @param null $fieldName
     * @param null $fieldValue
     */
    public function __construct($fieldName = null, $fieldValue = null)
    {
        if ($fieldName) {
            $this->setName($fieldName);
        }
        if ($fieldValue !== null) {
            $this->setValue($fieldValue);
        }
    }

    /**
     * @param $fieldName
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setFieldName($fieldName)
    {
        if (!is_string($fieldName) || empty($fieldName)) {
            throw new \InvalidArgumentException('Header name must be a string');
        }
        $fieldName = str_replace(' ', '-', ucwords(str_replace(['_', '-'], ' ', $fieldName)));
        if (!HeaderName::isValid($fieldName)) {
            throw new \InvalidArgumentException('Header name must be composed of printable US-ASCII characters, except colon.');
        }
        $this->fieldName = $fieldName;
        return $this;
    }

    /**
     * @return null
     */
    public function getName()
    {
        return $this->fieldName;
    }

    /**
     * @param $fieldValue
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setFieldValue($fieldValue)
    {
        $fieldValue = (string)$fieldValue;
        if (!HeaderWrap::canBeEncoded($fieldValue)) {
            throw new \InvalidArgumentException('Header value must be composed of printable US-ASCII characters and valid folding sequences.');
        }
        $this->fieldValue = $fieldValue;
        $this->encoding = null;
        return $this;
    }

    /**
     * @param bool $format
     * @return null|string
     */
    public function getValue($format = HeaderInterface::FORMAT_RAW)
    {
        if (HeaderInterface::FORMAT_ENCODED === $format) {
            return HeaderWrap::wrap($this->fieldValue, $this);
        }
        return $this->fieldValue;
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
            $this->encoding = Mime::isPrintable($this->fieldValue) ? 'ASCII' : 'UTF-8';
        }
        return $this->encoding;
    }

    /**
     * @return string
     * @throws \RuntimeException
     */
    public function toString()
    {
        $name = $this->getName();
        if (empty($name)) {
            throw new \RuntimeException('Header name is not set, use setFieldName()');
        }
        $value = $this->getValue(HeaderInterface::FORMAT_ENCODED);
        return $name . ': ' . $value;
    }
}

