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
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class GigyaJobForm extends ConfigFormBase {
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
    if (!$this->helper->checkEncryptKey()) {
        drupal_set_message($this->t('Cannot read encrypt key'), 'error');
    }
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('gigya.job');

    //enable job checkbox
      $form['enableJob'] = array('#type' => 'checkbox', '#title' => $this->t('Enable Job'));
      $form['enableJob']['#description'] = $this->t('Enable job of user delete');
      $form['enableJob']['#default_value'] = $config->get('gigya.enableJob');
      $enableJob = $config->get('gigya.enableJob');

   //Job frequency
    $form['jobFrequency'] = array('#type' => 'textfield', '#title' => $this->t('Job frequency (minutes)'));
    $form['jobFrequency']['#description'] = $this->t('Specify the Job frequency in minutes');
    $jobFrequency = $config->get('gigya.jobFrequency') / 60;
    //if $jobFrequency == 0 => display an empty value
    if ($jobFrequency == 0)
    {
        $form['jobFrequency']['#default_value'] = "";
    }
    else
    {
        $form['jobFrequency']['#default_value'] =  $jobFrequency;
    }


    //Email on success
    $form['emailOnSuccess'] = array('#type' => 'email', '#title' => $this->t('Email on success'),
    $form['emailOnSuccess']['#description'] = $this->t('Specify the email address to send on success'));
    $form['emailOnSuccess']['#default_value'] = $config->get('gigya.emailOnSuccess');

    //Email on failure
    $form['emailOnFailure'] = array('#type' => 'email', '#title' => $this->t('Email on failure'),
    $form['emailOnFailure']['#description'] = $this->t('Specify the email address to send on failure'));
    $form['emailOnFailure']['#default_value'] = $config->get('gigya.emailOnFailure');

      //S3 Storage details:
      //bucketName
    $form['storageDetails']['bucketName'] = array('#type' => 'textfield', '#title' => $this->t('Bucket Name'));
    $form['storageDetails']['bucketName']['#default_value'] = $config->get('gigya.storageDetails.bucketName');
    $form['storageDetails']['bucketName']['#description'] = $this->t('Specify the bucket name');


    //accessKey
      $form['storageDetails']['accessKey'] = array('#type' => 'textfield', '#title' => $this->t('Access Key'));
      $form['storageDetails']['accessKey']['#description'] = $this->t('Specify the access key of the S3');
      $form['storageDetails']['accessKey']['#default_value'] = $config->get('gigya.storageDetails.accessKey');

      //secretKey
      //decrypt secret key
      $key = $config->get('gigya.storageDetails.secretKey');
      $access_key = "";
      if (!empty($key)) {
          $access_key = $this->helper->decrypt($key);
      }

      $form['storageDetails']['secretKey'] = array('#type' => 'textfield', '#title' => $this->t('Secret Key'));
      $form['storageDetails']['secretKey']['#description'] = $this->t('Specify the secret key of the S3');
     // $form['storageDetails']['secretKey']['#default_value'] = $config->get('gigya.storageDetails.secretKey');
      if (!empty($access_key))
      {
          $form['storageDetails']['secretKey']['#default_value'] = "*********";
          $form['storageDetails']['secretKey']['#description'] = $this->t(",current key first and last letters are
        @accessKey", array('@accessKey' => substr($access_key, 0, 2) . "****" .
              substr($access_key, strlen($access_key) - 2, 2)));

      }
      //objectKeyPrefix
      $form['storageDetails']['objectKeyPrefix'] = array('#type' => 'textfield', '#title' => $this->t('Object Key Prefix'));
      $form['storageDetails']['objectKeyPrefix']['#description'] = $this->t('Specify the object key prefix of the S3');
      $form['storageDetails']['objectKeyPrefix']['#default_value'] = $config->get('gigya.storageDetails.objectKeyPrefix');

      return $form;

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
    $jobRequiredFields = ['jobFrequency', 'storageDetails.bucketName','storageDetails.accessKey','storageDetails.secretKey'];

    // if $_enableJob is true verify all required fields with value
    $_enableJob = $this->getValue($form_state, 'enableJob');

    if ($_enableJob)
    {
        $helper = new GigyaHelper();
        if (!$helper->checkEncryptKey()) {
            drupal_set_message($this->t('Cannot read encrypt key'), 'error');
        }
        foreach ($jobRequiredFields as $field)
        {
            if (strpos($field, 'storageDetails') !== false)
            {
                $num = strlen($field) - strlen('storageDetails') - 1;
                $field = substr($field, 0 - $num);
                $fieldValue = $form['storageDetails'][$field]['#value'];
            }
            else
            {
                $fieldValue = $this->getValue($form_state, $field);
            }
            if ($fieldValue == '')
            {
                $form_state->setErrorByName($field, $this->t($field . " is required field if job enabled."));
                break;
            }
        }
        //try to connect to storage and get region
        $bucketName = $this->getValue($form_state, 'bucketName');
        $accessKey = $this->getValue($form_state, 'accessKey');
        $secretKey = $this->getValue($form_state, 'secretKey');

        //if secret encrypt -> decrypt it
        if (empty($secretKey) || $secretKey === "*********")
        {
            $secretKeyEnc = \Drupal::config('gigya.job')->get('gigya.storageDetails.secretKey');
            $secretKey = $helper->decrypt($secretKeyEnc);
        }
        $s3Client = S3Client::factory(array(
            'key' => $accessKey,
            'secret' => $secretKey,
        ));
        try {
            $response = $s3Client->GetBucketLocation(array('Bucket' => $bucketName,));
        }
        catch(S3Exception $e) {
            \Drupal::logger('gigya')->error("Failed to connect to S3 server - " . $e);
            $form_state->setErrorByName("Failed connecting to S3 server. ", $this->t($field . "Please try again later."));
        }

    }

  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('gigya.job');
    $config->set('gigya.enableJob', $this->getValue($form_state, 'enableJob'));

    $jobFrequency = $this->getValue($form_state, 'jobFrequency') * 60;
    if ($jobFrequency == 0)
    {
        $config->set('gigya.jobFrequency', "");
    }
    else
    {
        $config->set('gigya.jobFrequency', $jobFrequency);
    }
    $config->set('gigya.emailOnSuccess', $this->getValue($form_state, 'emailOnSuccess'));
    $config->set('gigya.emailOnFailure', $this->getValue($form_state, 'emailOnFailure'));
    $config->set('gigya.storageDetails.bucketName', $this->getValue($form_state, 'bucketName'));
    $config->set('gigya.storageDetails.accessKey', $this->getValue($form_state, 'accessKey'));
    //encrypt storageDetails.secret
    $temp_access_key = $this->getValue($form_state, 'secretKey');
    if (!empty($temp_access_key) && $temp_access_key !== "*********") {
        $enc = $this->helper->enc($temp_access_key);
        $config->set('gigya.storageDetails.secretKey', $enc);
    }
    $config->set('gigya.storageDetails.objectKeyPrefix', $this->getValue($form_state, 'objectKeyPrefix'));
    $config->save();
    return parent::submitForm($form, $form_state);
  }

  private function getValue($form_state, $prop_name) {
    return trim($form_state->getValue($prop_name));
  }
  function connectToStorage()
  {
      $secretKey = "";
      $storageDetails = \Drupal::config('gigya.job')->get('gigya.storageDetails');
      $helper = new GigyaHelper();
      $bucketName = $storageDetails['bucketName'];
      $accessKey = $storageDetails['accessKey'];
      $secretKeyEnc = $storageDetails['secretKey'];
      if (!empty($secretKeyEnc)) {
          $secretKey = $helper->decrypt($secretKeyEnc);
      }
      $objectKeyPrefix = $storageDetails['objectKeyPrefix'] . "/";

      $s3Client = S3Client::factory(array(
          'key' => $accessKey,
          'secret' => $secretKey,
      ));
      $response = $s3Client->GetBucketLocation(array('Bucket' => $bucketName,));
      return true;
     // return $files = $response->location('Location');
  }
}
