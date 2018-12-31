<?php
namespace Drupal\gigya_raas\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class GigyaSessionForm extends ConfigFormBase
{
	/**
	 * @param $form_state
	 * @param $prop_name
	 * @return string
	 */
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
			'gigya_raas.settings',
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
	 * @param array $form
	 * @param \Drupal\Core\Form\FormStateInterface $form_state
	 *
	 * @return    array
	 */
	public function buildForm(array $form, FormStateInterface $form_state) {
		$form = parent::buildForm($form, $form_state);
		$config = $this->config('gigya_raas.settings');
		$form['session_type'] = array(
			'#type'          => 'radios',
			'#title'         => $this->t('Session Type'),
			'#description'   => $this->t('If you choose “Fixed”, the user session lasts for the duration specified below. If you choose “Dynamic”, the user session lasts the specified duration, and restarts with every server-side interaction.'),
			'#options'       => array('fixed' => $this->t('Fixed'), 'dynamic' => $this->t('Dynamic')),
			'#default_value' => $config->get('gigya_raas.session_type'),
		);
		$form['session_time'] = array(
			'#type'          => 'textfield',
			'#title'         => $this->t('Session Duration (in seconds)'),
			'#description'   => $this->t('The session is led by Gigya. For more information visit <a href="@Gigya documentation"><u>Gigya\'s documentation</u></a>.', array('@Gigya documentation' => 'https://developers.gigya.com/display/GD/GConnector+-+CMS+and+E-Commerce+Integrations')),
			'#default_value' => $config->get('gigya_raas.session_time'),
		);
		$form['login_redirect'] = array(
			'#type'          => 'textfield',
			'#title'         => $this->t('Post login redirect URL'),
			'#description'   => $this->t('A relative URI path or full URL to redirect the user after a successful login.'),
			'#default_value' => $config->get('gigya_raas.login_redirect'),
		);
		$form['logout_redirect'] = array(
			'#type'          => 'textfield',
			'#title'         => $this->t('Post logout redirect URL'),
			'#description'   => $this->t('A relative URI path or full URL to redirect the user after a successful logout.'),
			'#default_value' => $config->get('gigya_raas.logout_redirect'),
		);

		return $form;
	}

	public function submitForm(array &$form, FormStateInterface $form_state) {
		$config = $this->config('gigya_raas.settings');
		$config->set('gigya_raas.session_type', $this->getValue($form_state, 'session_type'));
		$config->set('gigya_raas.session_time', $this->getValue($form_state, 'session_time'));
		$config->set('gigya_raas.login_redirect', $this->getValue($form_state, 'login_redirect'));
		$config->set('gigya_raas.logout_redirect', $this->getValue($form_state, 'logout_redirect'));
		$config->save();

		parent::submitForm($form, $form_state);
	}
}