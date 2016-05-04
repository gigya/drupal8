<?php

/**
 * @file
 * Contains \Drupal\gigya\Helper\GigyaHelper.
 */

namespace Drupal\gigya\Helper;
use Gigya\sdk\GigyaApiRequest;
use Gigya\sdk\GSApiException;


class GigyaHelper {
  public static function sendApiCall($method, $api_key = NULL, $app_key = NULL, $app_secret = NULL, $data_center = NULL) {
    try {
      //@TODO: load values from config.
      if (!$api_key) {

      }
      if (!$app_key) {

      }
      if (!$app_secret) {

      }
      if (!$data_center) {

      }
      $request = new GigyaApiRequest($api_key, $app_secret, $method, NULL, $data_center, TRUE, $app_key);
      $request->setParam('url', 'http://gigya.com');
      //@TODO: check if we need it.
//      ini_set('arg_separator.output', '&');
      $request->send();
      //@TODO: check if we need it.
//      ini_restore('arg_separator.output');

//        global $user;
//        $account = clone $user;
//        $datestr = \Drupal::service('date.formatter')->format(time(), 'custom', 'Y-m-d H:i:s');
      return TRUE;
    } catch (GSApiException $e) {
      return $e;
    }

  }
}
