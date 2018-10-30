<?php

namespace Ions\Mail;

use ArrayIterator;
use Countable;
use Iterator;
use Traversable;

/**
 * Class Headers
 * @package Ions\Mail
 */
class Headers implements Countable, Iterator
{
    const EOL = "\r\n";
    const FOLDING = "\r\n ";

    /**
     * @var array
     */
    protected $headersKeys = [];

    /**
     * @var array
     */
    protected $headers = [];

    /**
     * @var string
     */
    protected $encoding = 'ASCII';

    /**
     * @param $string
     * @param string $EOL
     * @return static
     * @throws \RuntimeException
     */
    public static function fromString($string, $EOL = self::EOL)
    {
        $headers = new static();
        $currentLine = '';
        $emptyLine = 0;
        $lines = explode($EOL, $string);
        $total = count($lines);
        for ($i = 0; $i < $total; $i += 1) {
            $line = $lines[$i];
            if (preg_match('/^\s*$/', $line)) {
                $emptyLine += 1;
                if ($emptyLine > 2) {
                    throw new \RuntimeException('Malformed header detected');
                }
                continue;
            }
            if ($emptyLine > 0) {
                throw new \RuntimeException('Malformed header detected');
            }
            if (preg_match('/^[\x21-\x39\x3B-\x7E]+:.*$/', $line)) {
                if ($currentLine) {
                    $headers->addHeaderLine($currentLine);
                }
                $currentLine = trim($line);
                continue;
            }
            if (preg_match('/^\s+.*$/', $line)) {
                $currentLine .= ' ' . trim($line);
                continue;
            }
            throw new \RuntimeException(sprintf('Line "%s" does not match header format!', $line));
        }
        if ($currentLine) {
            $headers->addHeaderLine($currentLine);
        }
        return $headers;
    }

    /**
     * @param $encoding
     * @return $this
     */
    public function setEncoding($encoding)
    {
        $this->encoding = $encoding;
        foreach ($this as $header) {
            $header->setEncoding($encoding);
        }
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
     * @param $headers
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addHeaders($headers)
    {
        if (!is_array($headers) && !$headers instanceof Traversable) {
            throw new \InvalidArgumentException(sprintf('Expected array or Traversable; received "%s"', (is_object($headers) ? get_class($headers) : gettype($headers))));
        }
        foreach ($headers as $name => $value) {
            if (is_int($name)) {
                if (is_string($value)) {
                    $this->addHeaderLine($value);
                } elseif (is_array($value) && count($value) == 1) {
                    $this->addHeaderLine(key($value), current($value));
                } elseif (is_array($value) && count($value) == 2) {
                    $this->addHeaderLine($value[0], $value[1]);
                } elseif ($value instanceof Header\HeaderInterface) {
                    $this->addHeader($value);
                }
            } elseif (is_string($name)) {
                $this->addHeaderLine($name, $value);
            }
        }
        return $this;
    }

    /**
     * @param $headerFieldNameOrLine
     * @param null $fieldValue
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addHeaderLine($headerFieldNameOrLine, $fieldValue = null)
    {
        if (!is_string($headerFieldNameOrLine)) {
            throw new \InvalidArgumentException(sprintf('%s expects its first argument to be a string; received "%s"', __METHOD__, (is_object($headerFieldNameOrLine) ? get_class($headerFieldNameOrLine) : gettype($headerFieldNameOrLine))));
        }
        if ($fieldValue === null) {
            $headers = $this->loadHeader($headerFieldNameOrLine);
            $headers = is_array($headers) ? $headers : [$headers];
            foreach ($headers as $header) {
                $this->addHeader($header);
            }
        } elseif (is_array($fieldValue)) {
            foreach ($fieldValue as $i) {
                $this->addHeader(Header\GenericMultiHeader::fromString($headerFieldNameOrLine . ':' . $i));
            }
        } else {
            $this->addHeader(Header\GenericHeader::fromString($headerFieldNameOrLine . ':' . $fieldValue));
        }
        return $this;
    }

    /**
     * @param Header\HeaderInterface $header
     * @return $this
     */
    public function addHeader(Header\HeaderInterface $header)
    {
        $key = $this->normalizeFieldName($header->getName());
        $this->headersKeys[] = $key;
        $this->headers[] = $header;
        if ($this->getEncoding() !== 'ASCII') {
            $header->setEncoding($this->getEncoding());
        }
        return $this;
    }

    /**
     * @param $instanceOrFieldName
     * @return bool
     */
    public function removeHeader($instanceOrFieldName)
    {
        if ($instanceOrFieldName instanceof Header\HeaderInterface) {
            $indexes = array_keys($this->headers, $instanceOrFieldName, true);
        } else {
            $key = $this->normalizeFieldName($instanceOrFieldName);
            $indexes = array_keys($this->headersKeys, $key, true);
        }
        if (!empty($indexes)) {
            foreach ($indexes as $index) {
                unset($this->headersKeys[$index]);
                unset($this->headers[$index]);
            }
            return true;
        }
        return false;
    }

    /**
     * @return $this
     */
    public function clearHeaders()
    {
        $this->headers = $this->headersKeys = [];
        return $this;
    }

    /**
     * @param $name
     * @return ArrayIterator|bool|mixed
     */
    public function get($name)
    {
        $key = $this->normalizeFieldName($name);
        $results = [];
        foreach (array_keys($this->headersKeys, $key) as $index) {
            if ($this->headers[$index] instanceof Header\GenericHeader) {
                $results[] = $this->lazyLoadHeader($index);
            } else {
                $results[] = $this->headers[$index];
            }
        }
        switch (count($results)) {
            case 0:
                return false;
            case 1:
                if ($results[0] instanceof Header\MultipleHeadersInterface) {
                    return new ArrayIterator($results);
                } else {
                    return $results[0];
                }
            default:
                return new ArrayIterator($results);
        }
    }

    /**
     * @param $name
     * @return bool
     */
    public function has($name)
    {
        $name = $this->normalizeFieldName($name);
        return in_array($name, $this->headersKeys, true);
    }

    /**
     * @return void
     */
    public function next()
    {
        next($this->headers);
    }

    /**
     * @return int|null|string
     */
    public function key()
    {
        return key($this->headers);
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return (current($this->headers) !== false);
    }

    /**
     * @return void
     */
    public function rewind()
    {
        reset($this->headers);
    }

    /**
     * @return mixed
     */
    public function current()
    {
        $current = current($this->headers);
        if ($current instanceof Header\GenericHeader) {
            $current = $this->lazyLoadHeader(key($this->headers));
        }
        return $current;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->headers);
    }

