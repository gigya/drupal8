<?php

namespace Drupal\gigya\CmsStarterKit;


#[\AllowDynamicProperties]
abstract class GigyaJsonObject {

  /**
   * GigyaJsonObject constructor.
   *
   * @param $json
   */
  public function __construct($json) {
    if (NULL != $json) {
      $jsonArray = $json !== NULL ? json_decode($json, TRUE): [];
      foreach ($jsonArray as $key => $value) {
        $this->__set($key, $value);
      }
    }
  }

  /**
   * @param $name
   * @param $arguments
   *
   * @return mixed|null
   * @throws \Exception
   */
  public function __call($name, $arguments) {
    if (strpos($name, 'get') === 0) {
      $property = strtolower(substr($name, 3, 1)) . substr($name, 4);

      return $this->$property;
    }
    elseif (strpos($name, 'set') === 0) {
      $property = strtolower(substr($name, 3, 1)) . substr($name, 4);

      return $this->$property = $arguments[0];
    }
    else {
      throw new \Exception("Method $name does not exist");
    }
  }
  public function __get($name) {
    $getter = $name;
    $prop = lcfirst(substr($name, 3));
    if (method_exists($this, $getter)) {
      return call_user_func([$this, $getter]);
    }
    return property_exists($this, $prop) ? $this->$prop : NULL;
  }

  public function __set($name, $value) {
    $setter = 'set' . ucfirst($name);
    if (method_exists($this, $setter)) {
      call_user_func([$this, $setter], $value);
    }
    else {
      $this->$name = $value;
    }
  }

}
