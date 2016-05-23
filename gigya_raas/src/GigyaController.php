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
use Gigya\user\GigyaUserFactory;
use Symfony\Component\HttpFoundation\Request;
use Drupal\gigya\Helper\GigyaHelper;

/**
 * Returns responses for Editor module routes.
 */
class GigyaController extends ControllerBase {

  /**
   * Process gigya raas login.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request object.
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response
   */
  public function gigyaRaasProfileAjax(Request $request) {
    $gigyaProfile = GigyaHelper::getGigyaUserFromArray($request->get('gigyaProfile'));
    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    GigyaHelper::processFieldMapping($gigyaProfile, $user, TRUE);
    $user->save();
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

      $err_msg = FALSE;
      $sig_timestamp = $request->get('sig_timestamp');
      $guid = $request->get('uid');
      $uid_sig = $request->get('uid_sig');

      $response = new AjaxResponse();

      if ($gigyaUser = GigyaHelper::validateUid($guid, $uid_sig, $sig_timestamp)) {
        $email = $gigyaUser->getProfile()->getEmail();
        if (empty($email)) {
          $err_msg = $this->t('Email address is required by Drupal and is missing, please contact the site administrator.');
        }
        else {
          if ($uids = GigyaHelper::getUidByMail($email)) {
            if ($gigyaUser->isRaasPrimaryUser($email)) {
              /* Set global variable so we would know the user as logged in
                 RaaS in other functions down the line.*/
              global $rass_login;
              $rass_login = true;

              //Log the user in.
              $user = User::load(array_shift($uids));
              GigyaHelper::processFieldMapping($gigyaUser, $user);
              $user->save();
              user_login_finalize($user);

            }
            else {
              /**
               * If this user is not the primary user account in gigya we disable the account.
               * (we don't want two different users with the same email)
               */
              GigyaHelper::sendApiCall('accounts.setAccountInfo', array('UID' => $gigyaUser->getUID(), 'isActive' => FALSE));
              $err_msg = $this->t('We found your email in our system.<br />Please use your existing account to login to the site, or create a new account using a different email address.');
            }
          }
          else {
            $user = User::create(array('name' => $email, 'pass' => user_password(), 'status' => 1));


            GigyaHelper::processFieldMapping($gigyaUser, $user);
            /* Allow other modules to modify the data before user
            is created in drupal database. */

            \Drupal::moduleHandler()->alter('gigya_raas_create_user', $gigya_account, $new_user);
            \Drupal::service('user.private_tempstore')->get('gigya')->set('gigya_raas_uid', $guid);
            try {
              $user->save();
              user_login_finalize($user);
            } catch (Exception $e) {
              session_destroy();
              $err_msg = $this->t("Oops! Something went wrong during your registration process. You are registered to the site but
            not logged-in. Please try to login again.");
              user_cookie_save(array('gigya' => 'gigyaLogOut'));
              $response->addCommand(new RedirectCommand("/"));
            }
          }
        }
      }
      else {
        GigyaHelper::saveUserLogoutCookie();
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