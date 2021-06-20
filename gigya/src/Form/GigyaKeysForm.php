<?php

/**
 * @file
 * Contains \Drupal\gigya\Form\GigyaKeysForm.
 */

namespace Drupal\gigya\Form;

use Drupal;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\gigya\Helper\GigyaHelper;
use Drupal\gigya\Helper\GigyaHelperInterface;
use Exception;
use Gigya\PHP\GSObject;

class GigyaKeysForm extends ConfigFormBase {

	/**
	 * @var Drupal\gigya\Helper\GigyaHelperInterface
	 */
	public $helper = FALSE;

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
	 * @param array                                $form
	 * @param \Drupal\Core\Form\FormStateInterface $form_state
	 * @param GigyaHelperInterface | NULL          $helper
	 *
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

		/* Verify requirements */
		$messenger = Drupal::service('messenger');
		if (!$this->helper->checkEncryptKey()) {
			$messenger->addError($this->t('Cannot read encryption key. Either the file path is incorrect or the file is empty.'));
		}
		if (!class_exists('Gigya\\PHP\\GSObject')) {
			$messenger->addError($this->t('The required library Gigya PHP SDK cannot be found. Please install it via Composer.'));
		}

		$form = parent::buildForm($form, $form_state);
		$config = $this->config('gigya.settings');

		$form['gigya_api_key'] = [
			'#type'          => 'textfield',
			'#title'         => $this->t('Gigya API Key'),
			'#description'   => $this->t('Specify the Gigya API Key for this domain'),
			'#default_value' => $config->get('gigya.gigya_api_key'),
			'#required'      => TRUE,
		];

		$form['gigya_application_key'] = [
			'#type'          => 'textfield',
			'#title'         => $this->t('Gigya Application Key'),
			'#description'   => $this->t('Specify the Gigya Application key for this domain'),
			'#default_value' => $config->get('gigya.gigya_application_key'),
			'#required'      => TRUE,
		];

		$form['gigya_auth_mode'] = [
			'#type'          => 'radios',
			'#title'         => $this->t('Gigya Authentication Mode'),
			'#description'   => $this->t('Gigya allows to authenticate with a user or application and secret key pair, or an RSA key pair'),
			'#options'       => [
				'user_secret' => $this->t('User / Secret key pair'),
				'user_rsa'    => $this->t('RSA key pair'),
			],
			'#default_value' => $config->get('gigya.gigya_auth_mode') ?? 'user_secret',
		];

		$rsa_private_key = $this->helper->decrypt($config->get('gigya.gigya_rsa_private_key'));
		$rsa_private_key = substr(trim(str_replace([
			'-----BEGIN RSA PRIVATE KEY-----',
			'-----BEGIN PRIVATE KEY-----',
			'-----END RSA PRIVATE KEY-----',
			'-----END PRIVATE KEY-----',
		], '', $rsa_private_key)), 0, -2);
		$is_private_key_entered = (!empty($rsa_private_key));
		$form['gigya_rsa_private_key'] = [
			'#type'        => 'textarea',
			'#title'       => $this->t('Gigya RSA Private Key'),
			'#description' => $is_private_key_entered
				? '<span class="gigya-msg-success">' . $this->t('RSA private key entered') . '</span>'
				: $this->t('Specify the Gigya RSA private key Key for this domain'),
			'#rows'        => 16,
			'#cols'        => 64,
			'#attributes'  => [
				'placeholder' => ($is_private_key_entered)
					? $this->t('Gigya RSA private key has been entered. Entered key is: ') . substr($rsa_private_key, 0, 4) . '****' . substr($rsa_private_key, -4)
					: $this->t('Enter your RSA private key, as provided by Gigya'),
				'style'       => 'width: auto',
			],
			'#states'      => [
				'visible' => [
					':input[name="gigya_auth_mode"]' => ['value' => 'user_rsa'],
				],
			],
		];

		$key = $config->get('gigya.gigya_application_secret_key');
		$access_key = "";
		if (!empty($key)) {
			$access_key = $this->helper->decrypt($key);
		}
		$form['gigya_application_secret_key'] = [
			'#type'        => 'textfield',
			'#title'       => $this->t('Gigya Application Secret Key'),
			'#description' => $this->t('Specify the Gigya Application Secret Key for this domain'),
			'#attributes'  => [
				'autocomplete' => 'off',
			],
			'#states'      => [
				'visible' => [
					':input[name="gigya_auth_mode"]' => ['value' => 'user_secret'],
				],
			],
			'#required'    => FALSE,
		];

		if (!empty($access_key)) {
			$form['gigya_application_secret_key']['#default_value'] = "*********";
			$form['gigya_application_secret_key']['#description'] .= $this->t(". Current key first and last letters are @accessKey", [
				'@accessKey' => substr($access_key, 0, 2) . "****" .
					substr($access_key, strlen($access_key) - 2, 2),
			]);
		}

		$data_centers = ['us1.gigya.com' => 'US', 'eu1.gigya.com' => 'EU', 'au1.gigya.com' => 'AU', 'ru1.gigya.com' => 'RU', 'cn1.gigya-api.cn' => 'CN', 'other' => "Other"];
		$form['gigya_data_center'] = [
			'#type'          => 'select',
			'#title'         => $this->t('Data Center'),
			'#description'   => $this->t('Please select the Gigya data center in which your site is defined. To verify your site location contact your Gigya implementation manager.'),
			'#options'       => $data_centers,
			'#default_value' => array_key_exists($config->get('gigya.gigya_data_center'), $data_centers) ? $config->get('gigya.gigya_data_center') : 'other',
		];

