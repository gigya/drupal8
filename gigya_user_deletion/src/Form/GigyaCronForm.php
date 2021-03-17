<?php
	/**
	 * @file
	 * Contains \Drupal\gigya_user_deletion\Form\GigyaCronForm.
	 */

	namespace Drupal\gigya_user_deletion\Form;

	use Drupal;
	use Drupal\Core\Form\ConfigFormBase;
	use Drupal\Core\Form\FormStateInterface;
	use Drupal\gigya\Helper\GigyaHelper;
	use Drupal\gigya\Helper\GigyaHelperInterface;
	use Aws\S3\S3Client;
	use Aws\S3\Exception\S3Exception;
	use Exception;
	use InvalidArgumentException;

	class GigyaCronForm extends ConfigFormBase {
		/**
		 * @var bool | GigyaHelper
		 */
		public $helper = false;

		protected array $aws_regions = [
			'us-east-2' => 'US East (Ohio)',
			'us-east-1' => 'US East (N. Virginia)',
			'us-west-1' => 'US West (N. California)',
			'us-west-2' => 'US West (Oregon)',
			'af-south-1' => 'Africa (Cape Town)',
			'ap-east-1' => 'Asia Pacific (Hong Kong)',
			'ap-south-1' => 'Asia Pacific (Mumbai)',
			'ap-northeast-3' => 'Asia Pacific (Osaka)',
			'ap-northeast-2' => 'Asia Pacific (Seoul)',
			'ap-southeast-1' => 'Asia Pacific (Singapore)',
			'ap-southeast-2' => 'Asia Pacific (Sydney)',
			'ap-northeast-1' => 'Asia Pacific (Tokyo)',
			'ca-central-1' => 'Canada (Central)',
			'cn-north-1' => 'China (Beijing)',
			'cn-northwest-1' => 'China (Ningxia)',
			'eu-central-1' => 'Europe (Frankfurt)',
			'eu-west-1' => 'Europe (Ireland)',
			'eu-west-2' => 'Europe (London)',
			'eu-south-1' => 'Europe (Milan)',
			'eu-west-3' => 'Europe (Paris)',
			'eu-north-1' => 'Europe (Stockholm)',
			'me-south-1' => 'Middle East (Bahrain)',
			'sa-east-1' => 'South America (São Paulo)',
			'other' => 'Other',
		];

		/**
		 * @param FormStateInterface $form_state
		 * @param string             $prop_name
		 *
		 * @return string
		 */
		private function getValue(FormStateInterface $form_state, string $prop_name) {
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
				'gigya_user_deletion.job',
			];
		}

		/**
		 * @param array                                $form
		 * @param \Drupal\Core\Form\FormStateInterface $form_state
		 * @param GigyaHelperInterface|NULL            $helper
		 *
		 * @return array
		 */
		public function buildForm(array $form, FormStateInterface $form_state, GigyaHelperInterface $helper = NULL) {
			if ($helper == NULL) {
				$this->helper = new GigyaHelper();
			}
			else {
				$this->helper = $helper;
			}

			/* Show error on missing dependencies */
			$messenger = Drupal::messenger();
			if (!$this->helper->checkEncryptKey()) {
				$messenger->addMessage($this->t('Cannot read encryption key'), 'error');
			}
			if (!class_exists('Aws\\S3\\S3Client')) {
				$messenger->addMessage($this->t('This module requires Amazon\'s PHP SDK'), 'error');
			}

			$form = parent::buildForm($form, $form_state);
			$config = $this->config('gigya_user_deletion.job');

			$form['enableJobLabel'] = array(
				'#type'       => 'label',
				'#title'      => $this->t('Enable'),
				"#attributes" => array(
					'class' => ['gigya-label-cb'],
				),
			);
			$form['enableJob'] = array(
				'#type'          => 'checkbox',
				'#default_value' => $config->get('gigya_user_deletion.enableJob'),
			);

			/* Deletion type – soft/hard */
			$form['deletionType'] = array(
				'#type'          => 'select',
				'#title'         => $this->t('Deletion type'),
				'#options'       => array(
					'soft' => $this->t('Tag deleted user'),
					'hard' => $this->t('Full user deletion'),
				),
				'#default_value' => $config->get('gigya_user_deletion.deletionType'),
			);

			/* Job frequency */
			$form['jobFrequency'] = array(
				'#type'  => 'textfield',
				'#title' => $this->t('Job frequency (minutes)'),
			);
			$jobFrequency = $config->get('gigya_user_deletion.jobFrequency') / 60;
			if ($jobFrequency == 0)
				$form['jobFrequency']['#default_value'] = '';
			else
				$form['jobFrequency']['#default_value'] = $jobFrequency;

			/* Email on success */
			$form['emailOnSuccess'] = array(
				'#type'          => 'textfield',
				'#title'         => $this->t('Email on success'),
				'#description'   => $this->t('A comma-separated list of emails that will be notified when the job completes successfully'),
				'#default_value' => $config->get('gigya_user_deletion.emailOnSuccess'),
			);

			/* Email on failure */
			$form['emailOnFailure'] = array(
				'#type'          => 'textfield',
				'#title'         => $this->t('Email on failure'),
				'#description'   => $this->t('A comma-separated list of emails that will be notified when the job fails or completes with errors'),
				'#default_value' => $config->get('gigya_user_deletion.emailOnFailure'),
			);

			/* S3 Storage Details */

			/* Label */
			$form['storage'] = array(
				'#type'       => 'label',
				'#title'      => $this->t('Amazon S3 settings'),
				'#attributes' => array(
					'class' => ['gigya-label-custom'],
				),
			);

			/* Region */
			$region = $config->get('gigya_user_deletion.storageDetails.region');
			$form['storageDetails']['region'] = [
				'#type'          => 'select',
				'#title'         => $this->t('Region'),
				'#description'   => $this->t('Please select an AWS region relevant for your files.'),
				'#options'       => $this->aws_regions,
				'#default_value' => array_key_exists($region, $this->aws_regions)
					? $region : (!empty($region) ? 'other' : 'us-east-1'),
			];

			$form['storageDetails']['other_region'] = [
				'#type'          => "textfield",
				'#default_value' => $config->get('gigya_user_deletion.storageDetails.region'),
				"#attributes"    => ["id" => "gigya-user-deletion-other-region"],
				'#states'        => [
					'visible' => [
						':input[name="region"]' => ['value' => 'other'],
					],
				],
			];

			/* Bucket name */
			$form['storageDetails']['bucketName'] = array(
				'#type'          => 'textfield',
				'#title'         => $this->t('Bucket name'),
				'#default_value' => $config->get('gigya_user_deletion.storageDetails.bucketName'),
			);

			/* Access key */
			$form['storageDetails']['accessKey'] = array(
				'#type'          => 'textfield',
				'#title'         => $this->t('Access key'),
				'#default_value' => $config->get('gigya_user_deletion.storageDetails.accessKey'),
			);

			/* Secret key */
			$key = $config->get('gigya_user_deletion.storageDetails.secretKey');
			$access_key = "";
			if (!empty($key))
				$access_key = $this->helper->decrypt($key);
			$form['storageDetails']['secretKey'] = array(
				'#type'       => 'textfield',
				'#title'      => $this->t('Secret key'),
				'#attributes' => array(
					'autocomplete' => 'off',
				),
			);
			if (!empty($access_key))
			{
				$form['storageDetails']['secretKey']['#default_value'] = "*********";
				$form['storageDetails']['secretKey']['#description'] = $this->t(
					"Current key first letters are @accessKey", array('@accessKey' => substr($access_key, 0, 2) . "******" . substr($access_key, strlen($access_key) - 2, 2))
				);

			}

			/* Object key prefix (directory) */
			$form['storageDetails']['objectKeyPrefix'] = array(
				'#type'          => 'textfield',
				'#title'         => $this->t('Object key prefix'),
				'#default_value' => $config->get('gigya_user_deletion.storageDetails.objectKeyPrefix'),
			);

			/* .S3 Storage Details */

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
			/* Check if the form has errors, if true we do not need to validate the user input because it has errors already. */
			parent::validateForm($form, $form_state);
			if ($form_state->getErrors()) {
				return;
			}

			$jobRequiredFields = [
				'jobFrequency',
				'storageDetails.bucketName',
				'storageDetails.accessKey',
				'storageDetails.secretKey',
			];

			/* If $_enableJob is true verify all required fields with value */
			$_enableJob = $this->getValue($form_state, 'enableJob');

			if ($_enableJob)
			{
				$messenger = Drupal::messenger();

				$helper = new GigyaHelper();
				if (!$helper->checkEncryptKey()) {
					$messenger->addMessage($this->t('Cannot read encryption key'), 'error');
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

					if (empty($fieldValue))
					{
						$form_state->setErrorByName($field, $this->t($field . " is a required field if the job enabled."));
						break;
					}
				}

				/* AWS region logic */
				if ($this->getValue($form_state, 'region') == 'other') {
					$region = $this->getValue($form_state, 'other_region');
				}
				else {
					$region = $this->getValue($form_state, 'region');
				}

				$bucketName = $this->getValue($form_state, 'bucketName');
				$accessKey = $this->getValue($form_state, 'accessKey');
				$secretKey = $this->getValue($form_state, 'secretKey');

				/* If secret encrypt -> decrypt it */
				if (empty($secretKey) or $secretKey === "*********")
				{
					$secretKeyEnc = Drupal::config('gigya_user_deletion.job')->get('gigya_user_deletion.storageDetails.secretKey');
					$secretKey = $helper->decrypt($secretKeyEnc);
				}

				if (class_exists('Aws\\S3\\S3Client'))
				{
					try {
						$s3Client = new S3Client([
							'credentials' => [
								'key'    => $accessKey,
								'secret' => $secretKey,
							],
							'region'      => $region,
							'version'     => 'latest',
						]);

						$s3Client->GetBucketLocation(['Bucket' => $bucketName,]);
					} catch (S3Exception $e) {
						$error = nl2br($e->getMessage());
						if (preg_match('/the region \'([a-zA-Z0-9-]+)\' is wrong; expecting \'([a-z0-9-]+)\'/', $error, $matches)) {
							$error = 'The region you entered is incorrect. It is likely that you meant: ' .
								((array_key_exists($matches[2], $this->aws_regions))
									? ($this->aws_regions[$matches[2]] . ' (' . $matches[2] . ')')
									: $matches[2]);
						}

						Drupal::logger('gigya_user_deletion')->error('Failed to connect to S3 server on form validation - ' . $error);
						$form_state->setErrorByName('storageDetails.secretKey',
							$this->t('Failed connecting to S3 server with error: ' . $error));
					} catch (InvalidArgumentException $e) {
						Drupal::logger('gigya_user_deletion')
							->error('Failed to validate S3 details with an internal error. This could be an issue with third party components. If the problem persists, please contact support. Error message: '
								. $e->getMessage());
						$form_state->setErrorByName('storageDetails.secretKey',
							$this->t('Failed to validate S3 details with an internal error: ' . $e->getMessage()));
					} catch (Exception $e) {
						Drupal::logger('gigya_user_deletion')->error('Failed to validate S3 details with an unknown error - '
							. $e->getMessage() . '<br />Exception type: ' . get_class($e));
						$form_state->setErrorByName('storageDetails.secretKey',
							$this->t('Failed to validate S3 details with an unknown error: ' . $e->getMessage()));
					}
				}
				else
				{
					$msg = 'This module requires the Amazon Web Services SDK for PHP. Please install the SDK before enabling the module.';
					Drupal::logger('gigya_user_deletion')->error($msg);
					$messenger->addMessage($this->t($msg), 'error');
				}
			}
		}

		public function submitForm(array &$form, FormStateInterface $form_state) {
			$config = $this->config('gigya_user_deletion.job');
			$config->set('gigya_user_deletion.enableJob', $this->getValue($form_state, 'enableJob'));
			$config->set('gigya_user_deletion.deletionType', $this->getValue($form_state, 'deletionType'));

			$jobFrequency = $this->getValue($form_state, 'jobFrequency') * 60;

			if ($jobFrequency == 0) {
				$config->set('gigya_user_deletion.jobFrequency', '');
			}
			else {
				$config->set('gigya_user_deletion.jobFrequency', $jobFrequency);
			}

			$config->set('gigya_user_deletion.emailOnSuccess', $this->getValue($form_state, 'emailOnSuccess'));
			$config->set('gigya_user_deletion.emailOnFailure', $this->getValue($form_state, 'emailOnFailure'));
			$config->set('gigya_user_deletion.storageDetails.bucketName', $this->getValue($form_state, 'bucketName'));
			$config->set('gigya_user_deletion.storageDetails.accessKey', $this->getValue($form_state, 'accessKey'));

			/* Region logic */
			if ($this->getValue($form_state, 'region') == 'other') {
				$config->set('gigya_user_deletion.storageDetails.region', $this->getValue($form_state, 'other_region'));
			}
			else {
				$config->set('gigya_user_deletion.storageDetails.region', $this->getValue($form_state, 'region'));
			}

			/* Encrypt storageDetails.secret */
			$temp_access_key = $this->getValue($form_state, 'secretKey');
			if (!empty($temp_access_key) && $temp_access_key !== "*********")
			{
				$enc = $this->helper->enc($temp_access_key);
				$config->set('gigya_user_deletion.storageDetails.secretKey', $enc);
			}
			$config->set('gigya_user_deletion.storageDetails.objectKeyPrefix', $this->getValue($form_state, 'objectKeyPrefix'));
			$config->save();
			parent::submitForm($form, $form_state);
		}
	}

