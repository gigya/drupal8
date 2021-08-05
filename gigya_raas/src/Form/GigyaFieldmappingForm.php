<?php

/**
 * @file
 * Contains \Drupal\gigya_raas\Form\GigyaFieldmappingForm.
 */

namespace Drupal\gigya_raas\Form;

use Drupal;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gigya_raas\Helper\GigyaRaasHelper;

class GigyaFieldmappingForm extends ConfigFormBase {

	private $helper;

	public function __construct(ConfigFactoryInterface $config_factory) {
		parent::__construct($config_factory);

		$this->helper = new GigyaRaasHelper();
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
			'gigya_raas.fieldmapping',
		];
	}

	/**
	 * @param array $form
	 * @param Drupal\Core\Form\FormStateInterface $form_state
	 *
	 * @return array
	 */
	public function buildForm(array $form, FormStateInterface $form_state) {
		$config = $this->config('gigya_raas.fieldmapping');

		$fieldmapping_config = json_encode($this->helper->getFieldMappingConfig(), JSON_PRETTY_PRINT);

		$form['gigya_fieldmapping_config'] = [
			'#type' => 'textarea',
			'#title' => $this->t('Field Mapping Configuration'),
			'#open' => TRUE,
			'#rows' => max(5, substr_count($fieldmapping_config, "\n") + 1),
			'#default_value' => $fieldmapping_config,
		];

		$form['uid_mapping'] = [
			'#type' => 'textfield',
			'#title' => $this->t('UID mapping (advanced)'),
			'#default_value' => $config->get('gigya.uid_mapping'),
			'#description' => $this->t('Change this to map Gigya\'s UID to a different user field in Drupal (not recommended).'),
		];

		$form['gigya_offline_sync'] = [
			'#type' => 'label',
			'#title' => $this->t('Offline Sync Settings'),
			'#attributes' => [
				'class' => ['gigya-label-custom'],
			],
		];

		$form['offline_sync']['enable_job_label'] = [
			'#type' => 'label',
			'#title' => $this->t('Enable'),
			"#attributes" => [
				'class' => ['gigya-label-cb'],
			],
		];
		$form['offline_sync']['enable_job'] = [
			'#type' => 'checkbox',
			'#default_value' => $config->get('gigya.offline_sync.enable_job'),
		];

		$form['offline_sync']['email_on_success'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Email on success'),
			'#default_value' => $config->get('gigya.offline_sync.email_on_success'),
		];

		$form['offline_sync']['email_on_failure'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Email on failure'),
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
	 * @param Drupal\Core\Form\FormStateInterface $form_state
	 *
	 * @throws \Exception
	 */
	public function validateForm(array &$form, FormStateInterface $form_state) {
		parent::validateForm($form, $form_state);

		/* Field mapping */

		$fieldmapping_config = $form_state->getValue('gigya_fieldmapping_config');

		if (!empty($fieldmapping_config) and $fieldmapping_config !== '{}') {
			if (empty(json_decode($fieldmapping_config))) {
				$form_state->setErrorByName('fieldmapping', $this->t('Invalid field mapping configuration'));
			}
		}

		/* Offline sync */

		$enable_job = $form_state->getValue('enable_job');

		if ($enable_job) {
			$email_on_success = $form_state->getValue('email_on_success');
			$email_on_failure = $form_state->getValue('email_on_failure');

			foreach (explode(',', $email_on_success) as $email) {
				if ($email !== '' and !Drupal::service('email.validator')
						->isValid(trim($email))) {
					$form_state->setErrorByName('fieldmapping', $this->t('Invalid email address provided in email on success'));
				}
			}

			foreach (explode(',', $email_on_failure) as $email) {
				if ($email !== '' and !Drupal::service('email.validator')
						->isValid(trim($email))) {
					$form_state->setErrorByName('fieldmapping', $this->t('Invalid email address provided in email on failure'));
				}
			}
		}
	}

	/**
	 * @param array $form
	 * @param Drupal\Core\Form\FormStateInterface $form_state
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
}
