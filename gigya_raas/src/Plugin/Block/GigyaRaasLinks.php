<?php

namespace Drupal\gigya_raas\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides a 'Gigya RaaS Links' Block
 *
 * @Block(
 *   id = "gigya_raas_links",
 *   admin_label = @Translation("Gigya RaaS Links"),
 *   category = @Translation("Gigya")
 * )
 */
class GigyaRaasLinks extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $links = [];
    if (\Drupal::currentUser()->isAnonymous()) {
      $url = Url::fromUserInput("/#");
      $url->setOptions([
        'attributes' => [
          'class' => 'gigya-raas-login',
          'id' => 'gigya-raas-login',
        ],
        'fragment' => 'raas-login',
      ]);
      $links['login'] = Link::fromTextAndUrl($this->t('Login'), $url);

      $url = Url::fromUserInput("#");
      $url->setOptions([
        'attributes' => [
          'class' => 'gigya-raas-reg',
          'id' => 'gigya-raas-reg',
        ],
        'fragment' => 'raas-register',
      ]);
      $links['register'] = Link::fromTextAndUrl($this->t('Register'), $url);
    }
    else {
      $url = Url::fromUserInput("#");
      $url->setOptions([
        'attributes' => [
          'class' => 'gigya-raas-prof',
          'id' => 'gigya-raas-prof',
        ],
        'fragment' => 'raas-profile',
      ]);

      $links['profile'] = Link::fromTextAndUrl($this->t('Profile'), $url);
    }
    $build['block'] = [
      '#theme' => 'gigya_raas_links_block',
      '#links' => $links,
    ];
    return $build;
  }

}
