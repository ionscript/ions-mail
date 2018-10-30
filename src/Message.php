<?php

namespace Ions\Mail;

use Traversable;

/**
 * Class Message
 * @package Ions\Mail
 */
class Message
{
    /**
     * @var
     */
    protected $body;

    /**
     * @var
     */
    protected $headers;

    /**
     * @var string
     */
    protected $encoding = 'ASCII';

    /**
     * @return bool
     */
    public function isValid()
    {
        $from = $this->getFrom();
        if (!$from instanceof AddressList) {
            return false;
        }
        return (bool)count($from);
    }

    /**
     * @param $encoding
     * @return $this
     */
    public function setEncoding($encoding)
    {
        $this->encoding = $encoding;
        $this->getHeaders()->setEncoding($encoding);
        return $this;
    }

    /**
     * @return string
     */
    public function getEncoding()
    {
        return $this->encoding;
    }

    /**
     * @param Headers $headers
     * @return $this
     */
    public function setHeaders(Headers $headers)
    {
        $this->headers = $headers;
        $headers->setEncoding($this->getEncoding());
        return $this;
    }

    /**
     * @return mixed
     */
    public function getHeaders()
    {
        if (null === $this->headers) {
            $this->setHeaders(new Headers());
            $date = Header\Date::fromString('Date: ' . date('r'));
            $this->headers->addHeader($date);
        }
        return $this->headers;
    }

    /**
     * @param $emailOrAddressList
     * @param null $name
     * @return Message
     */
    public function setFrom($emailOrAddressList, $name = null)
    {
        $this->clearHeaderByName('from');
        return $this->addFrom($emailOrAddressList, $name);
    }

