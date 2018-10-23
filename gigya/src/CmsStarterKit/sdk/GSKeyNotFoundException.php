<?php

namespace Drupal\gigya\CmsStarterKit\sdk;

class GSKeyNotFoundException extends GSException {

  public function __construct($key) {
    $this->errorMessage = "GSObject does not contain a value for key " . $key;
  }
}