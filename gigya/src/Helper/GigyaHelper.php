<?php

/**
 * @file
 * Contains \Drupal\gigya\Helper\GigyaHelper.
 */

namespace Drupal\gigya\Helper;

use Drupal;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Database;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Exception;
use Drupal\gigya\CmsStarterKit\GigyaApiHelper;
use Drupal\gigya\CmsStarterKit\sdk\GigyaApiRequest;
use Drupal\gigya\CmsStarterKit\sdk\GSApiException;
use Drupal\gigya\CmsStarterKit\sdk\GSObject;
use Drupal\gigya\CmsStarterKit\user\GigyaProfile;
use Drupal\gigya\CmsStarterKit\user\GigyaUser;
use Drupal\gigya\CmsStarterKit\user\GigyaUserFactory;
use Drupal\gigya\CmsStarterKit\ds\DsQueryObject;

class GigyaHelper implements GigyaHelperInterface {

  /**
   * @param $obj
   * @param $keys
   *
   * @return null | string
   */
  public function getNestedValue($obj, $keys) {
    while (!empty($keys)) {
      $key = array_shift($keys);

      if ($obj instanceof GigyaUser || $obj instanceof GigyaProfile) {
        $method = "get" . ucfirst($key);
        $obj = $obj->$method();
      }
      else if (is_array($obj)) {
        if (array_key_exists($key, $obj)) {
          $obj = $obj[$key];
        }
        else {
          return NULL;
        }
      }
      else {
        return NULL;
      }
    }

    if (is_array($obj)) {
      $obj = Json::encode($obj);
    }

    return $obj;
  }

  public function enc($str) {
    return GigyaApiHelper::enc($str, $this->getEncryptKey());
  }

  public function decrypt($str) {
    return GigyaApiHelper::decrypt($str, $this->getEncryptKey());
  }

