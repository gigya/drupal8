<?php

namespace Drupal\gigya\CmsStarterKit\user;

use Drupal\gigya\CmsStarterKit\GigyaJsonObject;

/**
 *
 */
class GigyaSubscription extends GigyaJsonObject {

  /**
   * @var bool
   */
  private $isSubscribed;

  /**
   * @var array
   */
  private $tags;

  /**
   * @var string
   */
  private $lastUpdatedSubscriptionState;

  /**
   * @var GigyaSubscriptionDoubleOptIn
   */
  private $doubleOptIn;

  /**
   * @return bool
   */
  public function getIsSubscribed() {
    return $this->isSubscribed;
  }

  /**
   * @param bool $isSubscribed
   */
  public function setIsSubscribed($isSubscribed) {
    $this->isSubscribed = $isSubscribed;
  }

  /**
   * @return array
   */
  public function getTags() {
    return $this->tags;
  }

  /**
   * @param string|array $tags
   */
  public function setTags($tags) {
    if ($tags !== NULL and is_string($tags)) {
      $tags = json_decode($tags);
    }
    $this->tags = $tags;
  }

  /**
   * @return string
   */
  public function getLastUpdatedSubscriptionState() {
    return $this->lastUpdatedSubscriptionState;
  }

  /**
   * @param string $lastUpdatedSubscriptionState
   */
  public function setLastUpdatedSubscriptionState($lastUpdatedSubscriptionState) {
    $this->lastUpdatedSubscriptionState = $lastUpdatedSubscriptionState;
  }

  /**
   * @return GigyaSubscriptionDoubleOptIn
   */
  public function getDoubleOptIn() {
    return $this->doubleOptIn;
  }

  /**
   * @param GigyaSubscriptionDoubleOptIn|array $doubleOptIn
   */
  public function setDoubleOptIn($doubleOptIn) {
    if (is_array($doubleOptIn)) {
      $doubleOptInObject = new GigyaSubscriptionDoubleOptIn(NULL);

      /** @var array $doubleOptIn */
      foreach ($doubleOptIn as $key => $value) {
        $methodName = 'set' . ucfirst($key);
        $methodParams = $value;
        $doubleOptInObject->$methodName($methodParams);
      }
    }
    else {
      $doubleOptInObject = $doubleOptIn;
    }

    $this->doubleOptIn = $doubleOptInObject;
  }

  /**
   * @return array|null
   */
  public function getDoubleOptInAsArray() {
    $result = NULL;

    if ($this->getDoubleOptIn()) {
      $result = $this->getDoubleOptIn()->asArray();
    }

    return $result;
  }

  /**
   * @return array
   */
  public function asArray() {
    return [
      'isSubscribed' => $this->getIsSubscribed(),
      'tags' => $this->getTags(),
      'lastUpdatedSubscriptionState' => $this->getLastUpdatedSubscriptionState(),
      'doubleOptIn' => $this->getDoubleOptInAsArray(),
    ];
  }

}
