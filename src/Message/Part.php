<?php

namespace Ions\Mail\Message;

/**
 * Class Part
 * @package Ions\Mail\Message
 */
class Part
{
    /**
     * @var string
     */
    public $type = Mime::TYPE_OCTETSTREAM;

    /**
     * @var string
     */
    public $encoding = Mime::ENCODING_8BIT;

    /**
     * @var
     */
    public $id;

    /**
     * @var
     */
    public $disposition;

    /**
     * @var string
     */
    public $filename;

    /**
     * @var
     */
    public $description;

    /**
     * @var
     */
    public $charset;

    /**
     * @var
     */
    public $boundary;

    /**
     * @var
     */
    public $location;

    /**
     * @var
     */
    public $language;

    /**
     * @var
     */
    protected $content;

    /**
     * @var bool
     */
    protected $isStream = false;

    /**
     * Part constructor.
     * @param string $content
     */
    public function __construct($content = '')
    {
        $this->setContent($content);
    }

    /**
     * @param string $type
     * @return $this
     */
    public function setType($type = Mime::TYPE_OCTETSTREAM)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $encoding
     * @return $this
     */
    public function setEncoding($encoding = Mime::ENCODING_8BIT)
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
     * @param $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param $disposition
     * @return $this
     */
    public function setDisposition($disposition)
    {
        $this->disposition = $disposition;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDisposition()
    {
        return $this->disposition;
    }

    /**
     * @param $description
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param $fileName
     * @return $this
     */
    public function setFileName($fileName)
    {
        $this->filename = $fileName;
        return $this;
    }

    /**
     * @return string
     */
    public function getFileName()
    {
        return $this->filename;
    }

    /**
     * @param $charset
     * @return $this
     */
    public function setCharset($charset)
    {
        $this->charset = $charset;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCharset()
    {
        return $this->charset;
    }

    /**
     * @param $boundary
     * @return $this
     */
    public function setBoundary($boundary)
    {
        $this->boundary = $boundary;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getBoundary()
    {
        return $this->boundary;
    }

    /**
     * @param $location
     * @return $this
     */
    public function setLocation($location)
    {
        $this->location = $location;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * @param $language
     * @return $this
     */
    public function setLanguage($language)
    {
        $this->language = $language;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * @param $content
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setContent($content)
    {
        if (!is_string($content) && !is_resource($content)) {
            throw new \InvalidArgumentException(sprintf(
                'Content must be string or resource; received "%s"',
               gettype($content)
            ));
        }
        $this->content = $content;
        if (is_resource($content)) {
            $this->isStream = true;
        }
        return $this;
    }

    /**
     * @param bool $isStream
     * @return $this
     */
    public function setIsStream($isStream = false)
    {
        $this->isStream = (bool)$isStream;
        return $this;
    }

    /**
     * @return bool
     */
    public function getIsStream()
    {
        return $this->isStream;
    }

    /**
     * @return bool
     */
    public function isStream()
    {
        return $this->isStream;
    }

    /**
     * @param string $EOL
     * @return mixed
     * @throws \RuntimeException
     */
    public function getEncodedStream($EOL = Mime::LINEEND)
    {
        if (!$this->isStream) {
            throw new \RuntimeException('Attempt to get a stream from a string part');
        }
        switch ($this->encoding) {
            case Mime::ENCODING_QUOTEDPRINTABLE:
                if (array_key_exists(Mime::ENCODING_QUOTEDPRINTABLE, $this->filters)) {
                    stream_filter_remove($this->filters[Mime::ENCODING_QUOTEDPRINTABLE]);
                }
                $filter = stream_filter_append($this->content, 'convert.quoted-printable-encode', STREAM_FILTER_READ, ['line-length' => 76, 'line-break-chars' => $EOL]);
                $this->filters[Mime::ENCODING_QUOTEDPRINTABLE] = $filter;
                if (!is_resource($filter)) {
                    throw new \RuntimeException('Failed to append quoted-printable filter');
                }
                break;
            case Mime::ENCODING_BASE64:
                if (array_key_exists(Mime::ENCODING_BASE64, $this->filters)) {
                    stream_filter_remove($this->filters[Mime::ENCODING_BASE64]);
                }
                $filter = stream_filter_append($this->content, 'convert.base64-encode', STREAM_FILTER_READ, ['line-length' => 76, 'line-break-chars' => $EOL]);
                $this->filters[Mime::ENCODING_BASE64] = $filter;
                if (!is_resource($filter)) {
                    throw new \RuntimeException('Failed to append base64 filter');
                }
                break;
            default:
        }
        return $this->content;
    }

    /**
     * @param string $EOL
     * @return bool|string
     */
    public function getContent($EOL = Mime::LINEEND)
    {
        if ($this->isStream) {
            $encodedStream = $this->getEncodedStream($EOL);
            $encodedStreamContents = stream_get_contents($encodedStream);
            $streamMetaData = stream_get_meta_data($encodedStream);
            if (isset($streamMetaData['seekable']) && $streamMetaData['seekable']) {
                rewind($encodedStream);
            }
            return $encodedStreamContents;
        }
        return Mime::encode($this->content, $this->encoding, $EOL);
    }

    /**
     * @return bool|string
     */
    public function getRawContent()
    {
        if ($this->isStream) {
            return stream_get_contents($this->content);
        }
        return $this->content;
    }

    /**
     * @param string $EOL
     * @return array
     */
    public function getHeadersArray($EOL = Mime::LINEEND)
    {
        $headers = [];
        $contentType = $this->type;
        if ($this->charset) {
            $contentType .= '; charset=' . $this->charset;
        }
        if ($this->boundary) {
            $contentType .= ';' . $EOL . " boundary=\"" . $this->boundary . '"';
        }
        $headers[] = ['Content-Type', $contentType];
        if ($this->encoding) {
            $headers[] = ['Content-Transfer-Encoding', $this->encoding];
        }
        if ($this->id) {
            $headers[] = ['Content-ID', '<' . $this->id . '>'];
        }
        if ($this->disposition) {
            $disposition = $this->disposition;
            if ($this->filename) {
                $disposition .= '; filename="' . $this->filename . '"';
            }
            $headers[] = ['Content-Disposition', $disposition];
        }
        if ($this->description) {
            $headers[] = ['Content-Description', $this->description];
        }
        if ($this->location) {
            $headers[] = ['Content-Location', $this->location];
        }
        if ($this->language) {
            $headers[] = ['Content-Language', $this->language];
        }
        return $headers;
    }

    /**
     * @param string $EOL
     * @return string
     */
    public function getHeaders($EOL = Mime::LINEEND)
    {
        $res = '';
        foreach ($this->getHeadersArray($EOL) as $header) {
            $res .= $header[0] . ': ' . $header[1] . $EOL;
        }
        return $res;
    }
}
