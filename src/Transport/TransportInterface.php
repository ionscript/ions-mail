<?php

namespace Ions\Mail\Transport;

use Ions\Mail;

interface TransportInterface
{
    public function send(Mail\Message $message);
}
