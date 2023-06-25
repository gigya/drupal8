<?php
/**
 * Provides a 'Gigya RaaS Profile' Block
 *
 * @Block(
 *   id = "gigya_raas_profile",
 *   admin_label = @Translation("Gigya RaaS Profile"),
 *   category = @Translation("Gigya")
 * )
 */

namespace Drupal\gigya_raas\Plugin\Block;

use Drupal\Core\Block\BlockBase;


class GigyaRaasProfile extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['block'] = array(
      '#theme' => 'gigya_raas_profile_block',
      '#showDiv' => \Drupal::currentUser()->isAuthenticated()
    );
    return $build;
  }
}
