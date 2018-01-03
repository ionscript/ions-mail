<?php

namespace Ions\Mail\Transport;

class Null extends InMemory
{
    public function __construct()
    {
        trigger_error(sprintf('The class %s has been deprecated; please use %s\\InMemory',
            __CLASS__,
            __NAMESPACE__
        ), E_USER_DEPRECATED);
    }
}
