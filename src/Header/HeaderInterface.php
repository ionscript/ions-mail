<?php

namespace Ions\Mail\Header;

/**
 * Interface HeaderInterface
 * @package Ions\Mail\Header
 */
interface HeaderInterface
{
    const FORMAT_ENCODED = true;
    const FORMAT_RAW = false;

    /**
     * @param $headerLine
     * @return mixed
     */
    public static function fromString($headerLine);

    /**
     * @return mixed
     */
    public function getName();

    /**
     * @param bool $format
     * @return mixed
     */
    public function getValue($format = HeaderInterface::FORMAT_RAW);

    /**
     * @param $encoding
     * @return mixed
     */
    public function setEncoding($encoding);

    /**
     * @return mixed
     */
    public function getEncoding();

    /**
     * @return mixed
     */
    public function toString();
}

