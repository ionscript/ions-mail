<?php

namespace Ions\Mail\Transport;

use Ions\Mail\Message;

class InMemory implements TransportInterface
{
    protected $lastMessage;

    public function send(Message $message)
    {
        $this->lastMessage = $message;
    }

    public function getLastMessage()
    {
        return $this->lastMessage;
    }
}
