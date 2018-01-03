<?php

namespace Ions\Mail\Header;

/**
 * Class HeaderName
 * @package Ions\Mail\Header
 */
final class HeaderName
{
    /**
     * HeaderName constructor.
     */
    private function __construct()
    {
    }

    /**
     * @param $name
     * @return string
     */
    public static function filter($name)
    {
        $result = '';
        $tot = strlen($name);
        for ($i = 0; $i < $tot; $i += 1) {
            $ord = ord($name[$i]);
            if ($ord > 32 && $ord < 127 && $ord !== 58) {
                $result .= $name[$i];
            }
        }
        return $result;
    }

    /**
     * @param $name
     * @return bool
     */
    public static function isValid($name)
    {
        $tot = strlen($name);
        for ($i = 0; $i < $tot; $i += 1) {
            $ord = ord($name[$i]);
            if ($ord < 33 || $ord > 126 || $ord === 58) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param $name
     * @throws \RuntimeException
     */
    public static function assertValid($name)
    {
        if (!self::isValid($name)) {
            throw new \RuntimeException('Invalid header name detected');
        }
    }
}
