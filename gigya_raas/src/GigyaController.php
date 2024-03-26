<?php

namespace Drupal\gigya_raas;
use Drupal;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\gigya_raas\Helper\GigyaRaasHelper;
use Drupal\user\Entity\User;
use Firebase\JWT\JWT;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\gigya\Helper\GigyaHelper;

define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS);
define('DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS);
define('WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS);
define('MONTH_IN_SECONDS', 30 * DAY_IN_SECONDS);
define('YEAR_IN_SECONDS', 365 * DAY_IN_SECONDS);

/**
 * Returns responses for Editor module routes.
 */
class GigyaController extends ControllerBase {

  /**
   * @var \Drupal\gigya_raas\Helper\GigyaRaasHelper
   */
  protected $helper;

  /**
   * @var \Drupal\gigya\Helper\GigyaHelper
   */
  protected $gigya_helper;

  protected $auth_mode;

  /**
   * Construct method.
   *
   * @param GigyaRaasHelper|NULL $helper
   * @param GigyaHelper|NULL $raas_helper
   */
  public function __construct(#[Autowire(service: 'gigya_raas.helper')] GigyaRaasHelper $helper = NULL, #[Autowire(service: 'gigya.helper')] GigyaHelper $raas_helper = NULL) {
    $this->helper = $helper ?? new GigyaRaasHelper();
    $this->gigya_helper = $raas_helper ?? new GigyaHelper();
    $gigya_conf = \Drupal::config('gigya.settings');
    $this->auth_mode = $gigya_conf->get('gigya.gigya_auth_mode');
  }

  /**
   * Process Gigya RaaS login.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request object.
   *
   * @return AjaxResponse
   *   The Ajax response
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function gigyaRaasProfileAjax(Request $request): AjaxResponse {
    $gigya_data          = $request->get('gigyaData');
    $is_dummy_email_used = \Drupal::config('gigya_raas.settings')
      ->get('gigya_raas.should_use_dummy_email');

    if (!empty($gigya_data['id_token']) && $this->auth_mode === 'user_rsa') {
      $signature = $gigya_data['id_token'];
    }
    else {
      $signature = $gigya_data['UIDSignature'];
    }
    $gigyaUser = $this->helper->validateAndFetchRaasUser($gigya_data['UID'], $signature, $gigya_data['signatureTimestamp']);
    if ($gigyaUser) {
      if ($user = $this->helper->getDrupalUserByGigyaUid($gigyaUser->getUID())) {
        if ($unique_email = $this->helper->checkEmailsUniqueness($gigyaUser, $user->id())) {
          if ($unique_email !== $user->mail) {
            $user->setEmail($unique_email);
            $user->save();
          }
        }
        $this->helper->processFieldMapping($gigyaUser, $user);
        \Drupal::moduleHandler()
               ->alter('gigya_profile_update', $gigyaUser, $user);
        $user->save();

        if (!$this->helper->checkEmailsUniqueness($gigyaUser, $user->id()) and $is_dummy_email_used) {
          $dummy_mail = $this->getDummyEmail($gigyaUser);
          if (empty($user->mail)) {
            $user->setEmail($dummy_mail);
          }
          $user->save();
        }
      }
    }

    return new AjaxResponse();
  }

  /**
   * Raw method for processing field mapping. Currently, an alias for
   * gigyaRaasProfileAjax, but could be modified in the future.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return AjaxResponse
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function gigyaRaasProcessFieldMapping(Request $request): AjaxResponse {
    return $this->gigyaRaasProfileAjax($request);
  }

  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request object.
   *
   * @return bool|AjaxResponse    The Ajax response
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function gigyaRaasLoginAjax(Request $request) {
    $should_validate_session = $request->get('is_session_validation_process');
    if (\Drupal::currentUser()
      ->isAnonymous() || $should_validate_session) {

      global $raas_login;
      $err_msg       = FALSE;
      $sig_timestamp = $request->get('sig_timestamp');
      $guid          = $request->get('uid');
      $uid_sig       = $request->get('uid_sig');
      $id_token      = $request->get('id_token');

      if (!$should_validate_session) {
        $session_type = ($request->get('remember') == 'true') ? 'remember_me' : 'regular';
      }

      $login_redirect = \Drupal::config('gigya_raas.settings')
        ->get('gigya_raas.login_redirect') ?: '/';

      if ($destination = \Drupal::request()->query->get('destination')) {
        $login_redirect = urldecode($destination);
      }

      $logout_redirect = \Drupal::config('gigya_raas.settings')
        ->get('gigya_raas.logout_redirect');

      $base_path     = base_path();
      $redirect_path = ($base_path === '/') ? '/' : $base_path . '/';
      if (substr($login_redirect, 0, 4) !== 'http') {
        $login_redirect = $redirect_path . $login_redirect;
      }
      if (substr($logout_redirect, 0, 4) !== 'http') {
        $logout_redirect = $redirect_path . $logout_redirect;
      }

      $response = new AjaxResponse();

      /* Checks whether the received UID is the correct UID at Gigya */
      $auth_mode = \Drupal::config('gigya.settings')
        ->get('gigya.gigya_auth_mode') ?? 'user_secret';
      $signature = ($auth_mode == 'user_rsa') ? $id_token : $uid_sig;
      /** @var \Drupal\gigya\CmsStarterKit\user\GigyaUser $gigyaUser */
      $gigyaUser = $this->helper->validateAndFetchRaasUser($guid, $signature, $sig_timestamp);
      if ($gigyaUser) {
        $userEmails = $gigyaUser->getAllVerifiedEmails();

        $is_dummy_email_used = \Drupal::config('gigya_raas.settings')
          ->get('gigya_raas.should_use_dummy_email');

        /* loginIDs.emails and emails.verified is missing in Gigya */
        if (empty($userEmails) and !$is_dummy_email_used) {
          if (!$should_validate_session) {
            $err_msg        = $this->t(
              'Email address is required by Drupal and is missing, please contact the site administrator.');
            $logger_message = [
              'type'    => 'gigya_raas',
              'message' => 'Email address is required by Drupal and is missing, the user asked to notify the admin.',
            ];
            $this->notifyUserAndAdminAboutLoginIssue($response, $logger_message, $err_msg);

            $this->gigya_helper->saveUserLogoutCookie();
          }
          else {
            $logger_message = [
              'type'    => 'gigya_raas',
              'message' => 'Email address is required by Drupal and is missing,Probably the email has been deleted.',
            ];

            $this->writeErrorValidationMessageToLoggerAndLogout($logger_message);
          }
        }
        /* loginIDs.emails or emails.verified is found in Gigya or using dummy email for mobile login */
        else {
          $user = $this->helper->getDrupalUserByGigyaUid($gigyaUser->getUID());

          if ($user) {
            /* if a user has the permission of bypass gigya raas (admin user)
             *  they can't log in via gigya
             */
            if ($user->hasPermission('bypass gigya raas')) {
              if ($should_validate_session) {
                $logger_message = [
                  'type'    => 'gigya_raas',
                  'message' => 'Apparently someone trying to steal permission of admin user. the user email: ' . $user->getEmail(),
                ];

                $this->writeErrorValidationMessageToLoggerAndLogout($logger_message);
              }
              else {
                $logger_message = [
                  'type'    => 'gigya_raas',
                  'message' => 'User with email ' . $user->getEmail()
                  . 'that has "bypass gigya raas" permission tried to login via gigya',
                ];
                $err_msg        = $this->t(
                  'Oops! Something went wrong during your login/registration process. Please try to login/register again.'
                );

                $this->gigya_helper->saveUserLogoutCookie();
                $this->notifyUserAndAdminAboutLoginIssue($response, $logger_message, $err_msg);
              }

              return $response;
            }

            if (empty($userEmails) and $is_dummy_email_used) {
              $unique_email = $user->getEmail();
            }
            else {
              $unique_email = $this->helper->checkEmailsUniqueness($gigyaUser, $user->id());
            }

            if (!$is_dummy_email_used and !$unique_email) {
              $this->gigya_helper->saveUserLogoutCookie();
              $err_msg = $this->t("Email already exists");
              $response->addCommand(new AlertCommand($err_msg));
              return $response;

              /* There is no need to check if a dummy email is used because the unique email will be created in case of dummy email */
            }
            elseif ($unique_email) {
              if ($user->getEmail() !== $unique_email) {
                $user->setEmail($unique_email);
                $user->save();
              }
            }

            /* Set global variable, so we would know the user as logged in
            RaaS in other functions down the line. */
            $raas_login = TRUE;

            /* Log the user in */
            $this->helper->processFieldMapping($gigyaUser, $user);

            $session_exp_type = \Drupal::config('gigya_raas.settings')
              ->get('gigya_raas.session_type');

            if ($session_exp_type == 'until_browser_close') {/*Handle until browser close session*/

              $this->gigyaRaasCreateUbcCookie($request, $raas_login);

            }
            elseif ($this->helper->shouldAddExtCookie($request, $raas_login)) {/*Handle dynamic session*/

              $this->helper->gigyaRaasExtCookie($request, $raas_login);
            }

            $user->save();
            user_login_finalize($user);
            if (!$should_validate_session) {

              /* Set user session */
              $this->gigyaRaasSetLoginSession($session_type);
            }

          }
          elseif (!$should_validate_session) {/* User does not exist - register */
            $uids = '';

            if (!empty($userEmails)) {
              $uids = $this->helper->getUidByMails($userEmails);
            }

            if (!empty($uids)) {
              \Drupal::logger('gigya_raas')->warning(
                "User with uid " . $guid . " that already exists tried to register via gigya"
              );
              $this->gigya_helper->saveUserLogoutCookie();
              $err_msg = $this->t(
                "Oops! Something went wrong during your login/registration process. Please try to login/register again."
              );
              $response->addCommand(new AlertCommand($err_msg));

              return $response;
            }

            $email = '';
            if ($unique_email = $this->helper->checkEmailsUniqueness($gigyaUser, 0)) {
              $email = $unique_email;
            }
            elseif (!$is_dummy_email_used) {
              $this->gigya_helper->saveUserLogoutCookie();
              $err_msg = $this->t("Email already exists");
              $response->addCommand(new AlertCommand($err_msg));

              return $response;
            }
            elseif ($unique_email === FALSE) {
              $email = $this->getDummyEmail($gigyaUser);
            }

            $gigya_user_name = $gigyaUser->getProfile()->getUsername();

            $uname = !empty($gigya_user_name) ? $gigyaUser->getProfile()
              ->getUsername()
              : $gigyaUser->getProfile()->getFirstName();

            if (empty($uname)) {
              $uname = $this->editPhoneNumber($gigyaUser->getPhoneNumber());
            }

            if (!$this->helper->getUidByName($uname)) {
              $username = $uname;
            }
            else {
              /* If username is taken use first name if it is not empty. */
              $gigya_firstname = $gigyaUser->getProfile()->getFirstName();

              if (!empty($gigya_firstname)
                  && (!$this->helper->getUidByName(
                  $gigyaUser->getProfile()->getFirstName()))) {

                $username = $gigyaUser->getProfile()->getFirstName();

              }
              else {
                /* When all fails add unique id  to the username so we could register the user. */
                $username = $uname . '_' . uniqid();
              }
            }

            $user = User::create(
              [
                'name'   => $username,
                'pass'   => \Drupal::hasService('password_generator') ? \Drupal::service('password_generator')
                  ->generate(32) : \Drupal::service('password_generator')
                  ->generate(),
                'status' => 1,
                'mail'   => $email,
              ]
            );
            $user->save();
            $this->helper->processFieldMapping($gigyaUser, $user);

            /* Allow other modules to modify the data before user is created in the Drupal database (create user hook). */
            \Drupal::moduleHandler()
              ->alter('gigya_raas_create_user', $gigyaUser, $user);
            try {

              $user->save();
              $raas_login = TRUE;
              $this->helper->gigyaRaasExtCookie($request, $raas_login);
              user_login_finalize($user);

              if (!$should_validate_session) {
                /* Set user session */
                $this->gigyaRaasSetLoginSession($session_type);
              }
            }
            catch (\Exception $e) {
              if ($should_validate_session) {
                $logger_message = [
                  'type'    => 'gigya_raas',
                  'message' => 'can not save the user.',
                ];
                $this->writeErrorValidationMessageToLoggerAndLogout($logger_message);
              }
              else {
                $logger_message = [
                  'type'    => 'gigya_raas',
                  'message' => 'User with username: ' . $username . ' could not log in after registration. Exception: ' . $e->getMessage(),
                ];
                $err_msg        = $this->t(
                  "Oops! Something went wrong during your registration process. You are registered to the site but not logged-in. Please try to login again."
                );
                session_destroy();
                $this->notifyUserAndAdminAboutLoginIssue($response, $logger_message, $err_msg);
                $this->gigya_helper->saveUserLogoutCookie();

                /* Post-logout redirect hook */
                \Drupal::moduleHandler()
                  ->alter('gigya_post_logout_redirect', $logout_redirect);
                $response->addCommand(new InvokeCommand(NULL, 'logoutRedirect', [$logout_redirect]));
              }
            }
          }
          else {/* Validation flow – user had already been validated, but suddenly isn't found in Drupal – possibly deleted */
            $logger_message = [
              'type'    => 'gigya_raas',
              'message' => 'User had already been validate, probably this user was deleted.',
            ];
            $this->writeErrorValidationMessageToLoggerAndLogout($logger_message);
          }
        }
      }
      else {/* No valid Gigya user found */
        if (!$should_validate_session) {
          $this->gigya_helper->saveUserLogoutCookie();
          $logger_message = [
            'type'    => 'gigya_raas',
            'message' => 'Invalid user. Guid: ' . $guid,
          ];
          $err_msg        = $this->t(
            "Oops! Something went wrong during your login/registration process. Please try to login/register again."
          );
          $this->notifyUserAndAdminAboutLoginIssue($response, $logger_message, $err_msg);
        }
        else {
          $logger_message = [
            'type'    => 'gigya_raas',
            'message' => 'Invalid user try to get session.',
          ];
          $this->writeErrorValidationMessageToLoggerAndLogout($logger_message);
        }
      }

      if ($err_msg === FALSE) {
        /* Post-login redirect hook */
        if (!$should_validate_session) {
          \Drupal::moduleHandler()
            ->alter('gigya_post_login_redirect', $login_redirect);
          $response->addCommand(new InvokeCommand(NULL, 'loginRedirect', [$login_redirect]));
        }
      }

      return $response;
    }
    return new AjaxResponse();
  }

  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request object.
   *
   * @return bool|AjaxResponse    The Ajax response
   */
  public function gigyaRaasLogoutAjax(Request $request) {
    $logout_redirect = \Drupal::config('gigya_raas.settings')
      ->get('gigya_raas.logout_redirect');

    /* Log out user in SSO */
    if (!empty(\Drupal::currentUser()->id())) {
      user_logout();
    }

    $base_path     = base_path();
    $redirect_path = ($base_path === '/') ? '/' : $base_path . '/';
    if (substr($logout_redirect, 0, 4) !== 'http') {
      $logout_redirect = $redirect_path . $logout_redirect;
    }

    $response = new AjaxResponse();

    /* Post-logout redirect hook */
    \Drupal::moduleHandler()
      ->alter('gigya_post_logout_redirect', $logout_redirect);
    $response->addCommand(new InvokeCommand(NULL, 'logoutRedirect', [$logout_redirect]));

    return $response;
  }

  /**
   * Sets the user $_SESSION with the expiration timestamp, derived from the
   * session time in the RaaS configuration. This is only step one of the
   * process; in GigyaRaasEventSubscriber, it is supposed to take the
   * $_SESSION and put it in the DB.
   *
   * @param string $type
   *   Whether the session is a 'regular' session, or a
   *   'remember_me' session.
   */
  public function gigyaRaasSetLoginSession($type = 'regular') {
    $session_params = GigyaRaasHelper::getSessionConfig($type);
    $is_remember_me = ($type == 'remember_me');

    switch ($session_params['type']) {
      case 'until_browser_close':
        $this->gigyaRaasSetSession(0, $is_remember_me);
        break;

      case 'dynamic':
        $this->gigyaRaasSetSession(-1, $is_remember_me);
        break;

      case 'forever':
        $this->gigyaRaasSetSession(time() + (10 * YEAR_IN_SECONDS), $is_remember_me);
        break;

      case 'fixed':
      default:
        $this->gigyaRaasSetSession(time() + $session_params['time'], $is_remember_me);
        break;

    }

    /*
     * The session details are written to the Drupal DB only after a Kernel event (KernelEvents::REQUEST) of AuthenticationSubscriber.
     * This means that the session in Drupal isn't yet registered when this code is run, and therefore it isn't possible to update it in the DB.
     * This $_SESSION var is therefore set in order to manipulate the session on the next request, which should be run after AuthenticationSubscriber.
     * */
    \Drupal::service('tempstore.private')
      ->get('gigya_raas')
      ->set('session_registered', FALSE);
  }

  /**
   * Sets $_SESSION['session_expiration']
   *
   * @param int $session_expiration
   * @param bool $is_remember_me
   */
  public function gigyaRaasSetSession(int $session_expiration, bool $is_remember_me) {
    /* PHP 7.0+ */
    \Drupal::service('tempstore.private')
      ->get('gigya_raas')
      ->set('session_expiration', $session_expiration);
    \Drupal::service('tempstore.private')
      ->get('gigya_raas')
      ->set('session_is_remember_me', $is_remember_me);
  }

  /**
   * Process gigya dynamic cookie request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request object.
   * @param bool $login
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response
   */
  public function gigyaRaasExtCookieAjax(Request $request, $login = FALSE) {
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

      $token              = $this->helper->getGigyaLoginToken($request);
      $now                = \Drupal::time()->getRequestTime();;
      $session_expiration = strval($now + $session_time);
      $gltexp_cookie      = $request->cookies->get('gltexp_' . $api_key);

      if (!empty($gltexp_cookie)) {
        if ($auth_mode === 'user_rsa') {
          $claims = json_decode(JWT::urlsafeB64Decode(explode('.', $gltexp_cookie)[1]) !== null ? : '', TRUE, 512, JSON_BIGINT_AS_STRING);
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
  private function shouldAddExtCookie($request, $login) {
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
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   * @param false $login
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public function gigyaRaasCreateUBCCookie(Request $request = NULL, $login = FALSE): AjaxResponse {

    if ('until_browser_close' === \Drupal::config('gigya_raas.settings')
      ->get('gigya_raas.session_type')) {

      /* Retrieve config from Drupal */
      $gigya_conf = \Drupal::config('gigya.settings');
      $api_key    = $gigya_conf->get('gigya.gigya_api_key');

      if ($request == NULL) {
        $request = \Drupal::request();
      }
      $token = $this->helper->getGigyaLoginToken($request);

      if (!empty($token)) {

        setrawcookie('gubc_' . $api_key, $token, 0, '/', $request->getHost(), $request->isSecure(), TRUE);
      }
    }

    return new AjaxResponse();
  }

  protected function notifyUserAndAdminAboutLoginIssue($response, $logger_message, $err_msg) {
    \Drupal::logger($logger_message['type'])
      ->notice($logger_message['message']);
    $response->addCommand(new AlertCommand($err_msg));

  }

  protected function writeErrorValidationMessageToLoggerAndLogout($logger_message) {
    \Drupal::logger($logger_message['type'])
      ->warning($logger_message['message']);
    user_logout();
  }

  protected function getDummyEmail($gigyaUser) {

    $typesOfValues = [
      "uid"         => '${UID}',
      'firstName'   => '${firstName}',
      "lastName"    => '${lastName}',
      "nickName"    => '${nickName}',
      "phoneNumber" => '${phoneNumber}',
    ];

    $dummy_email = \Drupal::config('gigya_raas.settings')
      ->get('gigya_raas.dummy_email_format');

    foreach ($typesOfValues as $key => $val) {
      $dummy_email = $this->findAndReplace($key, $val, $gigyaUser, $dummy_email);
    }

    return $dummy_email;

  }

  protected function findAndReplace($typeOfData, $stringToReplace, $gigyaUser, $email) {
    $dataToReplace = '';

    switch ($typeOfData) {
      case 'uid':
        $dataToReplace = $gigyaUser->getUID();
        break;

      case 'firstName':
        $dataToReplace = $gigyaUser->getProfile()->getFirstName();
        break;

      case 'lastName':
        $dataToReplace = $gigyaUser->getProfile()->getLastName();
        break;

      case 'nickName':
        $dataToReplace = $gigyaUser->getProfile()->getNickname();
        break;

      case 'phoneNumber':
        $dataToReplace = $this->editPHoneNumber($gigyaUser->getPhoneNumber());
        break;
    }

    return str_ireplace($stringToReplace, $dataToReplace, $email);
  }

  protected function editPhoneNumber($phoneNumber) {
    if (!empty($phoneNumber)) {
      return str_replace(['+', '-'], '', $phoneNumber);
    }

    return $phoneNumber;
  }

}
