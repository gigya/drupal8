<?php

namespace Drupal\gigya\CmsStarterKit\user;

use Drupal\gigya\CmsStarterKit\GigyaJsonObject;

class GigyaSubscriptionContainer extends GigyaJsonObject {

  /**
   * @var GigyaSubscription
   */
  private $email;

  /**
   * @return GigyaSubscription
   */
  public function getEmail() {
    return $this->email;
  }

  /**
   * @param GigyaSubscription $email
   */
  public function setEmail($email) {
    $this->email = $email;
  }

  /**
   * @return array|null
   */
  public function getSubscriptionAsArray() {
    $result = NULL;

    if ($this->getEmail()) {
      $result = $this->getEmail()->asArray();
    }

    return $result;
  }
}