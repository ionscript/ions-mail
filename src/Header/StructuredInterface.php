<?php

namespace Ions\Mail\Header;

/**
 * Interface StructuredInterface
 * @package Ions\Mail\Header
 */
interface StructuredInterface extends HeaderInterface
{
    /**
     * @return mixed
     */
    public function getDelimiter();
}