  public function checkEncryptKey() {
    $keypath = \Drupal::config('gigya.global')->get('gigya.keyPath');
    $key = $this->getEncKeyFile($keypath);
    if (!empty(file_get_contents($key))) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  public function getEncryptKey() {
  	$path = \Drupal::config('gigya.global')->get('gigya.keyPath');
    $keypath = $this->getEncKeyFile($path);
    try
	{
		if ($key = trim(file_get_contents($keypath))) {
			if (!empty($key))
				return $key;
		}
		return false;
	}
	catch (Exception $e)
	{
		\Drupal::logger('gigya')->error('Key file not found. Configure the correct path in your gigya.global TML file.');
		return false;
	}
  }

  public function getAccessParams() {
    $access_params = array();
    $key = $this->getEncryptKey();

    $access_params['api_key'] = Drupal::config('gigya.settings')->get('gigya.gigya_api_key');
    $access_params['app_secret'] = GigyaApiHelper::decrypt(Drupal::config('gigya.settings')->get('gigya.gigya_application_secret_key'), $key);
    $access_params['app_key'] = Drupal::config('gigya.settings')->get('gigya.gigya_application_key');
    $access_params['data_center'] = Drupal::config('gigya.settings')->get('gigya.gigya_data_center');
    return $access_params;
  }

	/**
	 * @param string $method
	 * @param GSObject $params
	 * @param bool $access_params
	 *
	 * @return Exception|GSApiException|\Drupal\gigya\CmsStarterKit\sdk\GSResponse
	 *
	 * @throws \Exception
	 */
  public function sendApiCall($method, $params = null, $access_params = FALSE) {
    try {
      if (!$access_params) {
        $access_params = $this->getAccessParams();
      }
      if ($params == null) {
        $params = new GSObject();
      }

      $params->put('environment', $this->getEnvString());

			$request = new GigyaApiRequest($access_params['api_key'], $access_params['app_secret'], $method, $params, $access_params['data_center'], TRUE, $access_params['app_key']);

			$result = $request->send();
			if (Drupal::config('gigya.global')->get('gigya.gigyaDebugMode') == TRUE) {
				/* On first module load, API & secret are empty, so no values in response */
				Drupal::logger('gigya')
					->debug('Response from gigya <br /><pre>callId: @callId, apicall: @method</pre>',
						[
							'@callId' => $result->getData()->getString('callId'),
							'@method' => $method,
						]);
			}

			return $result;
		} catch (GSApiException $e) {
			/* Always write error to log */
			Drupal::logger('gigya')->error('<pre>Gigya API error. Error code :' . $e->getErrorCode() . '</pre>');
			if ($e->getCallId()) {
				Drupal::logger('gigya')->error('Response from Gigya <br /><pre>Call ID: @callId, apicall:@method,
											 																		Error:@error</pre>',
					[
						'@callId' => $e->getCallId(),
						'@method' => $method,
						'@error' => $e->getErrorCode(),
					]);
			}

			return $e;
		}
	}

	/**
	 * Validates and gets Gigya user
	 *
	 * @param $uid
	 * @param $uid_sig
	 * @param $sig_timestamp
	 * @return bool | GigyaUser
	 */
  public function validateUid($uid, $uid_sig, $sig_timestamp) {
    try {
      $params = array('environment' => $this->getEnvString());

      return $this->getGigyaApiHelper()->validateUid($uid, $uid_sig, $sig_timestamp, NULL, NULL, $params);
    } catch (GSApiException $e) {
      Drupal::logger('gigya')->error("Gigya API call error: @error, Call ID: @callId", array('@callId' => $e->getCallId(), '@error' => $e->getMessage()));
      return false;
    }
    catch (Exception $e) {
      Drupal::logger('gigya')->error("General error validating gigya UID: " . $e->getMessage());
      return false;
    }
  }

  public function getGigyaApiHelper() {
    $access_params = $this->getAccessParams();
    return new GigyaApiHelper($access_params['api_key'], $access_params['app_key'], $access_params['app_secret'], $access_params['data_center']);
  }

  public function getGigyaDsQuery() {
    return new DsQueryObject($this->getGigyaApiHelper());
  }

  /**
   * @param string $uid
   * @param string $type
   * @param string $oid
   * @param array|object $data
   *
   * @return \Drupal\gigya\CmsStarterKit\sdk\GSResponse
   * @throws GSApiException
   * @throws \Drupal\gigya\CmsStarterKit\sdk\GSException
   */
  public function setDsData($uid, $type, $oid, $data) {
    $params = [];
    $params['type'] = $type;
    $params['UID'] = $uid;
    $params['oid'] = $oid;
    $params['data'] = json_encode($data);
    $res = $this->getGigyaApiHelper()->sendApiCall('ds.store', $params);
    return $res;
  }

	/**
	 * @param $type
	 * @param $oid
	 * @param $fields
	 * @param $uid
	 *
	 * @return mixed
	 * @throws \Drupal\gigya\CmsStarterKit\sdk\GSApiException
	 * @throws \Drupal\gigya\CmsStarterKit\sdk\GSException
	 */
  public function doSingleDsGet($type, $oid, $fields, $uid) {
    $dsQueryObj = $this->getGigyaDsQuery();
    $dsQueryObj->setOid($oid);
    $dsQueryObj->setTable($type);
    $dsQueryObj->setUid($uid);
    $dsQueryObj->setFields($fields);
    $res = $dsQueryObj->dsGet();
    return $res->serialize()['data'];
  }

	/**
	 * @param $type
	 * @param $oid
	 * @param $fields
	 * @param $uid
	 *
	 * @return array
	 * @throws \Drupal\gigya\CmsStarterKit\ds\DsQueryException
	 * @throws \Drupal\gigya\CmsStarterKit\sdk\GSApiException
	 * @throws \Drupal\gigya\CmsStarterKit\sdk\GSException
	 */
  public function doSingleDsSearch($type, $oid, $fields, $uid) {
    $dsQueryObj = $this->getGigyaDsQuery();
    $dsQueryObj->setFields($fields);
    $dsQueryObj->setTable($type);
    $dsQueryObj->addWhere("UID", "=", $uid, "string");
    $dsQueryObj->addWhere("oid", "=", $oid, "string");
    $res = $dsQueryObj->dsSearch()->serialize()['results'];
    return $this->dsProcessSearch($res);
  }

  private function dsProcessSearch($results) {
    $processed = array();
    foreach ($results as $result) {
      if (isset($result['data']) && is_array($result['data'])) {
        $processed += $result['data'];
      }
    }
    return $processed;
  }

  public function saveUserLogoutCookie() {
    user_cookie_save(array('gigya' => 'gigyaLogOut'));
  }

  public function getUidByMail($mail) {
    return \Drupal::entityQuery('user')
      ->condition('mail',  $mail)
      ->execute();
  }

  public function getUidByMails($mails) {
    return \Drupal::entityQuery('user')
      ->condition('mail',  $mails)
      ->execute();
  }

  /**
   * @param $uuid
   *
   * @return User
   */
  public function getUidByUUID($uuid) {
    return \Drupal::service('entity.repository')->loadEntityByUuid('user', $uuid);
  }

  /**
   * @param GigyaUser $gigyaUser
   * @param integer   $uid
   *
   * @return bool
   */
  public function checkEmailsUniqueness($gigyaUser, $uid) {
    if ($this->checkProfileEmail($gigyaUser->getProfile()->getEmail(), $gigyaUser->getLoginIDs()['emails'])) {
      $uid_check = $this->getUidByMail($gigyaUser->getProfile()->getEmail());
      if (empty($uid_check) || isset($uid_check[$uid])) {
        return $gigyaUser->getProfile()->getEmail();
      }
    }

    foreach ($gigyaUser->getloginIDs()['emails'] as $id) {
      $uid_check = $this->getUidByMail($id);
      if (empty($uid_check) || isset($uid_check[$uid])) {
        return $id;
      }
    }
    return FALSE;
  }

  public function checkProfileEmail($profile_email, $loginIds) {
    $exists = FALSE;
    foreach ($loginIds as $id) {
      if ($id == $profile_email) {
        $exists = TRUE;
      }
    }
    return $exists;
  }

  public function getUidByName($name) {
    return \Drupal::entityQuery('user')
      ->condition('name',  Database::getConnection()->escapeLike($name), 'LIKE')
      ->execute();
  }

	/**
	 * @return array|object|null
	 */
	public function getFieldMappingConfig() {
		$config = json_decode(\Drupal::config('gigya_raas.fieldmapping')
			->get('gigya.fieldmapping_config'));
		if (empty($config)) {
			$config = \Drupal::config('gigya.global')->get('gigya.fieldMapping');
		}

		return $config;
	}

	/**
	 * This function enriches the Drupal user with Gigya data, but it does not permanently save the user data!
	 *
	 * @param GigyaUser $gigya_data
	 * @param \Drupal\user\UserInterface $drupal_user
	 */
  public function processFieldMapping($gigya_data, UserInterface $drupal_user) {
    try {
			$field_map = $this->getFieldMappingConfig();

      try {
	      \Drupal::moduleHandler()
	        ->alter('gigya_raas_map_data', $gigya_data, $drupal_user, $field_map);
      }
      catch (Exception $e) {
	      Drupal::logger('gigya')->debug('Error altering field map data: @message',
	                                     array('@message' => $e->getMessage()));
      }

      foreach ($field_map as $drupal_field => $raas_field) {
      	/* Drupal fields to exclude even if configured in field mapping schema */
        if ($drupal_field == 'mail' or $drupal_field == 'name') {
          continue;
        }

        $raas_field_parts = explode('.', $raas_field);
        $val = $this->getNestedValue($gigya_data, $raas_field_parts);

				if ($val !== NULL) {
	        $drupal_field_type = 'string';

					try {
						$drupal_field_type = $drupal_user->get($drupal_field)->getFieldDefinition()->getType();
	        }
	        catch (Exception $e)
	        {
		        Drupal::logger('gigya')->debug('Error getting field definition for field map: @message',
							['@message' => $e->getMessage()]);
					}

	        /* Handles Boolean types */
          if ($drupal_field_type == 'boolean') {
            if (is_bool($val)) {
              $val = intval($val);
            }
            else {
              \Drupal::logger('gigya')->error('Failed to map ' . $drupal_field . ' from Gigya - Drupal type is boolean but Gigya type isn\'t');
            }
          }

          /* Perform the mapping from Gigya to Drupal */
					try {
						$drupal_user->set($drupal_field, $val);
					} catch (\InvalidArgumentException $e) {
						Drupal::logger('gigya')
							->debug('Error inserting mapped field: @message',
								['@message' => $e->getMessage()]);
					}
        }
			}
		} catch (Exception $e) {
			Drupal::logger('gigya')->debug('processFieldMapping error @message',
				['@message' => $e->getMessage()]);
		}
	}

	/**
	 * Queries Gigya with the accounts.search call
	 *
	 * @param string|array $query The literal query to send to accounts.search,
	 *   or a set of params to send instead (useful for cursors)
	 * @param bool $use_cursor
	 *
	 * @return GigyaUser[]
	 *
	 * @throws \Drupal\gigya\CmsStarterKit\sdk\GSApiException
	 * @throws \Drupal\gigya\CmsStarterKit\sdk\GSException
	 */
	public function searchGigyaUsers($query, $use_cursor = FALSE) {
		$gigya_users = [];

		if (is_array($query)) { /* Query is actually a set of params. Useful for setting cursor ID instead of query */
			$params = $query;
		}
		else {
			$params = ['query' => $query];
			if ($use_cursor) {
				$params['openCursor'] = TRUE;
			}
		}

		$gigya_data = $this->getGigyaApiHelper()
			->sendApiCall('accounts.search', $params)
			->getData()
			->serialize();

		foreach ($gigya_data['results'] as $user_data) {
			if (isset($user_data['profile'])) {
				$profile_array = $user_data['profile'];
				$gigya_user = GigyaUserFactory::createGigyaUserFromArray($user_data);
				$gigya_profile = GigyaUserFactory::createGigyaProfileFromArray($profile_array);
				$gigya_user->setProfile($gigya_profile);

				$gigya_users[] = $gigya_user;
			}
		}

		if (!empty($gigya_data['nextCursorId'])) {
			$cursorId = $gigya_data['nextCursorId'];
			return array_merge($gigya_users, $this->searchGigyaUsers(['cursorId' => $cursorId]));
		}

		return $gigya_users;
	}

	public function getGigyaUserFromArray($data) {
    return GigyaUserFactory::createGigyaProfileFromArray($data);
  }

  public function getGigyaLanguages() {
    return array("en" => "English (default)","ar" => "Arabic","br" => "Bulgarian","ca" => "Catalan","hr" => "Croatian",
                "cs" => "Czech","da" => "Danish","nl" => "Dutch","fi" => "Finnish","fr" => "French","de" => "German",
                "el" => "Greek","he" => "Hebrew","hu" => "Hungarian","id" => "Indonesian (Bahasa)","it" => "Italian",
                "ja" => "Japanese","ko" => "Korean","ms" => "Malay","no" => "Norwegian","fa" => "Persian (Farsi)",
                "pl" => "Polish","pt" => "Portuguese","ro" => "Romanian","ru" => "Russian","sr" => "Serbian (Cyrillic)",
                "sk" => "Slovak","sl" => "Slovenian","es" => "Spanish","sv" => "Swedish","tl" => "Tagalog","th" => "Thai",
                "tr" => "Turkish","uk" => "Ukrainian","vi" => "Vietnamese","zh-cn" => "Chinese (Mandarin)","Chinese (Hong Kong)" => "zh-cn",
                "zh-hk" => "Chinese (Hong Kong)","Chinese (Taiwan)" => "zh-hk","zh-tw" => "Chinese (Taiwan)","Croatian" => "zh-tw","nl-inf" => "Dutch Informal",
                "Finnish" => "nl-inf","fr-inf" => "French Informal","German" => "fr-inf","de-inf" => "German Informal","Greek" => "de-inf",
                "pt-br" => "Portuguese (Brazil)","Romanian" => "pt-br","es-inf" => "Spanish Informal","Spanish (Lat-Am)" => "es-inf",
                "es-mx" => "Spanish (Lat-Am)","Swedish" => "es-mx");
  }

  /**
   * @return string
   *  the environment string to add to the API call.
   */
  public function getEnvString() {
    $info = system_get_info('module', 'gigya');
    return '{"cms_name":"Drupal","cms_version":"Drupal_' . \Drupal::VERSION . '","gigya_version":"Gigya_module_' .$info['version'] . '"}';
  }

	public function sendCronEmail($job_type, $job_status, $to, $processed_items = NULL, $failed_items = NULL, $custom_email_body = '') {
		$email_body = $custom_email_body;
		if ($job_status == 'succeeded' or $job_status == 'completed with errors') {
			$email_body = 'Job ' . $job_status . ' on ' . gmdate("F n, Y H:i:s") . ' (UTC).';
			if ($processed_items !== NULL) {
				$email_body .= PHP_EOL . $processed_items . ' ' . (($processed_items > 1) ? 'items' : 'item') . ' successfully processed, ' . $failed_items . ' failed.';
			}
		}
		elseif ($job_status == 'failed') {
			$email_body = 'Job failed. No items were processed. Please consult the Drupal log (Administration > Reports > Recent log messages) for more info.';
		}

		return $this->sendEmail('Gigya cron job of type ' . $job_type . ' ' . $job_status . ' on website ' . \Drupal::request()
				->getHost(),
			$email_body,
			$to);
	}

	public function sendEmail($subject, $body, $to) {
		$mail_manager = \Drupal::service('plugin.manager.mail');
		$module = 'gigya_raas';
		$params['from'] = 'Gigya IdentitySync';
		$params['subject'] = $subject;
		$params['message'] = $body;
		$key = 'job_email';

		try /* For testability */ {
			$langcode = \Drupal::currentUser()->getPreferredLangcode();
		} catch (\Exception $e) {
			$langcode = 'en';
		}
		if (!isset($langcode)) {
			$langcode = 'en';
		}

		try {
			foreach (array_filter(explode(',', $to)) as $email) {
				$result = $mail_manager->mail($module, $key, trim($email), $langcode, $params, NULL, $send = TRUE);
				if (!$result) {
					\Drupal::logger('gigya_raas')
						->error('Failed to send email to ' . $email);
				}
			}
		} catch (\Exception $e) {
			\Drupal::logger('gigya_raas')
				->error('Failed to send emails - ' . $e->getMessage());

			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Gets real full path of the key even if only relative path is provided
	 *
	 * @param string	$uri	URI for the key, recommended to use full path
	 * @return string
	 */
  protected function getEncKeyFile($uri) {
    /** @var Drupal\Core\StreamWrapper\StreamWrapperInterface $stream */
    $stream = \Drupal::service('stream_wrapper_manager')->getViaUri($uri);
    if ($stream == FALSE) {
      return realpath($uri);
    }
    return $stream->realpath();
  }
}