    /**
     * @param $emailOrAddressOrList
     * @param null $name
     * @return $this
     */
    public function addFrom($emailOrAddressOrList, $name = null)
    {
        $addressList = $this->getFrom();
        $this->updateAddressList($addressList, $emailOrAddressOrList, $name, __METHOD__);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getFrom()
    {
        return $this->getAddressListFromHeader('from', Header\From::class);
    }

    /**
     * @param $emailOrAddressList
     * @param null $name
     * @return Message
     */
    public function setTo($emailOrAddressList, $name = null)
    {
        $this->clearHeaderByName('to');
        return $this->addTo($emailOrAddressList, $name);
    }

    /**
     * @param $emailOrAddressOrList
     * @param null $name
     * @return $this
     */
    public function addTo($emailOrAddressOrList, $name = null)
    {
        $addressList = $this->getTo();
        $this->updateAddressList($addressList, $emailOrAddressOrList, $name, __METHOD__);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTo()
    {
        return $this->getAddressListFromHeader('to', Header\To::class);
    }

    /**
     * @param $emailOrAddressList
     * @param null $name
     * @return Message
     */
    public function setCc($emailOrAddressList, $name = null)
    {
        $this->clearHeaderByName('cc');
        return $this->addCc($emailOrAddressList, $name);
    }

    /**
     * @param $emailOrAddressOrList
     * @param null $name
     * @return $this
     */
    public function addCc($emailOrAddressOrList, $name = null)
    {
        $addressList = $this->getCc();
        $this->updateAddressList($addressList, $emailOrAddressOrList, $name, __METHOD__);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCc()
    {
        return $this->getAddressListFromHeader('cc', Header\Cc::class);
    }

    /**
     * @param $emailOrAddressList
     * @param null $name
     * @return Message
     */
    public function setBcc($emailOrAddressList, $name = null)
    {
        $this->clearHeaderByName('bcc');
        return $this->addBcc($emailOrAddressList, $name);
    }

    /**
     * @param $emailOrAddressOrList
     * @param null $name
     * @return $this
     */
    public function addBcc($emailOrAddressOrList, $name = null)
    {
        $addressList = $this->getBcc();
        $this->updateAddressList($addressList, $emailOrAddressOrList, $name, __METHOD__);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getBcc()
    {
        return $this->getAddressListFromHeader('bcc', Header\Bcc::class);
    }

    /**
     * @param $emailOrAddressList
     * @param null $name
     * @return Message
     */
    public function setReplyTo($emailOrAddressList, $name = null)
    {
        $this->clearHeaderByName('reply-to');
        return $this->addReplyTo($emailOrAddressList, $name);
    }

    /**
     * @param $emailOrAddressOrList
     * @param null $name
     * @return $this
     */
    public function addReplyTo($emailOrAddressOrList, $name = null)
    {
        $addressList = $this->getReplyTo();
        $this->updateAddressList($addressList, $emailOrAddressOrList, $name, __METHOD__);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getReplyTo()
    {
        return $this->getAddressListFromHeader('reply-to', Header\ReplyTo::class);
    }

    /**
     * @param $emailOrAddress
     * @param null $name
     * @return $this
     */
    public function setSender($emailOrAddress, $name = null)
    {
        $header = $this->getHeaderByName('sender', Header\Sender::class);
        $header->setAddress($emailOrAddress, $name);
        return $this;
    }

    /**
     * @return null
     */
    public function getSender()
    {
        $headers = $this->getHeaders();
        if (!$headers->has('sender')) {
            return null;
        }
        $header = $this->getHeaderByName('sender', Header\Sender::class);
        return $header->getAddress();
    }

    /**
     * @param $subject
     * @return $this
     */
    public function setSubject($subject)
    {
        $headers = $this->getHeaders();
        if (!$headers->has('subject')) {
            $header = new Header\Subject();
            $headers->addHeader($header);
        } else {
            $header = $headers->get('subject');
        }
        $header->setSubject($subject);
        return $this;
    }

    /**
     * @return string
     */
    public function getSubject()
    {
        $headers = $this->getHeaders();
        if (!$headers->has('subject')) {
            return;
        }
        $header = $headers->get('subject');

        return $header->getValue();
    }

    /**
     * @param $body
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setBody($body)
    {
        if (!is_string($body) && $body !== null) {
            if (!is_object($body)) {
                throw new \InvalidArgumentException(sprintf('%s expects a string or object argument; received "%s"', __METHOD__, gettype($body)));
            }
            if (!$body instanceof Message\Message) {
                if (!method_exists($body, '__toString')) {
                    throw new \InvalidArgumentException(sprintf('%s expects object arguments of type Ions\Mime\Message or implementing __toString();' . ' object of type "%s" received', __METHOD__, get_class($body)));
                }
            }
        }
        $this->body = $body;
        if (!$this->body instanceof Message\Message) {
            return $this;
        }
        $headers = $this->getHeaders();
        $this->getHeaderByName('mime-version', Header\MimeVersion::class);
        if ($this->body->isMultiPart()) {
            $mime = $this->body->getMime();
            $header = $this->getHeaderByName('content-type', Header\ContentType::class);
            $header->setType('multipart/mixed');
            $header->addParameter('boundary', $mime->boundary());
            return $this;
        }
        $parts = $this->body->getParts();
        if (!empty($parts)) {
            $part = array_shift($parts);
            $headers->addHeaders($part->getHeadersArray("\r\n"));
        }
        return $this;
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @return string
     */
    public function getBodyText()
    {
        if ($this->body instanceof Message\Message) {
            return $this->body->generateMessage(Headers::EOL);
        }
        return (string)$this->body;
    }

    /**
     * @param $headerName
     * @param $headerClass
     * @return mixed
     */
    protected function getHeaderByName($headerName, $headerClass)
    {
        $headers = $this->getHeaders();
        if ($headers->has($headerName)) {
            $header = $headers->get($headerName);
        } else {
            $header = new $headerClass();
            $headers->addHeader($header);
        }
        return $header;
    }

    /**
     * @param $headerName
     */
    protected function clearHeaderByName($headerName)
    {
        $this->getHeaders()->removeHeader($headerName);
    }

    /**
     * @param $headerName
     * @param $headerClass
     * @return mixed
     * @throws \DomainException
     */
    protected function getAddressListFromHeader($headerName, $headerClass)
    {
        $header = $this->getHeaderByName($headerName, $headerClass);
        if (!$header instanceof Header\AbstractAddressList) {
            throw new \DomainException(sprintf('Cannot grab address list from header of type "%s"; not an AbstractAddressList implementation', get_class($header)));
        }
        return $header->getAddressList();
    }

    /**
     * @param AddressList $addressList
     * @param $emailOrAddressOrList
     * @param $name
     * @param $callingMethod
     * @throws \InvalidArgumentException
     */
    protected function updateAddressList(AddressList $addressList, $emailOrAddressOrList, $name, $callingMethod)
    {
        if ($emailOrAddressOrList instanceof Traversable) {
            foreach ($emailOrAddressOrList as $address) {
                $addressList->add($address);
            }
            return;
        }
        if (is_array($emailOrAddressOrList)) {
            $addressList->addMany($emailOrAddressOrList);
            return;
        }
        if (!is_string($emailOrAddressOrList) && !$emailOrAddressOrList instanceof Address\AddressInterface) {
            throw new \InvalidArgumentException(sprintf('%s expects a string, AddressInterface, array, AddressList, or Traversable as its first argument;' . ' received "%s"', $callingMethod, (is_object($emailOrAddressOrList) ? get_class($emailOrAddressOrList) : gettype($emailOrAddressOrList))));
        }
        if (is_string($emailOrAddressOrList) && $name === null) {
            $addressList->addFromString($emailOrAddressOrList);
            return;
        }
        $addressList->add($emailOrAddressOrList, $name);
    }

    /**
     * @return string
     */
    public function toString()
    {
        $headers = $this->getHeaders();
        return $headers->toString() . Headers::EOL . $this->getBodyText();
    }

    /**
     * @param $rawMessage
     * @return static
     */
    public static function fromString($rawMessage)
    {
        $message = new static();
        $headers = null;
        $content = null;
        Message\Decode::splitMessage($rawMessage, $headers, $content, Headers::EOL);
        if ($headers->has('mime-version')) {} // TODO: check this code
        $message->setHeaders($headers); // TODO: check this code
        $message->setBody($content); // TODO: check this code
        return $message;
    }
}
