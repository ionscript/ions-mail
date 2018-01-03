<?php

namespace Ions\Mail\Header;

use Ions\Mail\Headers;
use Ions\Mail\Message\Mime;

/**
 * Class HeaderWrap
 * @package Ions\Mail\Header
 */
abstract class HeaderWrap
{
    /**
     * @param $value
     * @param HeaderInterface $header
     * @return string
     */
    public static function wrap($value, HeaderInterface $header)
    {
        if ($header instanceof UnstructuredInterface) {
            return static::wrapUnstructuredHeader($value, $header);
        } elseif ($header instanceof StructuredInterface) {
            return static::wrapStructuredHeader($value, $header);
        }
        return $value;
    }

    /**
     * @param $value
     * @param HeaderInterface $header
     * @return string
     */
    protected static function wrapUnstructuredHeader($value, HeaderInterface $header)
    {
        $encoding = $header->getEncoding();
        if ($encoding === 'ASCII') {
            return wordwrap($value, 78, Headers::FOLDING);
        }
        return static::mimeEncodeValue($value, $encoding, 78);
    }

    /**
     * @param $value
     * @param StructuredInterface $header
     * @return string
     */
    protected static function wrapStructuredHeader($value, StructuredInterface $header)
    {
        $delimiter = $header->getDelimiter();
        $length = strlen($value);
        $lines = [];
        $temp = '';
        for ($i = 0; $i < $length; $i++) {
            $temp .= $value[$i];
            if ($value[$i] == $delimiter) {
                $lines[] = $temp;
                $temp = '';
            }
        }
        return implode(Headers::FOLDING, $lines);
    }

    /**
     * @param $value
     * @param $encoding
     * @param int $lineLength
     * @return mixed
     */
    public static function mimeEncodeValue($value, $encoding, $lineLength = 998)
    {
        return Mime::encodeQuotedPrintableHeader($value, $encoding, $lineLength, Headers::EOL);
    }

    /**
     * @param $value
     * @return string
     */
    public static function mimeDecodeValue($value)
    {
        $decodedValue = iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        return $decodedValue;
    }

    /**
     * @param $value
     * @return bool
     */
    public static function canBeEncoded($value)
    {
        $charset = 'UTF-8';
        $lineLength = strlen($value) * 4 + strlen($charset) + 16;
        $preferences = ['scheme' => 'Q', 'input-charset' => $charset, 'output-charset' => $charset, 'line-length' => $lineLength,];
        $encoded = iconv_mime_encode('x-test', $value, $preferences);
        return (false !== $encoded);
    }
}
