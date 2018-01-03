<?php

namespace Ions\Mail\Transport;

use Traversable;

abstract class Factory
{
    protected static $classMap = [
        'file' => File::class,
        'inmemory' => InMemory::class,
        'memory' => InMemory::class,
        'null' => InMemory::class,
        'sendmail' => Sendmail::class,
        'smtp' => Smtp::class
    ];

    public static function create($spec = [])
    {
        if (!is_array($spec)) {
            throw new \InvalidArgumentException(sprintf(
                '%s expects an array; received "%s"',
                __METHOD__,
                gettype($spec)
            ));
        }

        $type = isset($spec['type']) ? $spec['type'] : 'sendmail';

        $normalizedType = strtolower($type);

        if (isset(static::$classMap[$normalizedType])) {
            $type = static::$classMap[$normalizedType];
        }

        if (!class_exists($type)) {
            throw new \DomainException(sprintf(
                '%s expects the "type" attribute to resolve to an existing class; received "%s"',
                __METHOD__,
                $type
            ));
        }

        $transport = new $type;

        if (!$transport instanceof TransportInterface) {
            throw new \DomainException(sprintf(
                '%s expects the valid "type" attribute; received "%s"',
                __METHOD__,
                $type
            ));
        }

        if ($transport instanceof Smtp && isset($spec['options'])) {
            $transport->setOptions(new SmtpOptions($spec['options']));
        }

        if ($transport instanceof File && isset($spec['options'])) {
            $transport->setOptions(new FileOptions($spec['options']));
        }

        return $transport;
    }
}
