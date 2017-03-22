<?php

/**
 * @file
 * Contains \Drupal\gigya\Helper\GigyaHelper.
 */

namespace Drupal\gigya\Helper;

use Drupal;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use Drupal\user\Entity\User;
use Exception;
use Gigya\CmsStarterKit\GigyaApiHelper;
use Gigya\CmsStarterKit\sdk\GigyaApiRequest;
use Gigya\CmsStarterKit\sdk\GSApiException;
use Gigya\CmsStarterKit\sdk\GSObject;
use Gigya\CmsStarterKit\user\GigyaProfile;
use Gigya\CmsStarterKit\user\GigyaUser;
use Gigya\CmsStarterKit\user\GigyaUserFactory;


class GigyaHelper implements GigyaHelperInterface{
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
          return FALSE;
        }
      }
      else {
        return FALSE;
      }
    }
    if (is_array($obj)) {
      $obj = Json::encode($obj);
    }
    else if ($obj instanceof GigyaProfile) {
      //@TODO: think if / how handle this.
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
    if ($key = file_get_contents($keypath)) {
      return trim($key);
    }
    return FALSE;
    //@TODO: error handle and logs.
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
//    catch (Exception $e) {
//      Drupal::logger('gigya')->error('<pre>gigya api error ' . $e->getMessage() . '</pre>');
//      return $e;
//    }

  }

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

  public function saveUserLogoutCookie() {
    user_cookie_save(array('gigya' => 'gigyaLogOut'));
  }

  public function getUidByMail($mail) {

    return \Drupal::entityQuery('user')
      ->condition('mail',  \Drupal\Core\Database\Database::getConnection()->escapeLike($mail), 'LIKE')
      ->execute();
  }

  public function getUidByUUID($uuid) {
    return \Drupal::service('entity.repository')->loadEntityByUuid ('user', $uuid);
  }


  public function getUidByName($name) {
    return \Drupal::entityQuery('user')
      ->condition('name',  \Drupal\Core\Database\Database::getConnection()->escapeLike($name), 'LIKE')
      ->execute();
  }

  public function processFieldMapping($gigya_data, User $drupal_user) {
    try {
      $field_map = \Drupal::config('gigya.global')->get('gigya.fieldMapping');
      \Drupal::moduleHandler()
        ->alter('gigya_raas_map_data', $gigya_data, $drupal_user, $field_map);
      foreach ($field_map as $drupal_field => $raas_field) {
        $raas_field_parts = explode(".", $raas_field);
        $val = $this->getNestedValue($gigya_data, $raas_field_parts);
        if ($val !== NULL) {
          if (is_bool($val)) {
            $val = intval($val);
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

  protected function getEncKeyFile($uri) {
    /** @var Drupal\Core\StreamWrapper\StreamWrapperInterface $stream */
    $stream = \Drupal::service('stream_wrapper_manager')->getViaUri($uri);
    if ($stream == FALSE) {
      return realpath($uri);
    }
    return $stream->realpath();
  }
}
