<?php
/**
 * Created by PhpStorm.
 * User: Yaniv Aran-Shamir
 * Date: 5/23/17
 * Time: 9:19 AM
 */

namespace Drupal\gigya_raas\Form;


use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class GigyaSessionForm extends ConfigFormBase {

  private function getValue($form_state, $prop_name) {
    return trim($form_state->getValue($prop_name));
  }
  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return [
      'gigya_raas.settings'
    ];
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'gigya_raas_session';
  }

  /**
   * @param array                                $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form = parent::buildForm($form, $form_state);
    $config = $this->config('gigya_raas.settings');
    $form['session_type'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Session type'),
      '#description' => $this->t('Write something here'), //TODO:Change this.
      '#options' => array('fixed' => $this->t('Fixed session'), 'dynamic' => $this->t('Dynamic session')),
      '#default_value' => $config->get('session_type')
    );
    $form['fixed_session'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Fixed Session'),
      '#description' => $this->t('Add something here'), //TODO:Change this.
      '#default_value' => $config->get('fixed_session')
    );
    $form['dynamic_session'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Dynamic Session'),
      '#description' => $this->t('Add something here'), //TODO:Change this.
      '#default_value' => $config->get('dynamic_session')
    );
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('gigya_raas.settings');
    $config->set('session_type', $this->getValue($form_state, 'session_type'));
    $config->set('fixed_session', $this->getValue($form_state, 'fixed_session'));
    $config->set('dynamic_session', $this->getValue($form_state, 'dynamic_session'));
    $config->save();
    parent::submitForm($form, $form_state);
  }


}