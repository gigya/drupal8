<?php

/**
 * @file
 * Contains \Drupal\gigya\GigyaController.
 */

namespace Drupal\gigya;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Drupal\gigya\Helper\GigyaHelper;

/**
 * Returns responses for Editor module routes.
 */
class GigyaController extends ControllerBase {

  /**
   * Returns an Ajax response to render a text field without transformation filters.
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

              \Drupal::service('user.private_tempstore')->get('gigya')->set('gigya_raas_uid', $guid);;

              //Log the user in.
              $user = User::load(array_shift($uids));
              user_login_finalize($user);
              GigyaHelper::processFieldMapping($gigyaUser, $user);
              $user->save();
            }
            else {
              /**
               * If this user is not the primary user account in gigya we disable the account.
               * (we don't want two different users with the same email)
               */
              GigyaHelper::sendApiCall('accounts.setAccountInfo', array('UID' => $gigyaUser->getUID(), 'isActive' => FALSE));

//              watchdog('gigya', 'User tried to create a new account with an existing email. Please enable social link account in your RaaS policies. For more info, please refer to: http://developers.gigya.com/015_Partners/030_CMS_and_Ecommerce_Platforms/020_Drupal/010_RaaS#Gigya_Configuration', NULL, WATCHDOG_NOTICE);
              $err_msg = $this->t('We found your email in our system.<br />Please use your existing account to login to the site, or create a new account using a different email address.');

            }
          }
          else {

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