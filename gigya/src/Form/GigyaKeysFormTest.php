<?php

/**
 * @file
 * Contains \Drupal\gigya\Form\GigyaKeysForm.
 */

namespace Drupal\gigya\Form;


use Drupal;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gigya\Helper\GigyaHelper;
use Gigya\sdk\GSObject;

class GigyaKeysFormTest extends GigyaKeysForm {
  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return [
      'gigya.settings',
    ];
  }

  public function buildForm(array $form, FormStateInterface $form_state, $arg = FALSE) {
    // Form constructor
    parent::buildForm($form, $form_state);
    return $form;

  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'gigya_admin_keys_test';
  }

}
