<?php

/**
 * @file
 * Contains \Drupal\gigya\Helper\GigyaHelperInterface.
 */

namespace Drupal\gigya\Helper;

use Drupal\user\Entity\User;

interface GigyaHelperInterface {
  public function getNestedValue($obj, $keys);

  public function enc($str);

  public function decrypt($str);

  public function checkEncryptKey();

  public function getEncryptKey();

  public function getAccessParams();

  public function sendApiCall($method, $params = null, $access_params = FALSE);

  public function validateUid($uid, $uid_sig, $sig_timestamp);

  public function getGigyaApiHelper();

  public function saveUserLogoutCookie();

  public function getUidByMail($mail);

  public function getUidByUUID($uuid);

  public function getUidByName($name);

  public function processFieldMapping($gigya_data, User $drupal_user);

  public function getGigyaUserFromArray($data);

  public function getGigyaLanguages();
  /**
   * @return string
   *  the environment string to add to the API call.
   */
  public function getEnvString();
}
