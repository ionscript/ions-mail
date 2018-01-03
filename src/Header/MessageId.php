<?php

namespace Ions\Mail\Header;

/**
 * Class MessageId
 * @package Ions\Mail\Header
 */
class MessageId implements HeaderInterface
{
    /**
     * @var
     */
    protected $messageId;

    /**
     * @param $headerLine
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function fromString($headerLine)
    {
        list($name, $value) = GenericHeader::splitHeaderLine($headerLine);

        $value = HeaderWrap::mimeDecodeValue($value);

        if (strtolower($name) !== 'message-id') {
            throw new \InvalidArgumentException('Invalid header line for Message-ID string');
        }

        $header = new static();

        $header->setId($value);

        return $header;
    }

    /**
     * @return string
     */
    public function getFieldName()
    {
        return 'Message-ID';
    }

    /**
     * @param bool $format
     * @return mixed
     */
    public function getFieldValue($format = HeaderInterface::FORMAT_RAW)
    {
        return $this->messageId;
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
        return 'Message-ID: ' . $this->getFieldValue();
    }

    /**
     * @param null $id
     * @return $this
     */
    public function setId($id = null)
    {
        if ($id === null) {
            $id = $this->createMessageId();
        }

        if (!HeaderValue::isValid($id) || preg_match("/[\r\n]/", $id)) {
            throw new \InvalidArgumentException('Invalid ID detected');
        }

        $this->messageId = sprintf('<%s>', $id);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->messageId;
    }

    /**
     * @return string
     */
    public function createMessageId()
    {
        $time = time();
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $user = $_SERVER['REMOTE_ADDR'];
        } else {
            $user = getmypid();
        }
        $rand = mt_rand();
        if (isset($_SERVER["SERVER_NAME"])) {
            $hostName = $_SERVER["SERVER_NAME"];
        } else {
            $hostName = php_uname('n');
        }
        return sha1($time . $user . $rand) . '@' . $hostName;
    }
}
