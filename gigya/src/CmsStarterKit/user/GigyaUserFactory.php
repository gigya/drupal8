<?php

namespace Drupal\gigya\CmsStarterKit\user;

use Drupal\gigya\CmsStarterKit\user\GigyaProfile;
use Drupal\gigya\CmsStarterKit\user\GigyaSubscriptionContainer;
use Drupal\gigya\CmsStarterKit\user\GigyaSubscription;

class GigyaUserFactory {

  public static function createGigyaUserFromJson($json) {
    return new GigyaUser($json);
  }

  public static function createGigyaUserFromArray($array) {
    $gigyaUser = new GigyaUser(NULL);
    foreach ($array as $key => $value) {
      $gigyaUser->__set($key, $value);
    }

    if (array_key_exists('profile', $array)) {
      $profileArray = $array['profile'];
      $gigyaProfile = self::createGigyaProfileFromArray($profileArray);
      $gigyaUser->setProfile($gigyaProfile);
    }

    if (array_key_exists('subscriptions', $array)) {
      $subscriptionsArray = $array['subscriptions'];
      $gigyaSubscriptions = self::createGigyaSubscriptionsFromArray($subscriptionsArray);
      $gigyaUser->setSubscriptions($gigyaSubscriptions);
    }

    return $gigyaUser;
  }

  public static function createGigyaProfileFromJson($json) {
    $gigyaArray = json_decode($json);

    return self::createGigyaProfileFromArray($gigyaArray);
  }

  public static function createGigyaProfileFromArray($array) {
    $gigyaProfile = new GigyaProfile(NULL);
    foreach ($array as $key => $value) {
      $gigyaProfile->__set($key, $value);
    }

    return $gigyaProfile;
  }

  /**
   * @param array $array subscriptions data
   *
   * @return array
   */
  public static function createGigyaSubscriptionsFromArray($array) {
    $gigyaSubscriptions = [];

    /** @var array $subscriptionData */
    foreach ($array as $subscriptionId => $subscriptionData) {
      if (array_key_exists('email', $subscriptionData)) {
        $subscription = new GigyaSubscription(NULL);

        foreach ($subscriptionData['email'] as $subscriptionField => $subscriptionValue) {
          $methodName = 'set' . ucfirst($subscriptionField);
          $methodParams = $subscriptionValue;
          $subscription->$methodName($methodParams);
        }

        $subscriptionContainer = new GigyaSubscriptionContainer(NULL);
        $subscriptionContainer->setEmail($subscription);

        $gigyaSubscriptions[$subscriptionId] = $subscriptionContainer;
      }
    }

    return $gigyaSubscriptions;
  }
}