<?php

/**
 * @file
 * Contains \Drupal\gigya\Helper\GigyaHelper.
 */

namespace Drupal\gigya\Helper;

use Behat\Mink\Exception\Exception;
use Drupal;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use Drupal\user\Entity\User;
use Gigya\GigyaApiHelper;
use Gigya\sdk\GigyaApiRequest;
use Gigya\sdk\GSApiException;
use Gigya\user\GigyaProfile;
use Gigya\user\GigyaUser;
use Gigya\user\GigyaUserFactory;


class GigyaHelper {
  private static function getNestedValue($obj, $keys) {
    while (!empty($keys)) {
      $key = array_shift($keys);

      if ($obj instanceof GigyaUser || $obj instanceof GigyaProfile) {
        $method = "get" . strtoupper($key);
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

  public static function enc($str) {
    return \Gigya\GigyaApiHelper::enc($str, self::getEncryptKey());
  }

  public static function decrypt($str) {
    return \Gigya\GigyaApiHelper::decrypt($str, self::getEncryptKey());
  }


  private static function getEncryptKey() {
    $keypath = \Drupal::config('gigya.global')->get('gigya.keyPath');
    $key = file_get_contents($keypath);
    return $key;
    //@TODO: error handle and logs.
  }

  private static function getAccessParams() {
    $access_params = array();
    $key = self::getEncryptKey();

    $access_params['api_key'] = Drupal::config('gigya.settings')->get('gigya.gigya_api_key');
    $access_params['app_secret'] = \Gigya\GigyaApiHelper::decrypt(Drupal::config('gigya.settings')->get('gigya.gigya_application_secret_key'), $key);
    $access_params['app_key'] = Drupal::config('gigya.settings')->get('gigya.gigya_application_key');
    $access_params['data_center'] = Drupal::config('gigya.settings')->get('gigya.gigya_data_center');
    return $access_params;
  }

  public static function sendApiCall($method, $params = NULL, $access_params = FALSE) {
    try {
      if (!$access_params) {
        $access_params = self::getAccessParams();
      }
      $request = new GigyaApiRequest($access_params['api_key'], $access_params['app_secret'], $method, NULL, $access_params['data_center'], TRUE, $access_params['app_key']);
      $request->setParam('url', 'https://gigya.com');

      $result = $request->send();
      if (Drupal::config('gigya.global')->get('gigya.gigyaDebugMode') == true) {

        // on first module load, api & secret are empty, so no values in response
        Drupal::logger('gigya')->debug('Response from gigya <br /><pre>callId : @callId,apicall:@method</pre>',
                                                array('@callId' => $result->getCallId(), '@method' => $method));
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
    catch (Exception $e) {
      Drupal::logger('gigya')->error('<pre>gigya api error ' . $e->getMessage() . '</pre>');
      return $e;
    }

  }

  public static function validateUid($uid, $uid_sig, $sig_timestamp) {
    try {
      return self::getGigyaApiHelper()->validateUid($uid, $uid_sig, $sig_timestamp);
    } catch (GSApiException $e) {
      return false;
    }
    catch (Exception $e) {
      return false;
    }
  }

  public static function getGigyaApiHelper() {
    $access_params = self::getAccessParams();
    return new GigyaApiHelper($access_params['api_key'], $access_params['app_key'], $access_params['app_secret'], $access_params['data_center']);
  }

  public static function saveUserLogoutCookie() {
    user_cookie_save(array('gigya' => 'gigyaLogOut'));
  }

  public static function getUidByMail($mail) {
    return \Drupal::entityQuery('user')
      ->condition('mail', Connection::escapeLike($mail), 'LIKE')
      ->execute();
  }

  public static function getUidByName($name) {
    return \Drupal::entityQuery('user')
      ->condition('name', Connection::escapeLike($name), 'LIKE')
      ->execute();
  }

  public static function processFieldMapping($gigya_data, User $drupal_user, $profileOnly = false) {
    $field_map = \Drupal::config('gigya.global')->get('gigya.fieldMapping');
    \Drupal::moduleHandler()->alter('gigya_raas_map_data', $gigya_data, $drupal_user, $field_map);
    foreach ($field_map as $drupal_field => $raas_field) {
      $raas_field_parts = explode(".", $raas_field);
      if ($profileOnly) {
        if (isset($raas_field_parts[0]) && $raas_field_parts[0] == 'profile') {
          array_shift($raas_field_parts);
        }
        else {
          continue;
        }
      }
      $val = self::getNestedValue($gigya_data, $raas_field_parts);
      if ($val !== NULL) {
        $drupal_user->set($drupal_field, $val);
      }
    }

  }

  public static function getGigyaUserFromArray($data) {
    return GigyaUserFactory::createGigyaProfileFromArray($data);
  }
}
