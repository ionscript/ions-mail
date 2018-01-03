<?php

namespace Ions\Mail\Transport;

use Ions\Mail\Address;
use Ions\Mail\Headers;
use Ions\Mail\Message;
use Ions\Mail\Protocol;

class Smtp implements TransportInterface
{
    protected $options;
    protected $envelope;
    protected $connection;
    protected $autoDisconnect = true;

    public function __construct(SmtpOptions $options = null)
    {
        if (!$options instanceof SmtpOptions) {
            $options = new SmtpOptions();
        }
        $this->setOptions($options);
    }

    public function setOptions(SmtpOptions $options)
    {
        $this->options = $options;
        return $this;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function setEnvelope(Envelope $envelope)
    {
        $this->envelope = $envelope;
    }

    public function getEnvelope()
    {
        return $this->envelope;
    }

    public function setAutoDisconnect($flag)
    {
        $this->autoDisconnect = (bool)$flag;
        return $this;
    }

    public function getAutoDisconnect()
    {
        return $this->autoDisconnect;
    }

    public function __destruct()
    {
        if ($this->connection instanceof Protocol\Smtp) {
            try {
                $this->connection->quit();
            } catch (\Exception $e) {}

            if ($this->autoDisconnect) {
                $this->connection->disconnect();
            }
        }
    }

    public function setConnection(Protocol\AbstractProtocol $connection)
    {
        $this->connection = $connection;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function disconnect()
    {
        if (!empty($this->connection) && ($this->connection instanceof Protocol\Smtp)) {
            $this->connection->disconnect();
        }
    }

    public function send(Message $message)
    {
        $connection = $this->getConnection();
        if (!($connection instanceof Protocol\Smtp) || !$connection->hasSession()) {
            $connection = $this->connect();
        } else {
            $connection->rset();
        }
        $from = $this->prepareFromAddress($message);
        $recipients = $this->prepareRecipients($message);
        $headers = $this->prepareHeaders($message);
        $body = $this->prepareBody($message);
        if ((count($recipients) == 0) && (!empty($headers) || !empty($body))) {
            throw new \RuntimeException(sprintf('%s transport expects at least one recipient if the message has at least one header or body', __CLASS__));
        }
        $connection->mail($from);
        foreach ($recipients as $recipient) {
            $connection->rcpt($recipient);
        }
        $connection->data($headers . Headers::EOL . $body);
    }

    protected function prepareFromAddress(Message $message)
    {
        if ($this->getEnvelope() && $this->getEnvelope()->getFrom()) {
            return $this->getEnvelope()->getFrom();
        }
        $sender = $message->getSender();
        if ($sender instanceof Address\AddressInterface) {
            return $sender->getEmail();
        }
        $from = $message->getFrom();
        if (!count($from)) {
            throw new \RuntimeException(sprintf('%s transport expects either a Sender or at least one From address in the Message; none provided', __CLASS__));
        }
        $from->rewind();
        $sender = $from->current();
        return $sender->getEmail();
    }

    protected function prepareRecipients(Message $message)
    {
        if ($this->getEnvelope() && $this->getEnvelope()->getTo()) {
            return (array)$this->getEnvelope()->getTo();
        }
        $recipients = [];
        foreach ($message->getTo() as $address) {
            $recipients[] = $address->getEmail();
        }
        foreach ($message->getCc() as $address) {
            $recipients[] = $address->getEmail();
        }
        foreach ($message->getBcc() as $address) {
            $recipients[] = $address->getEmail();
        }
        $recipients = array_unique($recipients);
        return $recipients;
    }

    protected function prepareHeaders(Message $message)
    {
        $headers = clone $message->getHeaders();
        $headers->removeHeader('Bcc');
        return $headers->toString();
    }

    protected function prepareBody(Message $message)
    {
        return $message->getBodyText();
    }

    protected function lazyLoadConnection()
    {
        $options = $this->getOptions();
        $config = $options->getConnectionConfig();
        $config['host'] = $options->getHost();
        $config['port'] = $options->getPort();
        $connection = $this->plugin($options->getConnectionClass(), $config);
        $this->connection = $connection;
        return $this->connect();
    }

    protected function connect()
    {
        if (!$this->connection instanceof Protocol\Smtp) {
            return $this->lazyLoadConnection();
        }
        $this->connection->connect();
        $this->connection->helo($this->getOptions()->getName());
        return $this->connection;
    }
}
