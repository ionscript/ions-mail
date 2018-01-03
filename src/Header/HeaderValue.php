<?php

namespace Ions\Mail\Header;

/**
 * Class HeaderValue
 * @package Ions\Mail\Header
 */
final class HeaderValue
{
    /**
     * HeaderValue constructor.
     */
    private function __construct()
    {
    }

    /**
     * @param $value
     * @return string
     */
    public static function filter($value)
    {
        $result = '';
        $total = strlen($value);
        for ($i = 0; $i < $total; $i += 1) {
            $ord = ord($value[$i]);
            if ($ord === 10 || $ord > 127) {
                continue;
            }
            if ($ord === 13) {
                if ($i + 2 >= $total) {
                    continue;
                }
                $lf = ord($value[$i + 1]);
                $sp = ord($value[$i + 2]);
                if ($lf !== 10 || $sp !== 32) {
                    continue;
                }
                $result .= "\r\n ";
                $i += 2;
                continue;
            }
            $result .= $value[$i];
        }
        return $result;
    }

    /**
     * @param $value
     * @return bool
     */
    public static function isValid($value)
    {
        $total = strlen($value);
        for ($i = 0; $i < $total; $i += 1) {
            $ord = ord($value[$i]);
            if ($ord === 10 || $ord > 127) {
                return false;
            }
            if ($ord === 13) {
                if ($i + 2 >= $total) {
                    return false;
                }
                $lf = ord($value[$i + 1]);
                $sp = ord($value[$i + 2]);
                if ($lf !== 10 || !in_array($sp, [9, 32], true)) {
                    return false;
                }
                $i += 2;
            }
        }
        return true;
    }

    /**
     * @param $value
     * @throws \RuntimeException
     */
    public static function assertValid($value)
    {
        if (!self::isValid($value)) {
            throw new \RuntimeException('Invalid header value detected');
        }
    }
}
