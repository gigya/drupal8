<?php

/**
 * @file
 * Contains \Drupal\gigya\Helper\GigyaHelper.
 */

namespace Drupal\gigya\Helper;
use Gigya\GigyaApiHelper;
use Gigya\sdk\GigyaApiRequest;
use Gigya\sdk\GSApiException;


class GigyaHelper {

  private static function getAccessParams() {
    $access_params = array();
    $access_params['api_key'] = Drupal::config('gigya.settings')->get('gigya.gigya_api_key');
    $access_params['app_secret'] = Drupal::config('gigya.settings')->get('gigya.gigya_application_secret_key');
    $access_params['app_key'] = Drupal::config('gigya.settings')->get('gigya.gigya_application_key');
    $access_params['data_center'] = Drupal::config('gigya.settings')->get('gigya.gigya_data_center');
    return $access_params;
  }

  public static function sendApiCall($method, $access_params = false) {
    try {
      if (!$access_params) {
        $access_params = self::getAccessParams();
      }
      $request = new GigyaApiRequest($access_params['api_key'], $access_params['app_secret'], $method, NULL, $access_params['data_center'], TRUE, $access_params['app_key']);
      $request->setParam('url', 'https://gigya.com');
      $request->send();
//        global $user;
//        $account = clone $user;
//        $datestr = \Drupal::service('date.formatter')->format(time(), 'custom', 'Y-m-d H:i:s');
      return TRUE;
    } catch (GSApiException $e) {
      return $e;
    }

  }

  public static function validateUid($uid, $uid_sig, $sig_timestamp) {
    return self::getGigyaApiHelper()->validateUid($uid, $uid_sig, $sig_timestamp);
  }

  public static function getGigyaApiHelper() {
    $access_params = self::getAccessParams();
    return new GigyaApiHelper($access_params['api_key'], $access_params['app_key'], $access_params['app_secret'], $access_params['data_center']);
  }

}
