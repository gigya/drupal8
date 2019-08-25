<?php

/**
 * @file
 * Contains \Drupal\gigya_raas\Form\GigyaScreenSetsForm.
 */

namespace Drupal\gigya_raas\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class GigyaScreenSetsForm extends ConfigFormBase {

	/**
	 * Gets the configuration names that will be editable.
	 *
	 * @return array
	 *   An array of configuration object names that are editable if called in
	 *   conjunction with the trait's config() method.
	 */
	protected function getEditableConfigNames() {
		return [
			'gigya_raas.screensets',
		];
	}

	/**
	 * @param array $form
	 * @param \Drupal\Core\Form\FormStateInterface $form_state
	 *
	 * @return array
	 */
	public function buildForm(array $form, FormStateInterface $form_state) {
		$config = $this->config('gigya_raas.screensets');

		$form['gigya_login_screensets'] = [
			'#type' => 'details',
			'#title' => $this->t('Registration–Login Screen-Set'),
			'#open' => TRUE,
			'#prefix' => 'After updating the custom screen-sets, clearing the Drupal cache may be required for the changes to apply. In addition, changing a custom screen-set\'s name will not change the displayed screen-set automatically. This requires changing it in the block configuration as well.',
		];

		$form['gigya_login_screensets']['gigya_login_screenset_desktop'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Screen-Set'),
			'#default_value' => $config->get('gigya.login_screenset'),
			'#required' => TRUE,
		];

		$form['gigya_login_screensets']['gigya_login_screenset_mobile'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Mobile Screen-Set'),
			'#default_value' => $config->get('gigya.login_screenset_mobile'),
			'#required' => FALSE,
		];

		$form['gigya_profile_screensets'] = [
			'#type' => 'details',
			'#title' => $this->t('Edit Profile Screen-Set'),
			'#open' => TRUE,
		];

		$form['gigya_profile_screensets']['gigya_profile_screenset_desktop'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Edit Profile Screen-Set'),
			'#default_value' => $config->get('gigya.profile_screenset'),
			'#required' => TRUE,
		];

		$form['gigya_profile_screensets']['gigya_profile_screenset_mobile'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Edit Profile Mobile Screen-Set'),
			'#default_value' => $config->get('gigya.profile_screenset_mobile'),
			'#required' => FALSE,
		];

		$form['gigya_custom_screensets'] = [
			'#type' => 'details',
			'#title' => $this->t('Custom / Consent Screen-Sets'),
			'#open' => TRUE,
		];

		/* Custom screenset stuff */
		{
			if ($form_state->get('field_keys') == '') {
				$form_state->set('field_keys', []);
			}

			$gigya_custom_screensets_serialized = $config->get('gigya.custom_screensets');
			$custom_screensets = [];
			if (!empty($gigya_custom_screensets_serialized)) {
				$custom_screensets = json_decode($gigya_custom_screensets_serialized, TRUE);
			}

			$form['gigya_custom_screensets']['screensets'] = [
				'#type' => 'table',
				'#header' => [
					new FormattableMarkup('<span class="js-form-required form-required">@desktop</span>', ['@desktop' => $this->t('Desktop')]),
					$this->t('Mobile'),
					$this->t('Sync Data ?'),
					'',
				],
				'#tree' => TRUE,
				'#empty' => t('No screen-sets found'),
				'#attributes' => [
					'class' => ['views-table', 'views-view-table', 'cols-6'],
				],
				'#id' => 'custom-screenset-table',
			];

			$custom_screenset_count = count($custom_screensets);
			$existing_form_keys = $form_state->get('existing_field_keys');
			if ($custom_screenset_count > 0 and empty($existing_form_keys)) {
				foreach ($custom_screensets as $key => $screenset) {
					$existing_form_keys[$key] = $key;
				}
				$form_state->set('existing_field_keys', $existing_form_keys);
			}

			if ($custom_screenset_count > 0) {
				/* Builds elements for existing rows (already in DB) */
				foreach ($custom_screensets as $key => $custom_screenset) {
					if (isset($existing_form_keys[$key])) {
						$form['gigya_custom_screensets']['screensets'][$key] = $this->generateCustomScreensetRow($custom_screenset, $key);
					}
				}

				/* Builds elements for new rows (added through Add) */
				foreach ($form_state->get('field_keys') as $field_key) {
					$form['gigya_custom_screensets']['screensets'][$field_key] = $this->generateCustomScreensetRow([
						'',
						'',
						0,
					], $field_key);
				}
			}
			else {
				$form['gigya_custom_screensets']['screensets'][0] = $this->generateCustomScreensetRow([
					'',
					'',
					0,
				], 0);
			}

			$form['gigya_custom_screensets']['add_screenset'] = [
				'#type' => 'submit',
				'#value' => t('Add'),
				'#submit' => ['::addScreensetRow'],
				'#ajax' => [
					'callback' => '::addScreensetRowCallback',
					'wrapper' => 'custom-screenset-table',
				],
			];
		}

		return parent::buildForm($form, $form_state);
	}

	public static function addScreensetRow(array &$form, FormStateInterface $form_state) {
		$existing_field_keys_array = $form_state->get('existing_field_keys');
		if (empty($existing_field_keys_array)) {
			$existing_field_keys_array = [];
		}

		$field_keys_array = $form_state->get('field_keys');
		if (count($field_keys_array) > 0) {
			$field_keys_array[] = max($field_keys_array) + 1;
		}
		elseif (count($existing_field_keys_array) > 0) {
			$field_keys_array[] = max($existing_field_keys_array) + 1;
		}
		else {
			$field_keys_array[] = 0;
		}

		$form_state->set('field_keys', $field_keys_array);

		$form_state->setRebuild();

		$messenger = \Drupal::service('messenger');
		return $messenger->all();
	}

