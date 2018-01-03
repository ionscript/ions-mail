<?php

namespace Ions\Mail\Header;

/**
 * Class To
 * @package Ions\Mail\Header
 */
class To extends AbstractAddressList
{
    /**
     * @var string
     */
    protected $fieldName = 'To';

    /**
     * @var string
     */
    protected static $type = 'to';
}
