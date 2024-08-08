<?php

namespace Drupal\gigya_raas\Helper;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Database\Database;
use Drupal\gigya\CmsStarterKit\GSApiException;
use Drupal\gigya\CmsStarterKit\user\GigyaUser;
use Drupal\gigya\CmsStarterKit\user\GigyaUserFactory;
use Drupal\gigya\Helper\GigyaHelper;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Firebase\JWT\JWT;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Entity\FieldableEntityInterface;

class GigyaRaasHelper {
  private $gigya_helper;

  public static function getSessionConfig($type = 'regular') {
    if ($type == 'remember_me') {
      $session_type = \Drupal::config('gigya_raas.settings')->get('gigya_raas.remember_me_session_type');
      $session_time = \Drupal::config('gigya_raas.settings')->get('gigya_raas.remember_me_session_time');
    }
    else {
      $session_type = \Drupal::config('gigya_raas.settings')->get('gigya_raas.session_type');
      $session_time = \Drupal::config('gigya_raas.settings')->get('gigya_raas.session_time');
    }

    return [
      'type' => $session_type,
      'time' => $session_time,
    ];
  }


  public function __construct() {
    $this->gigya_helper = new GigyaHelper();
  }

  /**
   * Validates and gets Gigya user.
   *
   * @param $uid
   * @param $signature
   * @param $sig_timestamp
   *
   * @return bool | GigyaUser| null
   */
  public function validateAndFetchRaasUser($uid, $signature, $sig_timestamp) {
    $params = ['environment' => $this->gigya_helper->getEnvString()];

    $auth_mode = \Drupal::config('gigya.settings')->get('gigya.gigya_auth_mode') ?? 'user_secret';

    try {
      return ($auth_mode == 'user_rsa')
        ? $this->gigya_helper->getGigyaApiHelper()->validateJwtAuth($uid, $signature, NULL, NULL, $params)
        : $this->gigya_helper->getGigyaApiHelper()->validateUid($uid, $signature, $sig_timestamp, NULL, NULL, $params);
    }
    catch (GSApiException $e) {
      \Drupal::logger('gigya')
        ->error("Gigya API call error: @errorCode: @error, Call ID: @callId", [
          '@callId' => $e->getCallId(),
          '@error' => $e->getMessage(),
          '@errorCode' => $e->getCode() ?? -1,
        ]);
      return FALSE;
    }
    catch (\Exception $e) {
      \Drupal::logger('gigya')->error("General error validating gigya UID: " . $e->getMessage());
      return FALSE;
    }
  }

  public function getUidByMail($mail) {
    return \Drupal::entityQuery('user')
      ->accessCheck()
      ->condition('mail', $mail)
      ->execute();
  }

  public function getUidByMails($mails) {
    return \Drupal::entityQuery('user')
      ->accessCheck()
      ->condition('mail', $mails, 'IN')
      ->execute();
  }

  public function doesFieldExist(string $field_name) {

    return \Drupal::service('entity_field.manager')
                  ->getFieldDefinitions('user', 'user')[$field_name] ?? NULL;
  }

  /**
   * @param $uuid
   *
   * @return \Drupal\user\Entity\User|false| null
   */
  public function getDrupalUserByGigyaUid($uuid) {

    $uuid_field = \Drupal::config('gigya_raas.fieldmapping')
                         ->get('gigya.uid_mapping');
    if (empty($uuid_field)) {
      $uuid_field = 'uuid';

    }

    if ($uuid_field === 'uuid') {
      return \Drupal::service('entity.repository')
                    ->loadEntityByUuid('user', $uuid);

    }
    else {

      $ids   = \Drupal::entityQuery('user')
                      ->accessCheck()
                      ->condition($uuid_field, $uuid, '=')
                      ->execute();
      $users = User::loadMultiple($ids);

      foreach ($users as $user) {
        if ($user instanceof User) {
          return $user;
        }

      }
    }

    return FALSE;
  }


  public function getUidByName($name) {
    return \Drupal::entityQuery('user')
      ->accessCheck()
      ->condition('name', Database::getConnection()->escapeLike($name), 'LIKE')
      ->execute();
  }

