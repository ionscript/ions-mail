<?php

namespace Ions\Mail\Header;

use Ions\Mail\Headers;

/**
 * Class ContentType
 * @package Ions\Mail\Header
 */
class ContentType implements UnstructuredInterface
{
    /**
     * @var
     */
    protected $type;
    /**
     * @var string
     */
    protected $encoding = 'ASCII';
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

        if (strtolower($name) !== 'content-type') {
            throw new \InvalidArgumentException('Invalid header line for Content-Type string');
        }

        $value = str_replace(Headers::FOLDING, ' ', $value);
        $values = preg_split('#\s*;\s*#', $value);
        $type = array_shift($values);
        $header = new static();
        $header->setType($type);
        $values = array_filter($values);

        foreach ($values as $keyValuePair) {
            list($key, $value) = explode('=', $keyValuePair, 2);
            $value = trim($value, "'\" \t\n\r\0\x0B");
            $header->addParameter($key, $value);
        }

        return $header;
    }

    /**
     * @return string
     */
    public function getFieldName()
    {
        return 'Content-Type';
    }

    /**
     * @param bool $format
     * @return string
     */
    public function getFieldValue($format = HeaderInterface::FORMAT_RAW)
    {
        $prepared = $this->type;

        if (empty($this->parameters)) {
            return $prepared;
        }

        $values = [$prepared];

        foreach ($this->parameters as $attribute => $value) {
            if (HeaderInterface::FORMAT_ENCODED === $format && !Mime::isPrintable($value)) {
                $this->encoding = 'UTF-8';
                $value = HeaderWrap::wrap($value, $this);
                $this->encoding = 'ASCII';
            }
            $values[] = sprintf('%s="%s"', $attribute, $value);
        }

        return implode(';' . Headers::FOLDING, $values);
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
     * @return string
     */
    public function toString()
    {
        return 'Content-Type: ' . $this->getFieldValue(HeaderInterface::FORMAT_ENCODED);
    }

    /**
     * @param $type
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setType($type)
    {
        if (!preg_match('/^[a-z-]+\/[a-z0-9.+-]+$/i', $type)) {
            throw new \InvalidArgumentException(sprintf('%s expects a value in the format "type/subtype"; received "%s"', __METHOD__, (string)$type));
        }

        $this->type = $type;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param $name
     * @param $value
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addParameter($name, $value)
    {
        $name = strtolower($name);
        $value = (string)$value;

        if (!HeaderValue::isValid($name)) {
            throw new \InvalidArgumentException('Invalid content-type parameter name detected');
        }

        if (!HeaderWrap::canBeEncoded($value)) {
            throw new \InvalidArgumentException('Parameter value must be composed of printable US-ASCII or UTF-8 characters.');
        }

        $this->parameters[$name] = $value;

        return $this;
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @param $name
     * @return mixed|null
     */
    public function getParameter($name)
    {
        $name = strtolower($name);

        if (isset($this->parameters[$name])) {
            return $this->parameters[$name];
        }

        return null;
    }

    /**
     * @param $name
     * @return bool
     */
    public function removeParameter($name)
    {
        $name = strtolower($name);

        if (isset($this->parameters[$name])) {
            unset($this->parameters[$name]);
            return true;
        }

        return false;
    }
}
