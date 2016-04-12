<?php
/**
 * Created by PhpStorm.
 * User: Yaniv Aran-Shamir
 * Date: 4/6/16
 * Time: 11:24 AM
 */

namespace gigya\sdk;


abstract class GigyaJsonObject
{
    public function __call($name, $arguments)
    {
        if (strpos($name, 'get') === 0) {
            $property = strtolower(substr($name, 3, 1)) . substr($name, 4);

            return $this->$property;
        } elseif (strpos($name, 'set') === 0) {
            $property = strtolower(substr($name, 3, 1)) . substr($name, 4);

            return $this->$property = $arguments[0];
        } else {
            throw new \Exception("Method $name does not exist");
        }
    }


    public function __get($name)
    {
        $getter = $name;
        $prop = substr($name, 3);
        if (method_exists($this, $getter)) {
            return call_user_func(array($this, $getter));
        }

        return $this->$prop;
    }

    public function __set($name, $value)
    {
        $setter = 'set' . ucfirst($name);
        if (method_exists($this, $setter)) {
            call_user_func(array($this, $setter), $value);
        } else {
            $this->$name = $value;
        }
    }

}