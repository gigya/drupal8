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
use Exception;
use Gigya\CmsStarterKit\GigyaApiHelper;
use Gigya\CmsStarterKit\sdk\GigyaApiRequest;
use Gigya\CmsStarterKit\sdk\GSApiException;
use Gigya\CmsStarterKit\sdk\GSObject;
use Gigya\CmsStarterKit\user\GigyaProfile;
use Gigya\CmsStarterKit\user\GigyaUser;
use Gigya\CmsStarterKit\user\GigyaUserFactory;
use Gigya\CmsStarterKit\ds\DsQueryObject;

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
    if ($key) {
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
		if ($key = file_get_contents($keypath)) {
		  return trim($key);
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
	 * @param      $method
	 * @param null $params
	 * @param bool $access_params
	 * @return Exception|GSApiException|\Gigya\CmsStarterKit\sdk\GSResponse
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
      if (Drupal::config('gigya.global')->get('gigya.gigyaDebugMode') == true) {

        // on first module load, api & secret are empty, so no values in response
        Drupal::logger('gigya')->debug('Response from gigya <br /><pre>callId : @callId,apicall:@method</pre>',
                                                array('@callId' => $result->getData()->getString('callId'), '@method' => $method));
      }
      return $result;
    } catch (GSApiException $e) {
      //Always write error to log.
      Drupal::logger('gigya')->error('<pre>gigya api error error code :' . $e->getErrorCode() . '</pre>');
      if ($e->getCallId()) {

        Drupal::logger('gigya')->error('Response from gigya <br /><pre>callId : @callId,apicall:@method
                                                 ,Error:@error</pre>', array('@callId' => $e->getCallId(),
                                                '@method' => $method, '@error' => $e->getErrorCode()));
      }

      return $e;
    }
  }

	/**
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
      Drupal::logger('gigya')->error("Gigya api call error " . $e->getMessage());
      return false;
    }
    catch (Exception $e) {
      Drupal::logger('gigya')->error("General error validating gigya uid " . $e->getMessage());
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

  public function setDsData($uid, $type, $oid, $data) {
    $params = [];
    $params['type'] = $type;
    $params['UID'] = $uid;
    $params['oid'] = $oid;
    $params['data'] = json_encode($data);
    $res = $this->getGigyaApiHelper()->sendApiCall('ds.store', $params);
    return $res;
  }

  public function doSingleDsGet($type, $oid, $fields, $uid) {
    $dsQueryObj = $this->getGigyaDsQuery();
    $dsQueryObj->setOid($oid);
    $dsQueryObj->setTable($type);
    $dsQueryObj->setUid($uid);
    $dsQueryObj->setFields($fields);
    $res = $dsQueryObj->dsGet();
    return $res->serialize()['data'];
  }

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

  public function processFieldMapping($gigya_data, Drupal\user\UserInterface $drupal_user) {
    try {
      $field_map = \Drupal::config('gigya.global')->get('gigya.fieldMapping');
      \Drupal::moduleHandler()
        ->alter('gigya_raas_map_data', $gigya_data, $drupal_user, $field_map);
      foreach ($field_map as $drupal_field => $raas_field) {
        if ($drupal_field == 'mail') {
          continue;
        }
        $raas_field_parts = explode(".", $raas_field);
        $val = $this->getNestedValue($gigya_data, $raas_field_parts);
        if ($val !== NULL) {
          $drupal_field_type = $drupal_user->get($drupal_field)->getFieldDefinition()->getType();
          if ($drupal_field_type == 'boolean') {
            if (is_bool($val)) {
              $val = intval($val);
            }
            else {
              \Drupal::logger('gigya')->error('Failed to map ' . $drupal_field . ' from Gigya - Drupal type is boolean but Gigya type isn\'t');
            }
          }
          $drupal_user->set($drupal_field, $val);
        }
      }
    } catch (Exception $e) {
      Drupal::logger('gigya')->debug('processFieldMapping error @message',
        array('@message' => $e->getMessage()));
    }
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
