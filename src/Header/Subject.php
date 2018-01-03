<?php

namespace Ions\Mail\Header;

use Ions\Mail\Message\Mime;

/**
 * Class Subject
 * @package Ions\Mail\Header
 */
class Subject implements UnstructuredInterface
{
    /**
     * @var string
     */
    protected $subject = '';
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
        if (strtolower($name) !== 'subject') {
            throw new \InvalidArgumentException('Invalid header line for Subject string');
        }
        $header = new static();
        $header->setSubject($value);
        return $header;
    }

    /**
     * @return string
     */
    public function getFieldName()
    {
        return 'Subject';
    }

    /**
     * @param bool $format
     * @return string
     */
    public function getFieldValue($format = HeaderInterface::FORMAT_RAW)
    {
        if (HeaderInterface::FORMAT_ENCODED === $format) {
            return HeaderWrap::wrap($this->subject, $this);
        }
        return $this->subject;
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
            $this->encoding = Mime::isPrintable($this->subject) ? 'ASCII' : 'UTF-8';
        }
        return $this->encoding;
    }

    /**
     * @param $subject
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setSubject($subject)
    {
        $subject = (string)$subject;
        if (!HeaderWrap::canBeEncoded($subject)) {
            throw new \InvalidArgumentException('Subject value must be composed of printable US-ASCII or UTF-8 characters.');
        }
        $this->subject = $subject;
        $this->encoding = null;
        return $this;
    }

    /**
     * @return string
     */
    public function toString()
    {
        return 'Subject: ' . $this->getFieldValue(HeaderInterface::FORMAT_ENCODED);
    }
}
