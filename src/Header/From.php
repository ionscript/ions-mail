<?php

namespace Ions\Mail\Header;

/**
 * Class From
 * @package Ions\Mail\Header
 */
class From extends AbstractAddressList
{
    /**
     * @var string
     */
    protected $fieldName = 'From';

    /**
     * @var string
     */
    protected static $type = 'from';
}
