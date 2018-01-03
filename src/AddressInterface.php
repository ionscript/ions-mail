<?php

namespace Ions\Mail;

/**
 * Interface AddressInterface
 * @package Ions\Mail\Address
 */
interface AddressInterface
{
    /**
     * @return mixed
     */
    public function getEmail();

    /**
     * @return mixed
     */
    public function getName();

    /**
     * @return mixed
     */
    public function toString();
}
