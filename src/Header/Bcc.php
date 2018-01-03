<?php

namespace Ions\Mail\Header;

/**
 * Class Bcc
 * @package Ions\Mail\Header
 */
class Bcc extends AbstractAddressList
{
    /**
     * @var string
     */
    protected $fieldName = 'Bcc';

    /**
     * @var string
     */
    protected static $type = 'bcc';
}
