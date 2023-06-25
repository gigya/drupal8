<?php

/**
 * @file
 * Contains \Drupal\gigya\Helper\GigyaHelperInterface.
 */

namespace Drupal\gigya\Helper;

use Exception;
use Gigya\PHP\GSObject;
use Gigya\PHP\GSResponse;

interface GigyaHelperInterface {
  public function getNestedValue($obj, $keys);

  public function enc($str);

  public function decrypt($str);

  public function checkEncryptKey();

  public function getEncryptKey();

  public function getAccessParams();

	/**
	 * @param string        $method
	 * @param GSObject|null $params
	 * @param bool          $access_params
	 *
	 * @return GSResponse
	 *
	 * @throws Exception
	 */
  public function sendApiCall(string $method, $params = null, $access_params = FALSE);

  public function getGigyaApiHelper();

  public function getGigyaDsQuery();

  public function setDsData($uid, $type, $oid, $data);

	public function doSingleDsGet($type, $oid, $fields, $uid);

	public function doSingleDsSearch($type, $oid, $fields, $uid);

  public function saveUserLogoutCookie();

  public function getGigyaLanguages();

  /**
   * @return string
   *  the environment string to add to the API call.
   */
  public function getEnvString();

	public function sendEmail($subject, $body, $to);
}
