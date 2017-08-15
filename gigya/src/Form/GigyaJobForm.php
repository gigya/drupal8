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
//      $form['enableJobLabel'] = array('#type' => 'label', '#title' => $this->t('Enable Job'), "#attributes" => [
//          'class' => 'gigya-space'
//      ]);
      $form['enableJob'] = array('#type' => 'checkbox', '#title' => $this->t('Enable'));//, "#options_attributes" => array( 'class' => array('gigya-bold')));
      $form['enableJob']['attributes']['label']['class'] = 'gigya-bold';
      $form['enableJob']['#default_value'] = $config->get('gigya.enableJob');
     // $form['enableJob']['#theme'] = 'checkbox_markup_checkboxes';
      $enableJob = $config->get('gigya.enableJob');

   //Job frequency
    $form['jobFrequency'] = array('#type' => 'textfield', '#title' => $this->t('Job frequency (minutes)'));
//    if ($form['enableJob']['#default_value']) {
          $form['jobFrequency']['#required'] = TRUE;
//      }
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
    $form['emailOnSuccess'] = array('#type' => 'textfield', '#title' => $this->t('Email on success'),
    $form['emailOnSuccess']['#description'] = $this->t('A comma-separated list of emails that the job will notify upon completed successfully.'));
    $form['emailOnSuccess']['#default_value'] = $config->get('gigya.emailOnSuccess');

    //Email on failure
    $form['emailOnFailure'] = array('#type' => 'textfield', '#title' => $this->t('Email on failure'),
    $form['emailOnFailure']['#description'] = $this->t('A comma-separated list of emails that the job will notify upon completed failure.'));
    $form['emailOnFailure']['#default_value'] = $config->get('gigya.emailOnFailure');

      //S3 Storage details:

    $form['storage'] = array('#type' => 'label', '#title' => $this->t('Amazon S3 settings'), "#attributes" => [
        'class' => 'gigya-label-custom'
    ]);
      //bucketName
    $form['storageDetails']['bucketName'] = array('#type' => 'textfield', '#title' => $this->t('Bucket name'));
    $form['storageDetails']['bucketName']['#default_value'] = $config->get('gigya.storageDetails.bucketName');


    //accessKey
      $form['storageDetails']['accessKey'] = array('#type' => 'textfield', '#title' => $this->t('Access key'));
      $form['storageDetails']['accessKey']['#default_value'] = $config->get('gigya.storageDetails.accessKey');

      //secretKey
      //decrypt secret key
      $key = $config->get('gigya.storageDetails.secretKey');
      $access_key = "";
      if (!empty($key)) {
          $access_key = $this->helper->decrypt($key);
      }

      $form['storageDetails']['secretKey'] = array('#type' => 'textfield', '#title' => $this->t('Secret key'));
     // $form['storageDetails']['secretKey']['#default_value'] = $config->get('gigya.storageDetails.secretKey');
      if (!empty($access_key))
      {
          $form['storageDetails']['secretKey']['#default_value'] = "*********";
          $form['storageDetails']['secretKey']['#description'] = $this->t("Current key first letters are
        @accessKey", array('@accessKey' => substr($access_key, 0, 2) . "******" .
              substr($access_key, strlen($access_key) - 2, 2)));

      }
      //objectKeyPrefix
      $form['storageDetails']['objectKeyPrefix'] = array('#type' => 'textfield', '#title' => $this->t('Object key prefix'));
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
            $s3Client->GetBucketLocation(array('Bucket' => $bucketName,));
        }
        catch(S3Exception $e) {
            \Drupal::logger('gigya')->error("Failed to connect to S3 server - " . $e->getMessage());
            $form_state->setErrorByName('storageDetails.secretKey', $this->t("Failed connecting to S3 server with error: " . $e->getMessage()));
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

      $s3Client = S3Client::factory(array(
          'key' => $accessKey,
          'secret' => $secretKey,
      ));
      try {
          $response = $s3Client->GetBucketLocation(array('Bucket' => $bucketName,));
          return $response['Location'];
      }
      catch(S3Exception $e) {
          \Drupal::logger('gigya')->error("Failed to get region from S3 server - " . $e->getMessage());
      }
  }
}