	public static function addScreensetRowCallback(array &$form, FormStateInterface $form_state) {
		return $form['gigya_custom_screensets']['screensets'];
	}

	public static function removeScreensetRow(array &$form, FormStateInterface $form_state) {
		$key_remove = $form_state->getTriggeringElement()['#attributes']['data-screenset-row-serial'];
		$existing_field_keys_array = $form_state->get('existing_field_keys');
		$field_keys_array = $form_state->get('field_keys');

		if (($key_to_remove = array_search($key_remove, $existing_field_keys_array)) !== FALSE) {
			unset($existing_field_keys_array[$key_to_remove]);
		}
		elseif (($key_to_remove = array_search($key_remove, $field_keys_array)) !== FALSE) {
			unset($field_keys_array[$key_to_remove]);
		}

		$form_state->set('existing_field_keys', $existing_field_keys_array);
		$form_state->set('field_keys', $field_keys_array);

		$form_state->setRebuild();

		$messenger = \Drupal::service('messenger');
		return $messenger->all();
	}

	public static function removeScreensetRowCallback(array &$form, FormStateInterface $form_state) {
		return $form['gigya_custom_screensets']['screensets'];
	}

	/**
	 * Returns a unique string identifying the form.
	 *
	 * @return string
	 *   The unique string identifying the form.
	 */
	public function getFormId() {
		return 'gigya_admin_screensets';
	}

	/**
	 * @param array $form
	 * @param \Drupal\Core\Form\FormStateInterface $form_state
	 *
	 * @throws \Exception
	 */
	public function validateForm(array &$form, FormStateInterface $form_state) {
		parent::validateForm($form, $form_state);

		$custom_screensets = $form_state->getValue('screensets');

		/* Verifies that no two screen-sets have the same desktop screen-set */
		$desktop_screens = [];
		foreach ($custom_screensets as $key => $custom_screenset) {
			$desktop_screens[] = $custom_screenset['desktop'];
		}
		if (count(array_unique($desktop_screens)) !== count($desktop_screens)) {
			$form_state->setErrorByName('custom-screenset-table', $this->t('Two screen-sets cannot have the same desktop screen-set identifier'));
		}

		/* Verifies that for each custom screen-set added, either the whole row is empty (then it is ignored), or the desktop screen-set field is set */
		if (empty($form_state->getUserInput()['_triggering_element_name'])) { /* This line is necessary so that validation is only done on save, not add/remove rows where it is irrelevant */
			foreach ($custom_screensets as $key => $custom_screenset) {
				if (empty($custom_screenset['desktop']) and !empty($custom_screenset['mobile'])) {
					$form_state->setErrorByName('screensets][' . $key . '][desktop', $this->t('The desktop screen-set is required for each screen-set row'));
				}
			}
		}
	}

	/**
	 * @param array $form
	 * @param \Drupal\Core\Form\FormStateInterface $form_state
	 */
	public function submitForm(array &$form, FormStateInterface $form_state) {
		$config = $this->config('gigya_raas.screensets');

		$custom_screensets = $form_state->getValue('screensets');
		foreach ($custom_screensets as $key => $custom_screenset) {
			unset($custom_screensets[$key]['remove']);
			if (empty($custom_screenset['desktop'])) {
				unset($custom_screensets[$key]);
			}
		}

		$config->set('gigya.login_screenset', $form_state->getValue('gigya_login_screenset_desktop'));
		$config->set('gigya.login_screenset_mobile', $form_state->getValue('gigya_login_screenset_mobile'));
		$config->set('gigya.profile_screenset', $form_state->getValue('gigya_profile_screenset_desktop'));
		$config->set('gigya.profile_screenset_mobile', $form_state->getValue('gigya_profile_screenset_mobile'));
		$config->set('gigya.custom_screensets', json_encode($custom_screensets, JSON_FORCE_OBJECT));

		$config->save();

		parent::submitForm($form, $form_state);
	}

	/**
	 * Generates the code for a custom screen set row for the form builder
	 *
	 * @param array $custom_screenset The screen set data (desktop, mobile, is
	 *   sync?)
	 * @param int $row_id The row ID for this custom screen-set row (for the
	 *   deletion button mainly)
	 *
	 * @return array
	 */
	private function generateCustomScreensetRow($custom_screenset, $row_id = 0) {
		$screenset_values = array_values($custom_screenset);

		$custom_screenset_row = [
			'desktop' => [
				'#type' => 'textfield',
				'#title' => 'Desktop Screen-Set',
				'#title_display' => 'invisible',
				'#default_value' => $screenset_values[0],
			],
			'mobile' => [
				'#type' => 'textfield',
				'#title' => 'Mobile Screen-Set',
				'#title_display' => 'invisible',
				'#default_value' => $screenset_values[1],
			],
			'sync' => [
				'#type' => 'checkbox',
				'#default_value' => $screenset_values[2],
			],
			'remove' => [
				'#type' => 'submit',
				'#value' => t('Remove'),
				'#submit' => ['::removeScreensetRow'],
				'#ajax' => [
					'callback' => '::removeScreensetRowCallback',
					'wrapper' => 'custom-screenset-table',
				],
				'#id' => 'remove-screenset-row-' . $row_id,
				'#attributes' => [
					'data-screenset-row-serial' => $row_id,
				],
				'#name' => 'remove_screenset_row_' . $row_id,
			],
		];

		return $custom_screenset_row;
	}
}