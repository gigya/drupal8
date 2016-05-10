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

    $err_msg = false;
    $sig_timestamp = $request->get('sig_timestamp');
    $uid = $request->get('uid');
    $uid_sig = $request->get('uid_sig');

    $response = new AjaxResponse();
    if ($uid = GigyaHelper::validateUid($uid, $uid_sig, $sig_timestamp)) {
      dpm($uid);
    }
    else {
      dpm('false');
      dpm($uid);
      $err_msg = "msg";
    }
    if ($err_msg !== FALSE) {
      $response->addCommand(new AlertCommand($err_msg));
    }
    else {
      $response->addCommand(new RedirectCommand(\Drupal::service('path.current')->getPath()));
    }
    return $response;
  }

}