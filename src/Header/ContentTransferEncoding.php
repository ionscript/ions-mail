<?php

namespace Ions\Mail\Header;

/**
 * Class ContentTransferEncoding
 * @package Ions\Mail\Header
 */
class ContentTransferEncoding implements HeaderInterface
{
    /**
     * @var array
     */
    protected static $allowedTransferEncodings = [
        '7bit',
        '8bit',
        'quoted-printable',
        'base64',
        'binary'
    ];

    /**
     * @var
     */
    protected $transferEncoding;

    /**
     * @var array
     */
    protected $parameters = [];

    /**
     * @param $headerLine
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function fromString($headerLine)
    {
        list($name, $value) = GenericHeader::splitHeaderLine($headerLine);

        $value = HeaderWrap::mimeDecodeValue($value);

        if (strtolower($name) !== 'content-transfer-encoding') {
            throw new \InvalidArgumentException('Invalid header line for Content-Transfer-Encoding string');
        }

        $header = new static();

        $header->setTransferEncoding($value);

        return $header;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Content-Transfer-Encoding';
    }

    /**
     * @param bool $format
     * @return mixed
     */
    public function getValue($format = HeaderInterface::FORMAT_RAW)
    {
        return $this->transferEncoding;
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
        return 'Content-Transfer-Encoding: ' . $this->getValue();
    }

    /**
     * @param $transferEncoding
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setTransferEncoding($transferEncoding)
    {
        $transferEncoding = strtolower($transferEncoding);
        if (!in_array($transferEncoding, static::$allowedTransferEncodings, true)) {
            throw new \InvalidArgumentException(sprintf('%s expects one of "' . implode(', ', static::$allowedTransferEncodings) . '"; received "%s"', __METHOD__, (string)$transferEncoding));
        }

        $this->transferEncoding = $transferEncoding;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTransferEncoding()
    {
        return $this->transferEncoding;
    }
}
