<?php

namespace Ions\Mail\Header;

use Ions\Mail\Headers;

/**
 * Class Received
 * @package Ions\Mail\Header
 */
class Received implements HeaderInterface, MultipleHeadersInterface
{
    /**
     * @var string
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

        if (strtolower($name) !== 'received') {
            throw new \InvalidArgumentException('Invalid header line for Received string');
        }

        $header = new static($value);
        return $header;
    }

    /**
     * Received constructor.
     * @param string $value
     * @throws \InvalidArgumentException
     */
    public function __construct($value = '')
    {
        if (!HeaderValue::isValid($value)) {
            throw new \InvalidArgumentException('Invalid Received value provided');
        }

        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getFieldName()
    {
        return 'Received';
    }

    /**
     * @param bool $format
     * @return string
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
        return 'Received: ' . $this->getFieldValue();
    }

    /**
     * @param array $headers
     * @return string
     * @throws \RuntimeException
     */
    public function toStringMultipleHeaders(array $headers)
    {
        $strings = [$this->toString()];

        foreach ($headers as $header) {
            if (!$header instanceof Received) {
                throw new \RuntimeException('The Received multiple header implementation can only accept an array of Received headers');
            }

            $strings[] = $header->toString();
        }

        return implode(Headers::EOL, $strings);
    }
}
