<?php

/**
 * @file
 * Contains \Drupal\gigya\Helper\GigyaHelper.
 */

namespace Drupal\gigya\Helper;

include_once "/var/www/d8dev/modules/gigya/vendor/autoload.php";

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

  private static function getAccessParams() {
    $access_params = array();
    $access_params['api_key'] = Drupal::config('gigya.settings')->get('gigya.gigya_api_key');
    $access_params['app_secret'] = Drupal::config('gigya.settings')->get('gigya.gigya_application_secret_key');
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
      return $request->send();
    } catch (GSApiException $e) {
      return $e;
    }
    catch (Exception $e) {
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

  public static function processFieldMapping(GigyaUser $gigya_user, User $drupal_user) {
    $field_map = \Drupal::config('gigya.global')->get('gigya.fieldMapping');

    foreach ($field_map as $drupal_field => $raas_field) {
      $raas_field_parts = explode(".", $raas_field);
      $val = self::getNestedValue($gigya_user, $raas_field_parts);
      if ($val !== NULL) {
        $drupal_user->set($drupal_field, $val);
      }
    }

  }
}