  /**
  /**
   * @param \Drupal\gigya\CmsStarterKit\user\GigyaUser $gigyaUser
   * @param int $uid
   *
   * @return string | false
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

  /**
   * @return object|null
   */
  public function getFieldMappingConfig() {
    $config = json_decode(\Drupal::config('gigya_raas.fieldmapping')
      ->get('gigya.fieldmapping_config') ?? '');

    if (empty($config) or empty(get_object_vars($config))) {
      $config = (object) \Drupal::config('gigya.global')
        ->get('gigya.fieldMapping');
    }

    return $config;
  }

  /**
   * This function enriches the Drupal user with Gigya data, but it does not
   * permanently save the user data!
   *
   * @param \Drupal\gigya\CmsStarterKit\user\GigyaUser $gigya_data
   * @param \Drupal\user\UserInterface $drupal_user
   */
  public function processFieldMapping(GigyaUser $gigya_data, UserInterface $drupal_user) {
    try {
      $field_map = $this->getFieldMappingConfig();
      try {
        \Drupal::moduleHandler()
               ->alter('gigya_raas_map_data', $gigya_data, $drupal_user, $field_map);
      } catch (\Exception $e) {
        \Drupal::logger('gigya_raas')
               ->debug('Error altering field map data: @message',
                 ['@message' => $e->getMessage()]);
      }

      if (!is_object($field_map)) {
        \Drupal::logger('gigya_raas')
               ->error('Error processing field map data: incorrect format entered. The format for field mapping is a JSON object of the form: &#123;"drupalField": "gigyaField"&#125;. Proceeding with default field mapping configuration.');
        $field_map = json_decode('{}');
      }
      $uid_mapping = \Drupal::config('gigya_raas.fieldmapping')->get('gigya.uid_mapping');

      if (!empty($uid_mapping)) {

        if ($drupal_user->hasField($uid_mapping)) {

          $field_map->{$uid_mapping} = 'UID';
        }

      }
      else {
        $field_map->uuid = 'UID';
      }
      foreach ($field_map as $drupal_field => $raas_field) {
        /* Drupal fields to exclude even if configured in field mapping schema */
        if ($drupal_field == 'mail' or $drupal_field == 'name') {
          continue;
        }

        /* Field names must be strings. This protects against basic incorrect formatting, though care should be taken */
        if (gettype($drupal_field) !== 'string' or gettype($raas_field) !== 'string') {
          continue;
        }

        $raas_field_parts = explode('.', $raas_field);
        $val = $this->gigya_helper->getNestedValue($gigya_data, $raas_field_parts);

        if ($val !== NULL) {
          $drupal_field_type = 'string';

          try {
            $drupal_field_type = $drupal_user->get($drupal_field)->getFieldDefinition()->getType();
          }
          catch (\Exception $e) {
            \Drupal::logger('gigya')->debug('Error getting field definition for field map: @message',
            ['@message' => $e->getMessage()]);
          }

          /* Handles Boolean types */
          if ($drupal_field_type == 'boolean') {
            if (is_bool($val)) {
              $val = intval($val);
            }
            else {
              \Drupal::logger('gigya_raas')->error('Failed to map ' . $drupal_field . ' from Gigya - Drupal type is boolean but Gigya type isn\'t');
            }
          }

          /* Perform the mapping from Gigya to Drupal */
          try {
            $drupal_user->set($drupal_field, $val);
          }
          catch (\InvalidArgumentException $e) {
            \Drupal::logger('gigya_raas')
              ->debug('Error inserting mapped field: @message',
             ['@message' => $e->getMessage()]);
          }
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('gigya_raas')->debug('processFieldMapping error @message',
      ['@message' => $e->getMessage()]);
    }
  }

  /**
   * Queries Gigya with the accounts.search call.
   *
   * @param string|array $query
   *   The literal query to send to accounts.search,
   *   or a set of params to send instead (useful for cursors)
   * @param bool $use_cursor
   *
   * @return \Drupal\gigya\CmsStarterKit\user\GigyaUser[]
   *
   * @throws \Drupal\gigya\CmsStarterKit\GSApiException
   * @throws \Gigya\PHP\GSException
   */
  public function searchGigyaUsers($query, $use_cursor = FALSE) {
    $api_helper = $this->gigya_helper->getGigyaApiHelper();

    return $api_helper->searchGigyaUsers($query, $use_cursor);
  }

  public function getGigyaUserFromArray($data) {
    return GigyaUserFactory::createGigyaProfileFromArray($data);
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

    return $this->gigya_helper->sendEmail('Gigya cron job of type ' . $job_type . ' ' . $job_status . ' on website ' . \Drupal::request()
      ->getHost(),
      $email_body,
      $to);
  }

  /**
   * @return array that contain error code key and error case key
   */
  public function validateUBCCookie() {

    $compare_option_results = [
      0 => ['errorCode' => 0, 'errorMessage' => "Valid session"],
      1 => [
        'errorCode'    => 1,
        'errorMessage' => "There was an error validating the session, the gubc cookie didn't exist. This session will validate via Gigya",
      ],
      2 => [
        'errorCode'    => 2,
        'errorMessage' => "There was an error validating the session, the glt cookie didn't exist. This session is closing",
      ],
      3 => [
        'errorCode'    => 3,
        'errorMessage' => "The gubc cookie and the glt cookie were empty",
      ],
      4 => [
        'errorCode'    => 4,
        'errorMessage' => "There was an error validating the session, the gubc cookie wasn't compatible with glt cookie. This session is closing",
      ],
    ];
    $result                 = $compare_option_results[0];
    $current_user           = \Drupal::currentUser();

    if ('until_browser_close' === \Drupal::config('gigya_raas.settings')
      ->get('gigya_raas.session_type') && $current_user->isAuthenticated() && !$current_user->hasPermission('bypass gigya raas')) {

      $gigya_conf       = \Drupal::config('gigya.settings');
      $api_key          = $gigya_conf->get('gigya.gigya_api_key');
      $gigya_ubc_cookie = \Drupal::request()->cookies->get('gubc_' . $api_key);
      $glt_cookie       = \Drupal::request()->cookies->get('glt_' . $api_key);

      /*Do if there is glt cookie*/
      if (!empty($glt_cookie)) {
        $glt_token = explode('|', $glt_cookie)[0];

        /*Do if there is ubc cookie*/
        if (!empty($gigya_ubc_cookie)) {
          $gubc_token = explode('|', $gigya_ubc_cookie)[0];

          /*if both  cookies got the same value, the session is valid. otherwise, there was something malicious so do logout*/
          if (!empty($glt_token) and !empty($gubc_token)) {

            if ($glt_token !== $gubc_token) {

              user_logout();
              $result = $compare_option_results[4];
              \Drupal::logger("gigya_raas")->debug($result['errorMessage']);
            }
            /*Do while at least one of the cookie is empty*/
          }
          else {
            user_logout();
            $result = $compare_option_results[4];
            \Drupal::logger("gigya_raas")->debug($result['errorMessage']);
          }
          /*Do if there is no ubc cookie*/
        }
        else {
          $result = $compare_option_results[1];
          \Drupal::logger("gigya_raas")->debug($result['errorMessage']);
        }
        /*do if glt cookie is empty*/
      }
      else {
        if (empty($gigya_ubc_cookie)) {

          $result = $compare_option_results[3];
        }
        else {
          $result = $compare_option_results[2];
        }
        // In any case that user doesn't have 'glt' cookie he will logged out automatically.
        user_logout();
      }
    }

    return $result;
  }

  public function gigyaRaasExtCookie(Request $request, $login = FALSE) {
    if ($this->shouldAddExtCookie($request, $login)) {
      /* Retrieve config from Drupal */
      $helper       = new GigyaHelper();
      $gigya_conf   = \Drupal::config('gigya.settings');
      $session_time = \Drupal::config('gigya_raas.settings')
        ->get('gigya_raas.session_time');
      $api_key      = $gigya_conf->get('gigya.gigya_api_key');
      $app_key      = $gigya_conf->get('gigya.gigya_application_key');
      $auth_mode    = $gigya_conf->get('gigya.gigya_auth_mode');
      $auth_key     = $helper->decrypt(($auth_mode === 'user_rsa') ? $gigya_conf->get('gigya.gigya_rsa_private_key') : $gigya_conf->get('gigya.gigya_application_secret_key'));

      $token              = $this->getGigyaLoginToken($request);
      $now                = \Drupal::time()->getRequestTime();;
      $session_expiration = strval($now + $session_time);
      $gltexp_cookie      = $request->cookies->get('gltexp_' . $api_key);

      if (!empty($gltexp_cookie)) {
        if ($auth_mode === 'user_rsa') {
          $claims = json_decode(JWT::urlsafeB64Decode(explode('.', $gltexp_cookie)[1]) ??  '', TRUE, 512, JSON_BIGINT_AS_STRING);
          if (!empty($claims) && !empty($claims['exp'])) {
            $gltexp_cookie_timestamp = $claims['exp'];
          }
        }
        else {
          $gltexp_cookie_timestamp = explode('_', $gltexp_cookie)[0];
        }
      }

      if (empty($gltexp_cookie_timestamp) or (time() < $gltexp_cookie_timestamp)) {
        if (!empty($token)) {
          if ($auth_mode === 'user_rsa') {
            $session_sig = $this->calculateDynamicSessionSignatureJwtSigned($token, $session_expiration, $app_key, $auth_key);
          }
          else {
            $session_sig = $this->getDynamicSessionSignatureUserSigned($token, $session_expiration, $app_key, $auth_key);
          }
          setrawcookie('gltexp_' . $api_key, rawurlencode($session_sig), time() + (10 * 365 * 24 * 60 * 60), '/', $request->getHost(), $request->isSecure());
        }
      }
    }

    return new AjaxResponse();
  }

  /**
   * @param $request
   * @param $login
   *
   * @return bool
   */
  public function shouldAddExtCookie($request, $login) {
    if ("dynamic" != \Drupal::config('gigya_raas.settings')
      ->get('gigya_raas.session_type')) {
      return FALSE;
    }

    if ($login) {
      return TRUE;
    }

    $current_user = \Drupal::currentUser();
    if ($current_user->isAuthenticated() && !$current_user->hasPermission('bypass gigya raas')) {
      $gigya_conf    = \Drupal::config('gigya.settings');
      $api_key       = $gigya_conf->get('gigya.gigya_api_key');
      $gltexp_cookie = $request->cookies->get('gltexp_' . $api_key);
      return !empty($gltexp_cookie);
    }

    return TRUE;
  }

  private function getDynamicSessionSignatureUserSigned($token, $expiration, $userKey, $secret) {
    $unsignedExpString = mb_convert_encoding($token . "_" . $expiration . "_" . $userKey, 'UTF-8', 'ISO-8859-1');
    $rawHmac           = hash_hmac("sha1", mb_convert_encoding($unsignedExpString, 'UTF-8', 'ISO-8859-1'), base64_decode($secret), TRUE);
    $sig               = base64_encode($rawHmac);

    return $expiration . '_' . $userKey . '_' . $sig;
  }

  protected function calculateDynamicSessionSignatureJwtSigned(string $loginToken, int $expiration, string $applicationKey, string $privateKey) {
    $payload = [
      'sub' => $loginToken,
      'iat' => time(),
      'exp' => intval($expiration),
      'aud' => 'gltexp',
    ];
    return JWT::encode($payload, $privateKey, 'RS256', $applicationKey);
  }

  /**
   * Process gigya dynamic cookie request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request object.
   *
   * @return string|null
   *   The Ajax response
   */
  public function getGigyaLoginToken(Request $request) {
    $gigya_conf = \Drupal::config('gigya.settings');
    $api_key    = $gigya_conf->get('gigya.gigya_api_key');
    $glt_cookie = $request->cookies->get('glt_' . $api_key);

    if (empty($glt_cookie)) {
      return NULL;
    }

    return (!empty(explode('|', $glt_cookie)[0])) ? explode('|', $glt_cookie)[0] : NULL;
  }

}
