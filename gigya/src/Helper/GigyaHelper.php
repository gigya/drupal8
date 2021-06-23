<?php

/**
 * @file
 * Contains \Drupal\gigya\Helper\GigyaHelper.
 */

namespace Drupal\gigya\Helper;

use Drupal;
use Drupal\Component\Serialization\Json;
use Drupal\gigya\CmsStarterKit\ds\DsQueryException;
use Drupal\gigya\CmsStarterKit\GigyaApiRequest;
use Drupal\gigya\CmsStarterKit\GigyaAuthRequest;
use Drupal\gigya\CmsStarterKit\GSApiException;
use Exception;
use Drupal\gigya\CmsStarterKit\GigyaApiHelper;
use Drupal\gigya\CmsStarterKit\user\GigyaProfile;
use Drupal\gigya\CmsStarterKit\user\GigyaUser;
use Drupal\gigya\CmsStarterKit\ds\DsQueryObject;
use Gigya\PHP\GSException;
use Gigya\PHP\GSObject;
use Gigya\PHP\GSResponse;

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
    $keypath = Drupal::config('gigya.global')->get('gigya.keyPath');
    $key = $this->getEncKeyFile($keypath);

    if ($key !== FALSE && !empty(file_get_contents($key))) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  public function getEncryptKey() {
  	$path = Drupal::config('gigya.global')->get('gigya.keyPath');
    $keypath = $this->getEncKeyFile($path);

    try
    {
      if ($keypath !== FALSE && $key = trim(file_get_contents($keypath))) {
        if (!empty($key))
          return $key;
      }
      return false;
    }
    catch (Exception $e)
    {
      Drupal::logger('gigya')->error('Key file not found. Configure the correct path in your gigya.global YML file.');
      return false;
    }
  }

  public function getAccessParams() {
    $access_params = array();
    $key = $this->getEncryptKey();

    $access_params['api_key'] = Drupal::config('gigya.settings')->get('gigya.gigya_api_key');
    $access_params['auth_mode'] = Drupal::config('gigya.settings')->get('gigya.gigya_auth_mode') ?? 'user_secret';
		$access_params['auth_key'] = ($access_params['auth_mode'] === 'user_rsa')
			? GigyaApiHelper::decrypt(Drupal::config('gigya.settings')->get('gigya.gigya_rsa_private_key'), $key)
			: GigyaApiHelper::decrypt(Drupal::config('gigya.settings')->get('gigya.gigya_application_secret_key'), $key);
		$access_params['app_key'] = Drupal::config('gigya.settings')->get('gigya.gigya_application_key');
    $access_params['data_center'] = Drupal::config('gigya.settings')->get('gigya.gigya_data_center');

    return $access_params;
  }

	/**
	 * @param string        $method
	 * @param GSObject|null $params
	 * @param bool          $access_params
	 *
	 * @return GSResponse
	 *
	 * @throws \Drupal\gigya\CmsStarterKit\GSApiException
	 * @throws \Gigya\PHP\GSException
	 */
  public function sendApiCall(string $method, $params = null, $access_params = FALSE) {
    try {
      if (!$access_params) {
        $access_params = $this->getAccessParams();
      }
      if ($params == null) {
        $params = new GSObject();
      }

      $params->put('environment', $this->getEnvString());

			if (!empty($access_params['auth_mode']) && $access_params['auth_mode'] === 'user_rsa') {
				$request = new GigyaAuthRequest($access_params['api_key'], $access_params['auth_key'], $method, $params, $access_params['data_center'], TRUE, $access_params['app_key']);
			}
			else {
				$request = new GigyaApiRequest($access_params['api_key'], $access_params['auth_key'], $method, $params, $access_params['data_center'], TRUE, $access_params['app_key']);
			}

			$result = $request->send();
			if (Drupal::config('gigya.global')->get('gigya.gigyaDebugMode') == TRUE) {
				/* On first module load, API & secret are empty, so no values in response */
				Drupal::logger('gigya')
					->debug('Response from Gigya:<br /><pre>Call ID: @callId, API call: @method</pre>',
						[
							'@callId' => $result->getData()->getString('callId'),
							'@method' => $method,
						]);
			}

			return $result;
		} catch (GSException $e) {
			throw $e;
		} catch (GSApiException $e) {
			/* Always write error to log */
			Drupal::logger('gigya')
				->error('Gigya API error. Error code: @code<br />Response from Gigya:<br /><pre>Call ID: @callId, API call: @method, Error: @message</pre>',
					[
						'@callId'  => $e->getCallId(),
						'@method'  => $method,
						'@code'    => $e->getErrorCode(),
						'@message' => $e->getMessage(),
					]);

			throw $e;
		}
	}

  public function getGigyaApiHelper() {
    $access_params = $this->getAccessParams();
    return new GigyaApiHelper($access_params['api_key'], $access_params['app_key'], $access_params['auth_key'], $access_params['data_center'], $access_params['auth_mode']);
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
   * @return GSResponse
   * @throws GSApiException
   * @throws GSException
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
	 * @throws GSApiException
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
	 * @throws DsQueryException
	 * @throws GSApiException
	 * @throws GSException
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

  public function saveUserLogoutCookie() {
		setrawcookie('Drupal.visitor.gigya', rawurlencode('gigyaLogOut'), Drupal::time()->getRequestTime() + 31536000, '/', '', Drupal::request()->isSecure());
  }

  public function getGigyaLanguages() {
    return array("en" => "English (default)","ar" => "Arabic","bg" => "Bulgarian","ca" => "Catalan","hr" => "Croatian",
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
		$info = Drupal::service('extension.list.module')->getExtensionInfo('gigya');

		return '{"cms_name":"Drupal","cms_version":"Drupal_' . Drupal::VERSION . '","gigya_version":"Gigya_module_' . $info['version'] . '","php_version":"' . phpversion() . '"}';
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
					Drupal::logger('gigya_raas')
						->error('Failed to send email to ' . $email);
				}
			}
		} catch (\Exception $e) {
			Drupal::logger('gigya_raas')
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
  protected function getEncKeyFile(string $uri) {
    /** @var Drupal\Core\StreamWrapper\StreamWrapperInterface $stream */
    $stream = Drupal::service('stream_wrapper_manager')->getViaUri($uri);

    if ($stream == FALSE) {
      return realpath($uri);
    }

    return $stream->realpath();
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
}