		$form['gigya_other_data_center'] = [
			'#type'          => "textfield",
			'#default_value' => $config->get('gigya.gigya_data_center'),
			"#attributes"    => ["id" => "gigya-other-data-center"],
			'#states'        => [
				'visible' => [
					':input[name="gigya_data_center"]' => ['value' => 'other'],
				],
			],
		];

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

	/**
	 * @param array                                $form
	 * @param \Drupal\Core\Form\FormStateInterface $form_state
	 *
	 * @throws Exception
	 */
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
		$_gigya_auth_mode = $this->getValue($form_state, 'gigya_auth_mode');
		$temp_access_key = ($_gigya_auth_mode === 'user_rsa')
			? $this->getValue($form_state, 'gigya_rsa_private_key')
			: $this->getValue($form_state, 'gigya_application_secret_key');
		if (!empty($temp_access_key) && $temp_access_key !== "*********") { /* Auth key just entered */
			$_gigya_auth_key = $temp_access_key;
		}
		else { /* Auth key not yet entered or is already found in the system */
			$key = ($_gigya_auth_mode === 'user_rsa')
				? $config->get('gigya.gigya_rsa_private_key')
				: $config->get('gigya.gigya_application_secret_key');
			if (!empty($key)) {
				$_gigya_auth_key = $this->helper->decrypt($key);
			}
			else {
				$_gigya_auth_key = '';
			}
		}

		/* Data Center was changed ? */
		if ($this->getValue($form_state, 'gigya_data_center') != $config->get('gigya.gigya_data_center')
			|| $this->getValue($form_state, 'gigya_other_data_center') != $config->get('gigya.gigya_other_data_center')
		) {
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

		$access_params = [];
		$access_params['api_key'] = $_gigya_api_key;
		$access_params['auth_mode'] = $_gigya_auth_mode;
		$access_params['auth_key'] = $_gigya_auth_key;
		$access_params['app_key'] = $_gigya_application_key;
		$access_params['data_center'] = $_gigya_data_center;
		$params = new GSObject();
		$params->put('filter', 'full');

		$valid = FALSE;
		try {
			$res = $this->helper->sendApiCall('socialize.getProvidersConfig', $params, $access_params);
			if ($res->getErrorCode() == 0) {
				$valid = TRUE;
			}
		} catch (Exception $e) {
			$code = $e->getCode();
			$msg = $e->getMessage();
			$error_message = new TranslatableMarkup('Gigya error: @code – @msg', ['@code' => $code, '@message' => $msg]);
		}

		if ($valid !== TRUE) {
			if (!empty($res) and is_object($res)) {
				if (!isset($error_message)) {
					$code          = $res->getErrorCode();
					$msg           = $res->getErrorMessage();
					$error_message = new TranslatableMarkup('Gigya API error: @code – @msg. For more information, please refer to Gigya\'s documentation page on
																	<a href="https://developers.gigya.com/display/GD/Response+Codes+and+Errors" target="_blank">Response Codes and Errors</a>.',
						['@code' => $code, '@msg' => $msg]);
				}

				$form_state->setErrorByName('gigya_api_key', $error_message);
				Drupal::logger('gigya')->error($error_message);
			}
			else {
				$form_state->setErrorByName('gigya_api_key', $this->t('Your Gigya authentication details could not be validated. Please try again.'));
			}
		}
		else {
			$messenger = Drupal::service('messenger');
			$messenger->addMessage($this->t('Gigya validated properly. This site is authorized to use Gigya services'));
		}
	}

	/**
	 * @param array              $form
	 * @param FormStateInterface $form_state
	 */
	public function submitForm(array &$form, FormStateInterface $form_state) {
		$config = $this->config('gigya.settings');
		$config->set('gigya.gigya_application_key', $this->getValue($form_state, 'gigya_application_key'));
		$config->set('gigya.gigya_api_key', $this->getValue($form_state, 'gigya_api_key'));
		$config->set('gigya.gigya_auth_mode', $this->getValue($form_state, 'gigya_auth_mode'));
		$temp_access_key = $this->getValue($form_state, 'gigya_application_secret_key');
		if (!empty($temp_access_key) && $temp_access_key !== "*********") {
			$enc = $this->helper->enc($temp_access_key);
			$config->set('gigya.gigya_application_secret_key', $enc);
			$config->set('gigya.gigya_rsa_private_key', '');
		}
		$private_key = $this->getValue($form_state, 'gigya_rsa_private_key');
		if (!empty($private_key)) {
			$private_key = $this->helper->enc($private_key);
			$config->set('gigya.gigya_rsa_private_key', $private_key);
			$config->set('gigya.gigya_application_secret_key', '');
		}

		if ($this->getValue($form_state, 'gigya_data_center') == 'other') {
			$config->set('gigya.gigya_data_center', $this->getValue($form_state, 'gigya_other_data_center'));
		}
		else {
			$config->set('gigya.gigya_data_center', $this->getValue($form_state, 'gigya_data_center'));
		}

		$config->save();
		parent::submitForm($form, $form_state);
	}

	/**
	 * @param FormStateInterface $form_state
	 * @param                    $prop_name
	 *
	 * @return string
	 */
	private function getValue(FormStateInterface $form_state, $prop_name) {
		return trim($form_state->getValue($prop_name));
	}
}
