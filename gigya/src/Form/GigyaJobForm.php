<?php

/**
 * @file
 * Contains \Drupal\gigya\Form\GigyaJobForm.
 */

namespace Drupal\gigya\Form;


use Drupal;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gigya\Helper\GigyaHelper;
use Drupal\gigya\Helper\GigyaHelperInterface;
use Gigya\CmsStarterKit\sdk\GSObject;

class GigyaJobForm extends ConfigFormBase {
  /**
   * @var Drupal\gigya\Helper\GigyaHelperInterface
   */



 // public $helper = false;



  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return [
      'gigya.job',
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

    $form = parent::buildForm($form, $form_state);
    $config = $this->config('gigya.job');
    //enable job checkbox
      $form['enableJob'] = array('#type' => 'checkbox', '#title' => $this->t('Enable Job'),
      $form['enableJob']['#description'] = $this->t('Enable job of user delete'));
      $enableJob = $config->get('gigya.enableJob');
//
//    //Job frequency
//    $form['jobFrequency'] = array('#type' => 'textfield', '#title' => $this->t('Job frequency (minutes)'),
//    $form['jobFrequency']['#description'] = $this->t('Specify the Job frequency in minutes'));
//      if ($enableJob) {
//          $form['jobFrequency']['#required'] = TRUE;
//      }
//    //Email on success
//    $form['emailOnSuccess'] = array('#type' => 'textfield', '#title' => $this->t('Email on success'),
//    $form['emailOnSuccess']['#description'] = $this->t('Specify the email address to send on success'));
//
//    //Email on failure
//    $form['emailOnFailure'] = array('#type' => 'textfield', '#title' => $this->t('Email on failure'),
//    $form['emailOnFailure']['#description'] = $this->t('Specify the email address to send on failure'));

    //S3 Storage details:
      //bucketName
//      $form['bucketName'] = array('#type' => 'textfield', '#title' => $this->t('Bucket Name'));
//      $form['bucketName']['#description'] = $this->t('Specify the bucket name');
//      if ($enableJob == TRUE) {
//          $form['bucketName']['#required'] = TRUE;
//      }
//
//      //accessKey
//      $form['accessKey'] = array('#type' => 'textfield', '#title' => $this->t('Access Key'));
//      $form['accessKey']['#description'] = $this->t('Specify the access key of the S3');
//      if ($enableJob) {
//          $form['accessKey']['#required'] = TRUE;
//      }
//
//      //TBD: encrypt secretKey
//      //secretKey
//      $form['secretKey'] = array('#type' => 'textfield', '#title' => $this->t('Secret Key'));
//      $form['secretKey']['#description'] = $this->t('Specify the secret key of the S3');
//      if ($enableJob) {
//          $form['secretKey']['#required'] = TRUE;
//      }
//
//      //objectKeyPrefix
//      $form['objectKeyPrefix'] = array('#type' => 'textfield', '#title' => $this->t('Object Key Prefix'));
//      $form['objectKeyPrefix']['#description'] = $this->t('Specify the object key prefix of the S3');

  //  return $form;
      return parent::buildForm($form, $form_state);
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'gigya_job_params';
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    //Check if the form has errors, if true we do not need to validate the user input because it has errors already.
    if ($form_state->getErrors()) {
      return;
    }

    $config = $this->config('gigya.job');

    // enableJob was changed ?
    if ($this->getValue($form_state, 'enableJob') != $config->get('gigya.enableJob')) {
      $_enableJob = $this->getValue($form_state, 'enableJob');
    }
    else {
      $_enableJob = $config->get('gigya.enableJob');
    }
//
//    // jobFrequency was changed ?
//    if ($this->getValue($form_state, 'jobFrequency') != $config->get('gigya.jobFrequency')) {
//      $_jobFrequency = $this->getValue($form_state, 'jobFrequency');
//    }
//    else {
//      $_jobFrequency = $config->get('gigya.jobFrequency');
//    }
//
//    // emailOnSuccess was changed ?
//      if ($this->getValue($form_state, 'emailOnSuccess') != $config->get('gigya.emailOnSuccess')) {
//          $_emailOnSuccess = $this->getValue($form_state, 'emailOnSuccess');
//      }
//      else {
//          $_emailOnSuccess = $config->get('gigya.jobFrequency');
//      }
//
//      // emailOnFailure was changed ?
//      if ($this->getValue($form_state, 'emailOnFailure') != $config->get('gigya.emailOnFailure')) {
//          $_emailOnFailure = $this->getValue($form_state, 'emailOnFailure');
//      }
//      else {
//          $_emailOnFailure = $config->get('gigya.emailOnFailure');
//      }
//
//      // bucketName was changed ?
//      if ($this->getValue($form_state, 'bucketName') != $config->get('gigya.bucketName')) {
//          $_bucketName = $this->getValue($form_state, 'bucketName');
//      }
//      else {
//          $_bucketName = $config->get('gigya.bucketName');
//      }
//
//      // accessKey was changed ?
//      if ($this->getValue($form_state, 'accessKey') != $config->get('gigya.accessKey')) {
//          $_accessKey = $this->getValue($form_state, 'accessKey');
//      }
//      else {
//          $_accessKey = $config->get('gigya.accessKey');
//      }
//
//      // secretKey was changed ?
//      if ($this->getValue($form_state, 'secretKey') != $config->get('gigya.secretKey')) {
//          $_secretKey = $this->getValue($form_state, 'secretKey');
//      }
//      else {
//          $_secretKey = $config->get('gigya.secretKey');
//      }
//
//      // objectKeyPrefix was changed ?
//      if ($this->getValue($form_state, 'objectKeyPrefix') != $config->get('gigya.objectKeyPrefix')) {
//          $_objectKeyPrefix = $this->getValue($form_state, 'objectKeyPrefix');
//      }
//      else {
//          $_objectKeyPrefix = $config->get('gigya.objectKeyPrefix');
//      }
//
//    $access_params = array();
//    $access_params['enableJob'] = $_enableJob;
//    $access_params['jobFrequency'] = $_jobFrequency;
//    $access_params['emailOnSuccess'] = $_emailOnSuccess;
//    $access_params['emailOnFailure'] = $_emailOnFailure;
//    $access_params['bucketName'] = $_bucketName;
//    $access_params['accessKey'] = $_accessKey;
//    $access_params['secretKey'] = $_secretKey;
//    $access_params['objectKeyPrefix'] = $_objectKeyPrefix;
//    $params = new GSObject();
   // $params->put('url', 'http://gigya.com');


    //TBD: try to connect to storage
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('gigya.job');
    $config->set('gigya.enableJob', $this->getValue($form_state, 'enableJob'));
//    $config->set('gigya.jobFrequency', $this->getValue($form_state, 'jobFrequency'));
//    $config->set('gigya.emailOnSuccess', $this->getValue($form_state, 'emailOnSuccess'));
//    $config->set('gigya.emailOnFailure', $this->getValue($form_state, 'emailOnFailure'));
//    $config->set('gigya.bucketName', $this->getValue($form_state, 'bucketName'));
//    $config->set('gigya.accessKey', $this->getValue($form_state, 'accessKey'));
//    $config->set('gigya.secretKey', $this->getValue($form_state, 'secretKey'));
//    $config->set('gigya.objectKeyPrefix', $this->getValue($form_state, 'objectKeyPrefix'));
    $config->save();
    return parent::submitForm($form, $form_state);
  }

  private function getValue($form_state, $prop_name) {
    return trim($form_state->getValue($prop_name));
  }
}
