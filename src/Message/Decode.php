<?php

namespace Ions\Mail\Message;

use Ions\Mail\Headers;

/**
 * Class Decode
 * @package Ions\Mail\Message
 */
class Decode
{
    /**
     * @param $body
     * @param $boundary
     * @return array
     * @throws \RuntimeException
     */
    public static function splitMime($body, $boundary)
    {
        $body = str_replace("\r", '', $body);
        $start = 0;
        $res = [];
        $p = strpos($body, '--' . $boundary . "\n", $start);
        if ($p === false) {
            return [];
        }
        $start = $p + 3 + strlen($boundary);
        while (($p = strpos($body, '--' . $boundary . "\n", $start)) !== false) {
            $res[] = substr($body, $start, $p - $start);
            $start = $p + 3 + strlen($boundary);
        }
        $p = strpos($body, '--' . $boundary . '--', $start);
        if ($p === false) {
            throw new \RuntimeException('Not a valid Mime Message: End Missing');
        }
        $res[] = substr($body, $start, $p - $start);
        return $res;
    }

    /**
     * @param $message
     * @param $boundary
     * @param string $EOL
     * @return array|void
     */
    public static function splitMessageStruct($message, $boundary, $EOL = Mime::LINEEND)
    {
        $parts = static::splitMime($message, $boundary);
        if (count($parts) <= 0) {
            return;
        }
        $result = [];
        $headers = null;
        $body = null;
        foreach ($parts as $part) {
            static::splitMessage($part, $headers, $body, $EOL);
            $result[] = ['header' => $headers, 'body' => $body];
        }
        return $result;
    }

    /**
     * @param $message
     * @param $headers
     * @param $body
     * @param string $EOL
     * @param bool $strict
     */
    public static function splitMessage($message, &$headers, &$body, $EOL = Mime::LINEEND, $strict = false)
    {
        if ($message instanceof Headers) {
            $message = $message->toString();
        }

        $firstlinePos = strpos($message, "\n");
        $firstline = $firstlinePos === false ? $message : substr($message, 0, $firstlinePos);
        if (!preg_match('%^[^\s]+[^:]*:%', $firstline)) {
            $headers = [];
            $body = str_replace(["\r", "\n"], ['', $EOL], $message);
            return;
        }

        if (!$strict) {
            $parts = explode(':', $firstline, 2);
            if (count($parts) != 2) {
                $message = substr($message, strpos($message, $EOL) + 1);
            }
        }

        if (strpos($message, $EOL . $EOL)) {
            list($headers, $body) = explode($EOL . $EOL, $message, 2);
        } elseif ($EOL !== "\r\n" && strpos($message, "\r\n\r\n")) {
            list($headers, $body) = explode("\r\n\r\n", $message, 2);
        } elseif ($EOL !== "\n" && strpos($message, "\n\n")) {
            list($headers, $body) = explode("\n\n", $message, 2);
        } else {
            list($headers, $body) = preg_split("%([\r\n]+)\\1%U", $message, 2);
        }

        $headers = Headers::fromString($headers, $EOL);
    }

    /**
     * @param $type
     * @param null $wantedPart
     * @return array|bool|null|string
     */
    public static function splitContentType($type, $wantedPart = null)
    {
        return static::splitHeaderField($type, $wantedPart, 'type');
    }

    /**
     * @param $field
     * @param null $wantedPart
     * @param string $firstName
     * @return array|bool|null|string
     */
    public static function splitHeaderField($field, $wantedPart = null, $firstName = '0')
    {
        $wantedPart = strtolower($wantedPart);
        $firstName = strtolower($firstName);

        if ($firstName === $wantedPart) {
            $field = strtok($field, ';');
            return $field[0] === '"' ? substr($field, 1, -1) : $field;
        }

        $field = $firstName . '=' . $field;

        if (!preg_match_all('%([^=\s]+)\s*=\s*("[^"]+"|[^;]+)(;\s*|$)%', $field, $matches)) {
            throw new \RuntimeException('not a valid header field');
        }

        if ($wantedPart) {
            foreach ($matches[1] as $key => $name) {
                if (strcasecmp($name, $wantedPart)) {
                    continue;
                }
                if ($matches[2][$key][0] !== '"') {
                    return $matches[2][$key];
                }
                return substr($matches[2][$key], 1, -1);
            }

            return null;
        }

        $split = [];
        foreach ($matches[1] as $key => $name) {
            $name = strtolower($name);
            if ($matches[2][$key][0] === '"') {
                $split[$name] = substr($matches[2][$key], 1, -1);
            } else {
                $split[$name] = $matches[2][$key];
            }
        }
        return $split;
    }

    /**
     * @param $string
     * @return string
     */
    public static function decodeQuotedPrintable($string)
    {
        return iconv_mime_decode($string, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
    }
}
