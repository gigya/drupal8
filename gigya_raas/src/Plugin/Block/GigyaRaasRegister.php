<?php
/**
 * Provides a 'Gigya RaaS Register' Block
 *
 * @Block(
 *   id = "gigya_raas_register",
 *   admin_label = @Translation("Gigya RaaS Register"),
 *   category = @Translation("Gigya")
 * )
 */

namespace Drupal\gigya_raas\Plugin\Block;

use Drupal\Core\Block\BlockBase;


class GigyaRaasRegister extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['block'] = array(
      '#theme' => 'gigya_raas_register_block',
      '#showDiv' => \Drupal::currentUser()->isAnonymous()
    );
    return $build;
  }
}
