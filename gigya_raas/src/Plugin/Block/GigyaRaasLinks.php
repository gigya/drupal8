<?php
/**
 * Provides a 'Gigya RaaS Links' Block
 *
 * @Block(
 *   id = "gigya_raas_links",
 *   admin_label = @Translation("Gigya RaaS Links"),
 *   category = @Translation("Gigya")
 * )
 */

namespace Drupal\gigya_raas\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;


class GigyaRaasLinks extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function build() {
    $links = array();
    if (\Drupal::currentUser()->isAnonymous()) {
      $url = Url::fromUserInput("/#");
      $url->setOptions(array(
        'attributes' => array(
          'class' => 'gigya-raas-login',
          'id' => 'gigya-raas-login',
        ),
        'fragment' => 'raas-login',
      ));
      $links['login'] = Link::fromTextAndUrl($this->t('Login'), $url);

      $url = Url::fromUserInput("#");
      $url->setOptions(array(
        'attributes' => array(
          'class' => 'gigya-raas-reg',
          'id' => 'gigya-raas-reg',
        ),
        'fragment' => 'raas-register',
      ));
      $links['register'] = Link::fromTextAndUrl($this->t('Register'), $url);
    }
    else {
      $url = Url::fromUserInput("#");
      $url->setOptions(array(
        'attributes' => array(
          'class' => 'gigya-raas-prof',
          'id' => 'gigya-raas-prof',
        ),
        'fragment' => 'raas-profile',
      ));

      $links['profile'] = Link::fromTextAndUrl($this->t('Profile'), $url);
    }
    $build['block'] = array(
      '#theme' => 'gigya_raas_links_block',
      '#links' => $links
    );
    return $build;
  }
}
