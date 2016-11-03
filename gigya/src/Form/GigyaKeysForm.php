<?php

/**
 * @file
 * Contains \Drupal\gigya\Form\GigyaKeysForm.
 */

namespace Drupal\gigya\Form;


use Drupal;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gigya\Helper\GigyaHelper;
use Drupal\gigya\Helper\GigyaHelperInterface;
use Gigya\CmsStarterKit\sdk\GSObject;

class GigyaKeysForm extends ConfigFormBase {
  /**
   * @var Drupal\gigya\Helper\GigyaHelperInterface
   */
  public $helper = false;
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

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param GigyaHelperInterface $helper
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state, GigyaHelperInterface $helper = NULL) {
    // Form constructor

    if ($helper == NULL) {
      $this->helper = new GigyaHelper();
    }
    else {
      $this->helper = $helper;
    }
    if (!$this->helper->checkEncryptKey()) {
      drupal_set_message($this->t('Cannot read encrypt key'), 'error');
    }
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('gigya.settings');
    $form['gigya_api_key'] = array('#type' => 'textfield', '#title' => $this->t('Gigya API Key'),
      '#description' => $this->t('Specify the Gigya API Key for this domain'),
      '#default_value' => $config->get('gigya.gigya_api_key'), '#required' => TRUE);


    $form['gigya_application_key'] = array('#type' => 'textfield', '#title' => $this->t('Gigya Application Key'),
                                  '#description' => $this->t('Specify the Gigya Application key for this domain'),
                                  '#default_value' => $config->get('gigya.gigya_application_key'), '#required' => TRUE);
    $key = $config->get('gigya.gigya_application_secret_key');
    $access_key = "";
    if (!empty($key)) {
      $access_key = $this->helper->decrypt($key);
    }

    $form['gigya_application_secret_key'] = array('#type' => 'textfield', '#title' => $this->t('Gigya Application Secret Key'));
    $form['gigya_application_secret_key']['#description'] = $this->t('Specify the Gigya Application Secret (Base64 encoded) key for this domain');
    if (empty($access_key)) {
      $form['gigya_application_secret_key']['#required'] = TRUE;
    }
    else {
      $form['gigya_application_secret_key']['#default_value'] = "*********";
      $form['gigya_application_secret_key']['#required'] = FALSE;
      $form['gigya_application_secret_key']['#description'].= $this->t(",current key first and last letters are
        @accessKey", array('@accessKey' => substr($access_key, 0, 2) . "****" .
        substr($access_key, strlen($access_key) - 2, 2)));
    }

    $data_centers = array('us1.gigya.com' => 'US', 'eu1.gigya.com' => 'EU', 'au1.gigya.com' => 'AU', 'other' => "Other");
    $form['gigya_data_center'] = array(
      '#type' => 'select',
      '#title' => $this->t('Data Center'),
      '#description' => $this->t('Please select the Gigya data center in which your site is defined. To verify your site location contact your Gigya implementation manager.'),
      '#options' => $data_centers,
      '#default_value' => array_key_exists($config->get('gigya.gigya_data_center'), $data_centers) ? $config->get('gigya.gigya_data_center') : 'other'
    );


    $form['gigya_other_data_center'] = array(
      "#type" => "textfield",
      "#default_value" => $config->get('gigya.gigya_data_center'),
      "#attributes" => array("id" => "gigya-other-data-center"),
      '#states' => array(
        'visible' => array(
         ':input[name="gigya_data_center"]' => array('value' => 'other'),
        ),
      ),
    );

    return $form;

  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'gigya_admin_keys';

  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    //Encrypt key error.
    if (!$this->helper->checkEncryptKey()) {
      $form_state->setErrorByName('gigya_api_key', "");
      return;
    }
    //Check if the form has errors, if true we do not need to validate the user input because it has errors already.
    if ($form_state->getErrors()) {

      return;
    }

    $config = $this->config('gigya.settings');


    // API key was changed ?
    if ($this->getValue($form_state, 'gigya_api_key') != $config->get('gigya.gigya_api_key')) {
      $_gigya_api_key = $this->getValue($form_state, 'gigya_api_key');
    }
    else {
      $_gigya_api_key = $config->get('gigya.gigya_api_key');
    }

    // APP key was changed ?
    if ($this->getValue($form_state, 'gigya_application_key') != $config->get('gigya.gigya_application_key')) {
      $_gigya_application_key = $this->getValue($form_state, 'gigya_application_key');
    }
    else {
      $_gigya_application_key = $config->get('gigya.gigya_application_key');
    }

    // APP secret key was changed ?
    $temp_access_key = $this->getValue($form_state, 'gigya_application_secret_key');
    if (!empty($temp_access_key) && $temp_access_key !== "*********") {
      $_gigya_application_secret_key = $temp_access_key;
    }
    else {
      $key = $config->get('gigya.gigya_application_secret_key');
      if (!empty($key)) {
        $_gigya_application_secret_key = $this->helper->decrypt($key);
      }
      else {
        $_gigya_application_secret_key = "";
      }
    }

    // Data Center was changed ?

    if ($this->getValue($form_state, 'gigya_data_center') != $config->get('gigya.gigya_data_center') || $this->getValue($form_state, 'gigya_other_data_center') != $config->get('gigya.gigya_other_data_center')) {
      if ($this->getValue($form_state, 'gigya_data_center') == 'other') {
        $_gigya_data_center = $this->getValue($form_state, 'gigya_other_data_center');
      }
      else {
        $_gigya_data_center = $this->getValue($form_state, 'gigya_data_center');
      }
    }
    else {
      $_gigya_data_center = $config->get('gigya.gigya_data_center');
    }
    $access_params = array();
    $access_params['api_key'] = $_gigya_api_key;
    $access_params['app_secret'] = $_gigya_application_secret_key;
    $access_params['app_key'] = $_gigya_application_key;
    $access_params['data_center'] = $_gigya_data_center;
    $params = new GSObject();
    $params->put('url', 'http://gigya.com');


    $res = $this->helper->sendApiCall('shortenURL', $params, $access_params);
    $valid = FALSE;
    if ($res->getErrorCode() == 0) {
      $valid = TRUE;
    }
    if ($valid !== TRUE) {
      if (is_object($res)) {
        $code = $res->getErrorCode();
        $msg = $res->getMessage();
        //@TODO: see how we can print markup in the error messages.

        $form_state->setErrorByName('gigya_api_key', $this->t("Gigya API error: {$code} - {$msg}.") .
        "For more information please refer to <a href=http://developers.gigya.com/037_API_reference/zz_Response_Codes_and_Errors target=_blank>Response_Codes_and_Errors page</a>");
        Drupal::logger('gigya')->error('Error setting API key, error code: @code - @msg', array('@code' => $code, '@msg' => $msg));
      }
      else {
        $form_state->setErrorByName('gigya_api_key', $this->t("Your API key or Secret key could not be validated. Please try again"));
      }
    }
    else {
      drupal_set_message($this->t('Gigya validated properly. This site is authorized to use Gigya services'));

    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('gigya.settings');
    $config->set('gigya.gigya_application_key', $this->getValue($form_state, 'gigya_application_key'));
    $config->set('gigya.gigya_api_key', $this->getValue($form_state, 'gigya_api_key'));
    $temp_access_key = $this->getValue($form_state, 'gigya_application_secret_key');
    if (!empty($temp_access_key) && $temp_access_key !== "*********") {
      $enc = $this->helper->enc($temp_access_key);
      $config->set('gigya.gigya_application_secret_key', $enc);
    }

    if ($this->getValue($form_state, 'gigya_data_center') == 'other') {
      $config->set('gigya.gigya_data_center', $this->getValue($form_state, 'gigya_other_data_center'));
    }
    else {
      $config->set('gigya.gigya_data_center', $this->getValue($form_state, 'gigya_data_center'));
    }

    $config->save();
    return parent::submitForm($form, $form_state);
  }

  private function getValue($form_state, $prop_name) {
    return trim($form_state->getValue($prop_name));
  }
}
