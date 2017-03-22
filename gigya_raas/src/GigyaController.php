<?php

/**
 * @file
 * Contains \Drupal\gigya\GigyaController.
 */

namespace Drupal\gigya_raas;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\gigya\Helper\GigyaHelper;

/**
 * Returns responses for Editor module routes.
 */
class GigyaController extends ControllerBase {

  /** @var GigyaHelper */
  protected $helper;


  /**
   * Construct method.
   * @param bool $helper
   */
  public function __construct($helper = FALSE) {
    if ($helper == FALSE) {
      $this->helper = new GigyaHelper();
    }
    else {
      $this->helper = $helper;
    }
  }

  /**
   * Process gigya raas login.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request object.
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response
   */
  public function gigyaRaasProfileAjax(Request $request) {
    $gigya_data = $request->get('gigyaData');
    if ($gigyaUser = $this->helper->validateUid($gigya_data['UID'], $gigya_data['UIDSignature'], $gigya_data['signatureTimestamp'])) {
      if ($user = $this->helper->getUidByUUID($gigyaUser->getUID())) {
        $this->helper->processFieldMapping($gigyaUser, $user);
        \Drupal::moduleHandler()->alter('gigya_profile_update', $gigyaUser, $user);
        $user->save();
      }
    }
    return new AjaxResponse();
  }

  /**
   * Process gigya raas login.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request object.
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response
   */
  public function gigyaRaasLoginAjax(Request $request) {

    if (\Drupal::currentUser()->isAnonymous()) {
      global $raas_login;
      $err_msg = FALSE;
      $sig_timestamp = $request->get('sig_timestamp');
      $guid = $request->get('uid');
      $uid_sig = $request->get('uid_sig');

      $response = new AjaxResponse();

      if ($gigyaUser = $this->helper->validateUid($guid, $uid_sig, $sig_timestamp)) {
        $email = $gigyaUser->getProfile()->getEmail();
        if (empty($email)) {
          $err_msg = $this->t('Email address is required by Drupal and is missing, please contact the site administrator.');
          $this->helper->saveUserLogoutCookie();
        }
        else {
          /** @var UserInterface $user */
          $user = $this->helper->getUidByUUID($gigyaUser->getUID());
          if ($user) {
            /** if a user has the a permission of bypass gigya raas (admin user)
             *  they can't login via gigya
             */
            if ($user->hasPermission('bypass gigya raas')) {
              \Drupal::logger('gigya_raas')->notice("User with email " . $user->getEmail() . " that has 'bypass gigya raas' permission tried to login via gigya");
              $this->helper->saveUserLogoutCookie();
              $err_msg = $this->t("Oops! Something went wrong during your login/registration process. Please try to login/register again.");
              $response->addCommand(new AlertCommand($err_msg));
              return $response;
            }
            /* Set global variable so we would know the user as logged in
               RaaS in other functions down the line.*/
            $raas_login = true;
            //Log the user in.
            $this->helper->processFieldMapping($gigyaUser, $user);
            $user->save();
            user_login_finalize($user);
          }
          else {
            $uids = $this->helper->getUidByMail($email);
            if (!empty($uids)) {
              \Drupal::logger('gigya_raas')->notice("User with email " . $email . " that already exists tried to register via gigya");
              $this->helper->saveUserLogoutCookie();
              $err_msg = $this->t("Oops! Something went wrong during your login/registration process. Please try to login/register again.");
              $response->addCommand(new AlertCommand($err_msg));
              return $response;
            }
            $uname = !empty($gigyaUser->getProfile()->getUsername()) ? $gigyaUser->getProfile()->getUsername() : $gigyaUser->getProfile()->getFirstName();
            if (!$this->helper->getUidByName($uname)) {
              $username = $uname;
            }
            else {
              // If user name is taken use first name if it is not empty.
              if (!empty($gigyaUser->getProfile()->getFirstName()) && (!$this->helper->getUidByName($gigyaUser->getProfile()->getFirstName()))) {
                $username = $gigyaUser->getProfile()->getFirstName();
              }
              else {
                // When all fails add unique id  to the username so we could register the user.
                $username = $uname . '_' . uniqid();
              }
            }

            $user = User::create(array('name' => $username, 'pass' => user_password(), 'status' => 1));
            $user->save();
            $this->helper->processFieldMapping($gigyaUser, $user);
            $user->set('name', $username);
            /* Allow other modules to modify the data before user
            is created in drupal database. */

            \Drupal::moduleHandler()->alter('gigya_raas_create_user', $gigyaUser, $user);
            try {
              //@TODO: generate Unique user name.
              $user->save();
              $raas_login = TRUE;
              user_login_finalize($user);

            } catch (Exception $e) {
              session_destroy();
              $err_msg = $this->t("Oops! Something went wrong during your registration process. You are registered to the site but
            not logged-in. Please try to login again.");
              $this->helper->saveUserLogoutCookie();
              $response->addCommand(new RedirectCommand("/"));
            }
          }
        }
      }
      else {
        $this->helper->saveUserLogoutCookie();
        $err_msg = $this->t("Oops! Something went wrong during your login/registration process. Please try to login/register again.");
      }
      if ($err_msg !== FALSE) {

        $response->addCommand(new AlertCommand($err_msg));
      }
      else {
        $response->addCommand(new RedirectCommand("/"));
      }
      return $response;


    }


  }

}