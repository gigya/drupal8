<?php
	/**
	 * @file
	 * Contains \Drupal\gigya\GigyaController.
	 */

	namespace Drupal\gigya_raas;

	use Drupal;
	use Drupal\Core\Ajax\AjaxResponse;
	use Drupal\Core\Ajax\AlertCommand;
	use Drupal\Core\Ajax\InvokeCommand;
	use Drupal\Core\Controller\ControllerBase;
	use Drupal\gigya\CmsStarterKit\user\GigyaUser;
	use Drupal\gigya_raas\Helper\GigyaRaasHelper;
	use Drupal\user\Entity\User;
	use Drupal\user\UserInterface;
	use Exception;
	use Firebase\JWT\JWT;
	use Symfony\Component\HttpFoundation\Request;
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

    /** @var GigyaRaasHelper */
    protected $helper;

    /** @var GigyaHelper */
    protected $gigya_helper;

    protected $auth_mode;

    /**
     * Construct method.
     *
     * @param bool $helper
     */
    public function __construct($helper = FALSE) {
      if ($helper === FALSE) {
        $this->helper = new GigyaRaasHelper();
        $this->gigya_helper = new GigyaHelper();
      }
      else {
        $this->gigya_helper = $helper;
      }

      $gigya_conf = Drupal::config('gigya.settings');
      $this->auth_mode = $gigya_conf->get('gigya.gigya_auth_mode');
    }

    /**
     * Process Gigya RaaS login.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The incoming request object.
     *
     * @return Drupal\Core\Ajax\AjaxResponse
     *   The Ajax response
     *
     * @throws Drupal\Core\Entity\EntityStorageException
     */
    public function gigyaRaasProfileAjax(Request $request) {
      $gigya_data = $request->get('gigyaData');

      if (!empty($gigya_data['id_token']) && $this->auth_mode === 'user_rsa') {
        $signature = $gigya_data['id_token'];
      }
      else {
        $signature = $gigya_data['UIDSignature'];
      }
      $gigyaUser = $this->helper->validateAndFetchRaasUser($gigya_data['UID'], $signature, $gigya_data['signatureTimestamp']);
      if ($gigyaUser) {
        if ($user = $this->helper->getDrupalUidByGigyaUid($gigyaUser->getUID())) {
          if ($unique_email = $this->helper->checkEmailsUniqueness($gigyaUser, $user->id())) {
            if ($user->mail !== $unique_email) {
              $user->setEmail($unique_email);
              $user->save();
            }
          }
          $this->helper->processFieldMapping($gigyaUser, $user);
          Drupal::moduleHandler()->alter('gigya_profile_update', $gigyaUser, $user);
          $user->save();
        }
      }

      return new AjaxResponse();
    }

    /**
     * Raw method for processing field mapping. Currently an alias for gigyaRaasProfileAjax, but could be modified in the future.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return Drupal\Core\Ajax\AjaxResponse
     * @throws Drupal\Core\Entity\EntityStorageException
     */
    public function gigyaRaasProcessFieldMapping(Request $request) {
      return $this->gigyaRaasProfileAjax($request);
    }

		/**
		 * @param Request $request      The incoming request object.
		 *
		 * @return bool|AjaxResponse    The Ajax response
		 *
		 * @throws Drupal\Core\Entity\EntityStorageException
		 */
		public function gigyaRaasLoginAjax(Request $request) {
      $is_session_validation_process = $request->get('is_session_validation_process');

      if (Drupal::currentUser()->isAnonymous() || $is_session_validation_process) {

				global $raas_login;
				$err_msg = FALSE;
				$sig_timestamp = $request->get('sig_timestamp');
				$guid = $request->get('uid');
				$uid_sig = $request->get('uid_sig');
				$id_token = $request->get('id_token');

        if (!$is_session_validation_process) {
          $session_type = ($request->get('remember') == 'true') ? 'remember_me' : 'regular';
        }

        $login_redirect = \Drupal::config('gigya_raas.settings')->get('gigya_raas.login_redirect') ?: '/';

        if ($destination = \Drupal::request()->query->get('destination')) {
          $login_redirect = urldecode($destination);
        }

				$logout_redirect = Drupal::config('gigya_raas.settings')->get('gigya_raas.logout_redirect');

        $base_path = base_path();
        $redirect_path = ($base_path === '/') ? '/' : $base_path . '/';
        if (substr($login_redirect, 0, 4) !== 'http') {
          $login_redirect = $redirect_path . $login_redirect;
        }
        if (substr($logout_redirect, 0, 4) !== 'http') {
          $logout_redirect = $redirect_path . $logout_redirect;
        }

        $response = new AjaxResponse();

        /* Checks whether the received UID is the correct UID at Gigya */
        $auth_mode = Drupal::config('gigya.settings')->get('gigya.gigya_auth_mode') ?? 'user_secret';
        $signature = ($auth_mode == 'user_rsa') ? $id_token : $uid_sig;
        /** @var GigyaUser $gigyaUser */
        $gigyaUser = $this->helper->validateAndFetchRaasUser($guid, $signature, $sig_timestamp);
        if ($gigyaUser) {
          $userEmails = $gigyaUser->getAllVerifiedEmails();



          /* loginIDs.emails and emails.verified is missing in Gigya */
          if (empty($userEmails)) {
            if (!$is_session_validation_process) {

              $err_msg = $this->t(
                'Email address is required by Drupal and is missing, please contact the site administrator.');
              $logger_message = [
                'type' => 'gigya_raas',
                'message' => 'Email address is required by Drupal and is missing, The user asked to notice the admin.',
              ];
              $this->noticeUserAndAdminByLoginIssue($response, $logger_message, $err_msg);

              $this->gigya_helper->saveUserLogoutCookie();
            }else {

              $logger_message = [
                'type' => 'gigya_raas',
                'message' => 'Email address is required by Drupal and is missing,Probably the email has been deleted.'
              ];

              $this->writeErrorValidationMessageToLoggerAndLogout($logger_message);
            }

          }
          /* loginIDs.emails or emails.verified is found in Gigya */
          else {

            /** @var UserInterface $user */
            $user = $this->helper->getDrupalUidByGigyaUid($gigyaUser->getUID());
            if ($user) {
              /* if a user has the a permission of bypass gigya raas (admin user)
               *  they can't login via gigya
               */
              if ($user->hasPermission('bypass gigya raas')) {

                if ($is_session_validation_process) {
                  $logger_message = ['type' => 'gigya_raas', 'message' => 'Apparently someone trying to steal permission of admin user. the user email: '.$user->getEmail()];

                  $this->writeErrorValidationMessageToLoggerAndLogout($logger_message);
                }
                else {


                  $logger_message = [
                    'type' => 'gigya_raas',
                    'message' => 'User with email ' . $user->getEmail()
                      . 'that has "bypass gigya raas" permission tried to login via gigya',
                  ];
                  $err_msg = $this->t(
                    'Oops! Something went wrong during your login/registration process. Please try to login/register again.'
                  );
                  $this->gigya_helper->saveUserLogoutCookie();
                  $this->noticeUserAndAdminByLoginIssue($response, $logger_message, $err_msg);

                }
                return $response;

              }

              if ($unique_email = $this->helper->checkEmailsUniqueness($gigyaUser, $user->id())) {

                if ($user->getEmail() !== $unique_email) {
                  $user->setEmail($unique_email);
                  $user->save();

                }
              }
              else {
                $this->gigya_helper->saveUserLogoutCookie();
                $err_msg = $this->t("Email already exists");
                $response->addCommand(new AlertCommand($err_msg));
                return $response;
              }

              /* Set global variable so we would know the user as logged in
                 RaaS in other functions down the line.*/
              $raas_login = TRUE;

              /* Log the user in */
              $this->helper->processFieldMapping($gigyaUser, $user);

              $session_exp_type = Drupal::config('gigya_raas.settings')
                ->get('gigya_raas.session_type');

              if ($session_exp_type == 'until_browser_close') {/*Handle until browser close session*/

                $this->gigyaRaasCreateUbcCookie($request, $raas_login);

              }
              else if ($this->shouldAddExtCookie($request, $raas_login)) { /*Handle dynamic session*/

                  $this->gigyaRaasExtCookieAjax($request, $raas_login);
                }

              $user->save();
              user_login_finalize($user);

              if (!$is_session_validation_process) {

                /* Set user session */
                $this->gigyaRaasSetLoginSession($session_type);
              }

            }elseif (!$is_session_validation_process) /* User does not exist - register */ {

              $uids = $this->helper->getUidByMails($userEmails);
              if (!empty($uids)) {
                Drupal::logger('gigya_raas')->warning(
                  "User with uid " . $guid . " that already exists tried to register via gigya"
                );
                $this->gigya_helper->saveUserLogoutCookie();
                $err_msg = $this->t(
                  "Oops! Something went wrong during your login/registration process. Please try to login/register again."
                );
                $response->addCommand(new AlertCommand($err_msg));
                return $response;
              }
              if ($unique_email = $this->helper->checkEmailsUniqueness($gigyaUser, 0)) {
                $email = $unique_email;
              }
              else {
                $this->gigya_helper->saveUserLogoutCookie();
                $err_msg = $this->t("Email already exists");
                $response->addCommand(new AlertCommand($err_msg));
                return $response;
              }
              $gigya_user_name = $gigyaUser->getProfile()->getUsername();
              $uname = !empty($gigya_user_name) ? $gigyaUser->getProfile()->getUsername()
                : $gigyaUser->getProfile()->getFirstName();
              if (!$this->helper->getUidByName($uname)) {
                $username = $uname;
              }
              else {
                /* If user name is taken use first name if it is not empty. */
                $gigya_firstname = $gigyaUser->getProfile()->getFirstName();
                if (!empty($gigya_firstname)
                  && (!$this->helper->getUidByName(
                    $gigyaUser->getProfile()->getFirstName()
                  ))
                ) {
                  $username = $gigyaUser->getProfile()->getFirstName();
                }
                else {
                  /* When all fails add unique id  to the username so we could register the user. */
                  $username = $uname . '_' . uniqid();
                }
              }

              $user = User::create(
                [
                  'name' => $username,
                  'pass' => Drupal::hasService('password_generator') ? Drupal::service('password_generator')->generate(32) : user_password(),
                  'status' => 1,
                  'mail' => $email
                ]
              );
              $user->save();
              $this->helper->processFieldMapping($gigyaUser, $user);

              /* Allow other modules to modify the data before user is created in the Drupal database (create user hook). */
              Drupal::moduleHandler()->alter('gigya_raas_create_user', $gigyaUser, $user);
              try {
                $user->save();
                $raas_login = TRUE;
                $this->gigyaRaasExtCookieAjax($request, $raas_login);
                user_login_finalize($user);

                if (!$is_session_validation_process) {
                  /* Set user session */
                  $this->gigyaRaasSetLoginSession($session_type);
                }
              } catch (Exception $e) {
                if ($is_session_validation_process) {
                  $logger_message = ['type' => 'gigya_raas', 'message' => 'can not save the user.'];
                  $this->writeErrorValidationMessageToLoggerAndLogout($logger_message);
                }
                else {
                  $logger_message = [
                    'type' => 'gigya_raas',
                    'message' => 'User with username: ' . $username . ' could not log in after registration. Exception: ' . $e->getMessage(),
                  ];
                  $err_msg = $this->t(
                    "Oops! Something went wrong during your registration process. You are registered to the site but not logged-in. Please try to login again."
                  );
                  session_destroy();
                  $this->noticeUserAndAdminByLoginIssue($response, $logger_message, $err_msg);
                  $this->gigya_helper->saveUserLogoutCookie();

                  /* Post-logout redirect hook */
                  Drupal::moduleHandler()->alter('gigya_post_logout_redirect', $logout_redirect);
                  $response->addCommand(new InvokeCommand(NULL, 'logoutRedirect', [$logout_redirect]));
                }
              }
            }
            else { /* Validation flow – user had already been validated, but suddenly isn't found in Drupal – possibly deleted */
              $logger_message = ['type' => 'gigya_raas', 'message' => 'User had already been validate, probably this user was deleted.'];
              $this->writeErrorValidationMessageToLoggerAndLogout($logger_message);
            }
          }
        }
        else { /* No valid Gigya user found */
          if (!$is_session_validation_process) {
            $this->gigya_helper->saveUserLogoutCookie();
            $logger_message = ['type' => 'gigya_raas', 'message' => 'Invalid user. Guid: ' . $guid];
            $err_msg = $this->t(
              "Oops! Something went wrong during your login/registration process. Please try to login/register again."
            );
            $this->noticeUserAndAdminByLoginIssue($response, $logger_message, $err_msg);
          }
          else {
            $logger_message = ['type' => 'gigya_raas', 'message' => 'Invalid user try to get session.'];
            $this->writeErrorValidationMessageToLoggerAndLogout($logger_message);
          }
        }

        if ($err_msg === FALSE) {
          /* Post-login redirect hook */
          if(!$is_session_validation_process) {
            Drupal::moduleHandler()->alter('gigya_post_login_redirect', $login_redirect);
            $response->addCommand(new InvokeCommand(NULL, 'loginRedirect', [$login_redirect]));
          }
        }

        return $response;
      }
      return FALSE;
    }

    /**
     * @param Request $request The incoming request object.
     *
     * @return bool|AjaxResponse    The Ajax response
     */
    public function gigyaRaasLogoutAjax(Request $request) {
      $logout_redirect = Drupal::config('gigya_raas.settings')->get('gigya_raas.logout_redirect');

      /* Log out user in SSO */
      if (!empty(Drupal::currentUser()->id())) {
        user_logout();
      }

      $base_path = base_path();
      $redirect_path = ($base_path === '/') ? '/' : $base_path . '/';
      if (substr($logout_redirect, 0, 4) !== 'http') {
        $logout_redirect = $redirect_path . $logout_redirect;
      }

      $response = new AjaxResponse();

      /* Post-logout redirect hook */
      Drupal::moduleHandler()->alter('gigya_post_logout_redirect', $logout_redirect);
      $response->addCommand(new InvokeCommand(NULL, 'logoutRedirect', [$logout_redirect]));

      return $response;
    }

    /**
     * Sets the user $_SESSION with the expiration timestamp, derived from the session time in the RaaS configuration.
     * This is only step 1 of the process; in GigyaRaasEventSubscriber, it is supposed to take the $_SESSION and put it in the DB.
     *
     * @param string $type Whether the session is a 'regular' session, or a 'remember_me' session
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

      };

      /*
       * The session details are written to the Drupal DB only after a Kernel event (KernelEvents::REQUEST) of AuthenticationSubscriber.
       * This means that the session in Drupal isn't yet registered when this code is run, and therefore it isn't possible to update it in the DB.
       * This $_SESSION var is therefore set in order to manipulate the session on the next request, which should be run after AuthenticationSubscriber.
       * */
      Drupal::service('tempstore.private')->get('gigya_raas')->set('session_registered', FALSE);
    }

    /**
     * Sets $_SESSION['session_expiration']
     *
     * @param int $session_expiration
     * @param bool $is_remember_me
     */
    public function gigyaRaasSetSession(int $session_expiration, bool $is_remember_me) { /* PHP 7.0+ */
      Drupal::service('tempstore.private')->get('gigya_raas')->set('session_expiration', $session_expiration);
      Drupal::service('tempstore.private')->get('gigya_raas')->set('session_is_remember_me', $is_remember_me);
    }

    /**
     * Process gigya dynamic cookie request.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The incoming request object.
     * @param boolean $login
     *
     * @return Drupal\Core\Ajax\AjaxResponse
     *   The Ajax response
     */
    public function gigyaRaasExtCookieAjax(Request $request, $login = FALSE) {
      if ($this->shouldAddExtCookie($request, $login)) {
        /* Retrieve config from Drupal */
        $helper = new GigyaHelper();
        $gigya_conf = Drupal::config('gigya.settings');
        $session_time = Drupal::config('gigya_raas.settings')->get('gigya_raas.session_time');
        $api_key = $gigya_conf->get('gigya.gigya_api_key');
        $app_key = $gigya_conf->get('gigya.gigya_application_key');
        $auth_mode = $gigya_conf->get('gigya.gigya_auth_mode');
        $auth_key = $helper->decrypt(($auth_mode === 'user_rsa') ? $gigya_conf->get('gigya.gigya_rsa_private_key') : $gigya_conf->get('gigya.gigya_application_secret_key'));

        $token = $this->getGigyaLoginToken($request);
        $now = $_SERVER['REQUEST_TIME'];
        $session_expiration = strval($now + $session_time);
        $gltexp_cookie = $request->cookies->get('gltexp_' . $api_key);

        if (!empty($gltexp_cookie)) {
          if ($auth_mode === 'user_rsa') {
            $claims = json_decode(JWT::urlsafeB64Decode(explode('.', $gltexp_cookie)[1]), TRUE, 512, JSON_BIGINT_AS_STRING);
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
      if ("dynamic" != Drupal::config('gigya_raas.settings')->get('gigya_raas.session_type')) {
        return FALSE;
      }

      if ($login) {
        return TRUE;
      }

      $current_user = Drupal::currentUser();
      if ($current_user->isAuthenticated() && !$current_user->hasPermission('bypass gigya raas')) {
        $gigya_conf = Drupal::config('gigya.settings');
        $api_key = $gigya_conf->get('gigya.gigya_api_key');
        $gltexp_cookie = $request->cookies->get('gltexp_' . $api_key);
        return !empty($gltexp_cookie);
      }

      return TRUE;
    }

    private function getDynamicSessionSignatureUserSigned($token, $expiration, $userKey, $secret) {
      $unsignedExpString = utf8_encode($token . "_" . $expiration . "_" . $userKey);
      $rawHmac = hash_hmac("sha1", utf8_encode($unsignedExpString), base64_decode($secret), TRUE);
      $sig = base64_encode($rawHmac);

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

    public function getGigyaLoginToken(Request $request) {

      $gigya_conf = Drupal::config('gigya.settings');
      $api_key = $gigya_conf->get('gigya.gigya_api_key');
      $glt_cookie = $request->cookies->get('glt_' . $api_key);
      return (!empty(explode('|', $glt_cookie)[0])) ? explode('|', $glt_cookie)[0] : NULL;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request|null $request
     * @param false $login
     *
     * @return \Drupal\Core\Ajax\AjaxResponse
     */
    public function gigyaRaasCreateUBCCookie(Request $request = NULL, $login = FALSE): AjaxResponse {

      if ('until_browser_close' === Drupal::config('gigya_raas.settings')
          ->get('gigya_raas.session_type')) {

        /* Retrieve config from Drupal */
        $gigya_conf = Drupal::config('gigya.settings');
        $api_key = $gigya_conf->get('gigya.gigya_api_key');

        if ($request == NULL) {
          $request = Drupal::request();
        }
        $token = $this->getGigyaLoginToken($request);

        if (!empty($token)) {

          setrawcookie('gubc_' . $api_key, $token, 0, '/', $request->getHost(), $request->isSecure(), TRUE);
        }
      }

      return new AjaxResponse();
    }


    protected function noticeUserAndAdminByLoginIssue($response, $logger_message, $err_msg) {


      Drupal::logger($logger_message['type'])->notice($logger_message['message']);
      $response->addCommand(new AlertCommand($err_msg));

    }

    protected function writeErrorValidationMessageToLoggerAndLogout($logger_message) {
      Drupal::logger($logger_message['type'])->warning($logger_message['message']);
      user_logout();
    }


  }

