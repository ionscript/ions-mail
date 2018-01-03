<?php

namespace Ions\Mail\Transport;

use Ions\Std\AbstractOptions;

class Envelope extends AbstractOptions
{
    protected $from;
    protected $to;

    public function getFrom()
    {
        return $this->from;
    }

    public function setFrom($from)
    {
        $this->from = (string)$from;
    }

    public function getTo()
    {
        return $this->to;
    }

    public function setTo($to)
    {
        $this->to = $to;
    }
}
