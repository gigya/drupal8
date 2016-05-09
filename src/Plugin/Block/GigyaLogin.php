<?php
/**
 * Provides a 'Gigya RaaS links' Block
 *
 * @Block(
 *   id = "gigya_login",
 *   admin_label = @Translation("Gigya Login"),
 *   category = @Translation("Gigya")
 * )
 */

namespace Drupal\gigya\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGenerator;


class GigyaLogin extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function build() {

//    $gigya_mode = variable_get('gigya_login_mode', 'drupal_and_gigya');
//    if ($gigya_mode !== 'raas') {
//      $form_id = ($gigya_mode !== 'gigya') ? 'user_login_block' : NULL;
//      return array(
//        'content' => theme('gigya_login_block', array('form_id' => $form_id, 'suppress_title' => TRUE)),
//      );
//    }
//    //@TODO: check if we in raas mode.

    $build['block'] = array(
      '#theme' => 'gigya_login_block',
      '#suppress_title' => true
    );
    return $build;
  }
}
