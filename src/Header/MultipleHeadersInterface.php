<?php

namespace Ions\Mail\Header;

/**
 * Interface MultipleHeadersInterface
 * @package Ions\Mail\Header
 */
interface MultipleHeadersInterface extends HeaderInterface
{
    /**
     * @param array $headers
     * @return mixed
     */
    public function toStringMultipleHeaders(array $headers);
}
