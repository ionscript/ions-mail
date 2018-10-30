<?php

namespace Ions\Mail\Transport;

use Traversable;
use Ions\Mail;
use Ions\Mail\Address\AddressInterface;
use Ions\Mail\Header\HeaderInterface;

class Sendmail implements TransportInterface
{
    protected $parameters;
    protected $callable;
    protected $errstr;
    protected $operatingSystem;

    public function __construct($parameters = null)
    {
        if ($parameters !== null) {
            $this->setParameters($parameters);
        }
        $this->callable = [$this, 'mailHandler'];
    }

    public function setParameters($parameters)
    {
        if ($parameters === null || is_string($parameters)) {
            $this->parameters = $parameters;
            return $this;
        }
        if (!is_array($parameters) && !$parameters instanceof Traversable) {
            throw new \InvalidArgumentException(sprintf('%s expects a string, array, or Traversable object of parameters; received "%s"', __METHOD__, (is_object($parameters) ? get_class($parameters) : gettype($parameters))));
        }
        $string = '';
        foreach ($parameters as $param) {
            $string .= ' ' . $param;
        }
        trim($string);
        $this->parameters = $string;
        return $this;
    }

    public function setCallable($callable)
    {
        if (!is_callable($callable)) {
            throw new \InvalidArgumentException(sprintf('%s expects a callable argument; received "%s"',
                __METHOD__,
                gettype($callable)
            ));
        }
        $this->callable = $callable;
        return $this;
    }

    public function send(Mail\Message $message)
    {
        $to = $this->prepareRecipients($message);
        $subject = $this->prepareSubject($message);
        $body = $this->prepareBody($message);
        $headers = $this->prepareHeaders($message);
        $params = $this->prepareParameters($message);

        if (!$this->isWindowsOs()) {
            $to = str_replace("\r\n", "\n", $to);
            $subject = str_replace("\r\n", "\n", $subject);
            $body = str_replace("\r\n", "\n", $body);
            $headers = str_replace("\r\n", "\n", $headers);
        }

        call_user_func($this->callable, $to, $subject, $body, $headers, $params);
    }

    protected function prepareRecipients(Mail\Message $message)
    {
        $headers = $message->getHeaders();

        if (!$headers->has('to')) {
            throw new \RuntimeException('Invalid email; contains no "To" header');
        }

        $to = $headers->get('to');
        $list = $to->getAddressList();

        if (0 == count($list)) {
            throw new \RuntimeException('Invalid "To" header; contains no addresses');
        }

        if (!$this->isWindowsOs()) {
            return $to->getValue(HeaderInterface::FORMAT_ENCODED);
        }

        $addresses = [];
        foreach ($list as $address) {
            $addresses[] = $address->getEmail();
        }

        $addresses = implode(', ', $addresses);
        return $addresses;
    }

    protected function prepareSubject(Mail\Message $message)
    {
        $headers = $message->getHeaders();
        if (!$headers->has('subject')) {
            return;
        }
        $header = $headers->get('subject');
        return $header->getValue(HeaderInterface::FORMAT_ENCODED);
    }

    protected function prepareBody(Mail\Message $message)
    {
        if (!$this->isWindowsOs()) {
            return $message->getBodyText();
        }
        $text = $message->getBodyText();
        $text = str_replace("\n.", "\n..", $text);
        return $text;
    }

    protected function prepareHeaders(Mail\Message $message)
    {
        if ($this->isWindowsOs()) {
            return $message->getHeaders()->toString();
        }
        $headers = clone $message->getHeaders();
        $headers->removeHeader('To');
        $headers->removeHeader('Subject');
        $from = $headers->get('From');
        if ($from) {
            foreach ($from->getAddressList() as $address) {
                if (preg_match('/\\\"/', $address->getEmail())) {
                    throw new \RuntimeException('Potential code injection in From header');
                }
            }
        }
        return $headers->toString();
    }

    protected function prepareParameters(Mail\Message $message)
    {
        if ($this->isWindowsOs()) {
            return;
        }
        $parameters = (string)$this->parameters;
        $sender = $message->getSender();
        if ($sender instanceof AddressInterface) {
            $parameters .= ' -f' . $sender->getEmail();
            return $parameters;
        }
        $from = $message->getFrom();
        if (count($from)) {
            $from->rewind();
            $sender = $from->current();
            $parameters .= ' -f' . $sender->getEmail();
            return $parameters;
        }
        return $parameters;
    }

    public function mailHandler($to, $subject, $message, $headers, $parameters)
    {
        set_error_handler([$this, 'handleMailErrors']);
        if ($parameters === null) {
            $result = mail($to, $subject, $message, $headers);
        } else {
            $result = mail($to, $subject, $message, $headers, $parameters);
        }
        restore_error_handler();
        if ($this->errstr !== null || !$result) {
            $errstr = $this->errstr;
            if (empty($errstr)) {
                $errstr = 'Unknown error';
            }
            throw new \RuntimeException('Unable to send mail: ' . $errstr);
        }
    }

    public function handleMailErrors($errno, $errstr, $errfile = null, $errline = null, array $errcontext = null)
    {
        $this->errstr = $errstr;
        return true;
    }

    protected function isWindowsOs()
    {
        if (!$this->operatingSystem) {
            $this->operatingSystem = strtoupper(substr(PHP_OS, 0, 3));
        }
        return ($this->operatingSystem === 'WIN');
    }
}
