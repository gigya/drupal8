<?php
/**
 * Provides a 'Gigya RaaS links' Block
 *
 * @Block(
 *   id = "gigya_rass_links",
 *   admin_label = @Translation("Gigya RaaS links"),
 *   category = @Translation("Gigya")
 * )
 */

namespace Drupal\gigya\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGenerator;


class GigyaRassLinks extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function build() {
    $links = array();
    $url = Url::fromUserInput("/#");
    $url->setOptions(array(
      'attributes' => array(
        'class' => 'gigya-raas-login',
        'id' => 'gigya-raas-login',
      ),
      'fragment' => 'raas-login',
    ));
    $links['login'] = Link::fromTextAndUrl($this->t('login'), $url);

    $url = Url::fromUserInput("#");
    $url->setOptions(array(
      'attributes' => array(
        'class' => 'gigya-raas-reg',
        'id' => 'gigya-raas-reg',
      ),
      'fragment' => 'raas-register',
    ));
    $links['register'] = Link::fromTextAndUrl($this->t('register'), $url);
    $build['block'] = array(
      '#theme' => 'gigya_raas_links_block',
      '#links' => $links
    );
    return $build;
  }
}
