<?php

namespace Drupal\gigya_raas\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gigya\Helper\GigyaHelper;
use Drupal\gigya\Helper\GigyaHelperInterface;
use Drupal\gigya_raas\Helper\GigyaRaasHelper;
use Drupal\Core\Config\TypedConfigManagerInterface;


class GigyaFieldmappingForm extends ConfigFormBase {

  private $raas_helper= NULL;

  public $api_helper = NULL;


  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return [
      'gigya_raas.fieldmapping',
    ];
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state, GigyaHelperInterface $helper = NULL) {

    if ($this->raas_helper == NULL) {
      $this->raas_helper = new GigyaRaasHelper();
    }

    if ($helper == NULL) {
      $this->api_helper = new GigyaHelper();
    }
    else {
      $this->api_helper = $helper;
    }

    $config              = $this->config('gigya_raas.fieldmapping');
    $fieldmapping_config = json_encode($this->raas_helper->getFieldMappingConfig(), JSON_PRETTY_PRINT);


    if (!$this->api_helper->checkEncryptKey()) {
      $messenger = \Drupal::service('messenger');
      $messenger->addWarning($this->t('Please go to Gigya\'s general settings to define a Gigya\'s encryption key.'));
    }

    $form['gigya_fieldmapping_config'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Field Mapping Configuration'),
      '#open'          => TRUE,
      '#rows'          => max(5, substr_count($fieldmapping_config, "\n") + 1),
      '#default_value' => $fieldmapping_config,
    ];

    $form['uid_mapping'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('UID mapping (advanced)'),
      '#default_value' => $config->get('gigya.uid_mapping'),
      '#description'   => $this->t('Change this to map Gigya\'s UID to a different user field in Drupal (not recommended).'),
    ];

    $form['gigya_offline_sync'] = [
      '#type'       => 'label',
      '#title'      => $this->t('Offline Sync Settings'),
      '#attributes' => [
        'class' => ['gigya-label-custom'],
      ],
    ];

    $form['offline_sync']['enable_job_label'] = [
      '#type'       => 'label',
      '#title'      => $this->t('Enable'),
      "#attributes" => [
        'class' => ['gigya-label-cb'],
      ],
    ];
    $form['offline_sync']['enable_job']       = [
      '#type'          => 'checkbox',
      '#default_value' => $config->get('gigya.offline_sync.enable_job'),
    ];

    $form['offline_sync']['email_on_success'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Email on success'),
      '#default_value' => $config->get('gigya.offline_sync.email_on_success'),
    ];

    $form['offline_sync']['email_on_failure'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Email on failure'),
      '#default_value' => $config->get('gigya.offline_sync.email_on_failure'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'gigya_admin_fieldmapping';
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @throws \Exception
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    if ($this->raas_helper == NULL) {
      $this->raas_helper = new GigyaRaasHelper();
    }

    if ($helper == NULL) {
      $this->api_helper = new GigyaHelper();
    }
    else {
      $this->api_helper = $helper;
    }

    /* Field mapping */

    $fieldmapping_config         = $form_state->getValue('gigya_fieldmapping_config');
    $global_field_mapping_config = json_encode(\Drupal::config('gigya.global')
                                                      ->get('gigya.fieldMapping'));
    $config                      = $this->config('gigya_raas.fieldmapping');
    $messenger                   = \Drupal::service('messenger');


    if (!empty($fieldmapping_config) and $fieldmapping_config !== '{}') {

      if ($this->jsonFormValidation($fieldmapping_config) !== TRUE) {
        $form_state->setErrorByName('fieldmapping', $this->t($this->jsonFormValidation($fieldmapping_config)));
      }
      if (is_array(json_decode($fieldmapping_config)) and !empty(json_decode($fieldmapping_config))) {
        $form_state->setErrorByName('fieldmapping', $this->t('The field mapping configuration cannot be an array. Please follow the <a href="@documentation"><u>documentation</u></a>', ['@documentation.' => 'https://github.com/gigya/drupal8/wiki#field-mapping']));
      }

      /* Offline sync */

      $enable_job = $form_state->getValue('enable_job');

      if ($enable_job) {
        $email_on_success = $form_state->getValue('email_on_success');
        $email_on_failure = $form_state->getValue('email_on_failure');

        foreach (explode(',', $email_on_success) as $email) {
          if ($email !== '' and !\Drupal::service('email.validator')
                                        ->isValid(trim($email))) {
            $form_state->setErrorByName('fieldmapping', $this->t('Invalid email address provided in email on success'));
          }
        }

        foreach (explode(',', $email_on_failure) as $email) {
          if ($email !== '' and !\Drupal::service('email.validator')
                                        ->isValid(trim($email))) {
            $form_state->setErrorByName('fieldmapping', $this->t('Invalid email address provided in email on failure'));
          }
        }
      }
    }
    $this->validateMappedUidFieldExists($form_state, $form_state->getValue('uid_mapping'));

    if ($global_field_mapping_config !== '[]' and json_encode(json_decode($fieldmapping_config)) !== json_encode(json_decode($global_field_mapping_config)) and empty($form_state->getErrors())) {
      $messenger->addWarning("Duplicate field mapping configuration detected.
      The field mapping is configured both on this page and in the gigya.global configuration settings.
      It is recommended to work with this page only,
      which in any case takes precedence over the global configuration.");
    }

    if ($form_state->getValue('uid_mapping') !== $config->get('gigya.uid_mapping')) {
      $messenger->addWarning("Warning: Changing the UID field mapping may require a full migration of existing users, without which users will not be able to log in.");
    }
  }

  private function jsonFormValidation($json_text) {
    $after_decode_json = json_decode($json_text);
    if ($after_decode_json === NULL && json_last_error() !== JSON_ERROR_NONE) {
      return 'Invalid field mapping configuration: ' . json_last_error_msg();
    }
    else {
      return TRUE;
    }
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('gigya_raas.fieldmapping');

    $config->set('gigya.fieldmapping_config', $form_state->getValue('gigya_fieldmapping_config'));
    $config->set('gigya.uid_mapping', $form_state->getValue('uid_mapping'));

    $config->set('gigya.offline_sync.enable_job', $form_state->getValue('enable_job'));
    $config->set('gigya.offline_sync.email_on_success', $form_state->getValue('email_on_success'));
    $config->set('gigya.offline_sync.email_on_failure', $form_state->getValue('email_on_failure'));

    $config->save();

    parent::submitForm($form, $form_state);
  }

  private function validateMappedUidFieldExists ($form_state, string $uid__field_mapping) {

    if ($this->raas_helper == NULL) {
      $this->raas_helper = new GigyaRaasHelper();
    }

    if (!empty($uid__field_mapping) and !$this->raas_helper->doesFieldExist($uid__field_mapping)) {
      $form_state->setErrorByName('fieldmapping', $this->t("The UID mapping field does not exist in your database.
      Therefore, it is necessary to create the field before proceeding"));
    }
  }

}
