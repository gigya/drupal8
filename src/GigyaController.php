<?php

/**
 * @file
 * Contains \Drupal\gigya\GigyaController.
 */

namespace Drupal\gigya;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

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
   *   The Ajax response.
   */
  public function gigyaRaasLoginAjax(Request $request) {

    $sig_timestamp = $request->get('sig_timestamp');
    $uid = $request->get('uid');
    $uid_sig = $request->get('uid_sig');

    $response = new AjaxResponse();
//    $response->addCommand(new GetUntransformedTextCommand($editable_text));

    return $response;
  }

}