<?php

namespace Drupal\gigya\Helper;

/**
 *
 */
interface GigyaHelperInterface {

  /**
   *
   */
  public function getNestedValue($obj, $keys);

  /**
   *
   */
  public function enc($str);

  /**
   *
   */
  public function decrypt($str);

  /**
   *
   */
  public function checkEncryptKey();

  /**
   *
   */
  public function getEncryptKey();

  /**
   *
   */
  public function getAccessParams();

  /**
   * @param string $method
   * @param \Gigya\PHP\GSObject|null $params
   * @param bool $access_params
   *
   * @return \Gigya\PHP\GSResponse
   *
   * @throws \Exception
   */
  public function sendApiCall(string $method, $params = NULL, $access_params = FALSE);

  /**
   *
   */
  public function getGigyaApiHelper();

  /**
   *
   */
  public function getGigyaDsQuery();

  /**
   *
   */
  public function setDsData($uid, $type, $oid, $data);

  /**
   *
   */
  public function doSingleDsGet($type, $oid, $fields, $uid);

  /**
   *
   */
  public function doSingleDsSearch($type, $oid, $fields, $uid);

  /**
   *
   */
  public function saveUserLogoutCookie();

  /**
   *
   */
  public function getGigyaLanguages();

  /**
   * @return string
   *   the environment string to add to the API call.
   */
  public function getEnvString();

  /**
   *
   */
  public function sendEmail($subject, $body, $to);

}
