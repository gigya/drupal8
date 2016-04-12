<?php
/**
 * Created by PhpStorm.
 * User: Yaniv Aran-Shamir
 * Date: 4/6/16
 * Time: 8:49 PM
 */

namespace gigya\sdk;


class GSKeyNotFoundException extends GSException
{
    public function __construct($key)
    {
        $this->errorMessage = "GSObject does not contain a value for key " . $key;
    }

}