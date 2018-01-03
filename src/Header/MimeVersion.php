<?php

namespace Ions\Mail\Header;

/**
 * Class MimeVersion
 * @package Ions\Mail\Header
 */
class MimeVersion implements HeaderInterface
{
    /**
     * @var string
     */
    protected $version = '1.0';

    /**
     * @param $headerLine
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function fromString($headerLine)
    {
        list($name, $value) = GenericHeader::splitHeaderLine($headerLine);
        $value = HeaderWrap::mimeDecodeValue($value);
        if (strtolower($name) !== 'mime-version') {
            throw new \InvalidArgumentException('Invalid header line for MIME-Version string');
        }
        $header = new static();
        if (preg_match('/^(?P<version>\d+\.\d+)$/', $value, $matches)) {
            $header->setVersion($matches['version']);
        }
        return $header;
    }

    /**
     * @return string
     */
    public function getFieldName()
    {
        return 'MIME-Version';
    }

    /**
     * @param bool $format
     * @return string
     */
    public function getFieldValue($format = HeaderInterface::FORMAT_RAW)
    {
        return $this->version;
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
        return 'MIME-Version: ' . $this->getFieldValue();
    }

    /**
     * @param $version
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setVersion($version)
    {
        if (!preg_match('/^[1-9]\d*\.\d+$/', $version)) {
            throw new \InvalidArgumentException('Invalid MIME-Version value detected');
        }
        $this->version = $version;
        return $this;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }
}
