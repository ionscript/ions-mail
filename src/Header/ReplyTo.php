<?php

namespace Ions\Mail\Header;

/**
 * Class ReplyTo
 * @package Ions\Mail\Header
 */
class ReplyTo extends AbstractAddressList
{
    /**
     * @var string
     */
    protected $fieldName = 'Reply-To';

    /**
     * @var string
     */
    protected static $type = 'reply-to';
}