    /**
     * @return string
     */
    public function toString()
    {
        $headers = '';
        foreach ($this as $header) {
            if ($str = $header->toString()) {
                $headers .= $str . self::EOL;
            }
        }
        return $headers;
    }

    /**
     * @param bool $format
     * @return array
     */
    public function toArray($format = Header\HeaderInterface::FORMAT_RAW)
    {
        $headers = [];
        foreach ($this->headers as $header) {
            if ($header instanceof Header\MultipleHeadersInterface) {
                $name = $header->getName();
                if (!isset($headers[$name])) {
                    $headers[$name] = [];
                }
                $headers[$name][] = $header->getValue($format);
            } else {
                $headers[$header->getName()] = $header->getValue($format);
            }
        }
        return $headers;
    }

    /**
     * @param $headerLine
     * @return mixed
     */
    public function loadHeader($headerLine)
    {
        list($name) = Header\GenericHeader::splitHeaderLine($headerLine);
        $class = $name ?: Header\GenericHeader::class;
        return $class::fromString($headerLine);
    }

    /**
     * @param $index
     * @return mixed
     */
    protected function lazyLoadHeader($index)
    {
        $current = $this->headers[$index];
        $key = $this->headersKeys[$index];

        $class = $key ?: Header\GenericHeader::class;

        $encoding = $current->getEncoding();
        $headers = $class::fromString($current->toString());

        if (is_array($headers)) {
            $current = array_shift($headers);
            $current->setEncoding($encoding);
            $this->headers[$index] = $current;

            foreach ($headers as $header) {
                $header->setEncoding($encoding);
                $this->headersKeys[] = $key;
                $this->headers[] = $header;
            }

            return $current;
        }

        $current = $headers;
        $current->setEncoding($encoding);
        $this->headers[$index] = $current;
        return $current;
    }

    /**
     * @param $fieldName
     * @return mixed
     */
    protected function normalizeFieldName($fieldName)
    {
        return str_replace(['-', '_', ' ', '.'], '', strtolower($fieldName));
    }
}
