<?php

namespace Ions\Mail\Transport;

use Ions\Mail\Message;

class File implements TransportInterface
{
    protected $options;
    protected $lastFile;

    public function __construct(FileOptions $options = null)
    {
        if (!$options instanceof FileOptions) {
            $options = new FileOptions();
        }
        $this->setOptions($options);
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function setOptions(FileOptions $options)
    {
        $this->options = $options;
    }

    public function send(Message $message)
    {
        $options = $this->options;
        $filename = call_user_func($options->getCallback(), $this);
        $file = $options->getPath() . DIRECTORY_SEPARATOR . $filename;
        $email = $message->toString();
        if (false === file_put_contents($file, $email)) {
            throw new \RuntimeException(sprintf('Unable to write mail to file (directory "%s")', $options->getPath()));
        }
        $this->lastFile = $file;
    }

    public function getLastFile()
    {
        return $this->lastFile;
    }
}
