<?php

namespace Ions\Mail\Transport;

use Ions\Std\AbstractOptions;

class FileOptions extends AbstractOptions
{
    protected $path;
    protected $callback;

    public function setPath($path)
    {
        if (!is_dir($path) || !is_writable($path)) {
            throw new \InvalidArgumentException(sprintf(
                '%s expects a valid path in which to write mail files; received "%s"',
                __METHOD__,
                (string)$path
            ));
        }

        $this->path = $path;
        return $this;
    }

    public function getPath()
    {
        if (null === $this->path) {
            $this->setPath(sys_get_temp_dir());
        }
        return $this->path;
    }

    public function setCallback($callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException(sprintf(
                '%s expects a valid callback; received "%s"',
                __METHOD__,
                gettype($callback)
            ));
        }

        $this->callback = $callback;

        return $this;
    }

    public function getCallback()
    {
        if (null === $this->callback) {
            $this->setCallback(function () {
                return 'IonsMail_' . time() . '_' . mt_rand() . '.eml';
            });
        }
        return $this->callback;
    }
}
