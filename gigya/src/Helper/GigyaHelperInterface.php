<?php

/**
 * @file
 * Contains \Drupal\gigya\Helper\GigyaHelperInterface.
 */

namespace Drupal\gigya\Helper;

use Drupal\user\UserInterface;
use Drupal\gigya\CmsStarterKit\GSApiException;
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

  public function validateAndFetchRaasUser($uid, $signature, $sig_timestamp);

  public function getGigyaApiHelper();

  public function getGigyaDsQuery();

  public function setDsData($uid, $type, $oid, $data);

	public function doSingleDsGet($type, $oid, $fields, $uid);

	public function doSingleDsSearch($type, $oid, $fields, $uid);

  public function saveUserLogoutCookie();

  public function getUidByMail($mail);

  public function getUidByUUID($uuid);

  public function getUidByName($name);

	public function checkEmailsUniqueness($gigyaUser, $uid);

	public function checkProfileEmail($profile_email, $loginIds);

	public function getFieldMappingConfig();

  public function processFieldMapping($gigya_data, UserInterface $drupal_user);

  public function getGigyaUserFromArray($data);

  public function getGigyaLanguages();

  /**
   * @return string
   *  the environment string to add to the API call.
   */
  public function getEnvString();

	public function sendCronEmail($job_type, $job_status, $to, $processed_items, $failed_items, $custom_email_body);

	public function sendEmail($subject, $body, $to);
}
