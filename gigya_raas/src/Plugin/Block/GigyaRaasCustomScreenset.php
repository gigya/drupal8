<?php
/**
 * Provides a 'Gigya RaaS Custom Screen-Set' Block
 *
 * @Block(
 *   id = "gigya_raas_custom_screenset",
 *   admin_label = @Translation("Gigya RaaS Custom Screen-Set"),
 *   category = @Translation("Gigya")
 * )
 */

namespace Drupal\gigya_raas\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

class GigyaRaasCustomScreenset extends BlockBase implements BlockPluginInterface {

	protected $gigya_config;

	public function __construct(array $configuration, $plugin_id, $plugin_definition) {
		parent::__construct($configuration, $plugin_id, $plugin_definition);

		$this->gigya_config = \Drupal::config('gigya_raas.screensets');
	}

	/**
	 * {@inheritdoc}
	 */
	public function build() {
		/* Get block config */
		$config = $this->getConfiguration();

		/* Get Gigya config */
		$screenset_params = array();
		$screenset_config = json_decode($this->gigya_config->get('gigya.custom_screensets'));
		foreach ($screenset_config as $screenset_config_item) {
			$screenset = (array)$screenset_config_item;
			if (!empty($screenset['desktop']) and ($screenset['desktop'] == $config['desktop_screenset'])) {
				$screenset_params = $screenset;
			}
		}

		$link = array();
		if ($config['display_type'] == 'popup') {
			$url = Url::fromUserInput("#");
			$url->setOptions(array(
				'attributes' => array(
					'class' => $config['link_class'],
					'id' => $config['link_id'],
				),
				'fragment' => $config['container_id'],
			));
			$link['popup'] = Link::fromTextAndUrl($config['label'], $url);
		}

		$build['block'] = [
			'#theme' => 'gigya_raas_custom_screenset_block',
			'#link' => $link,
			'#display_type' => $config['display_type'],
			'#container_id' => $config['container_id'],
			'#screenset' => $screenset_params,
		];
		return $build;
	}

	/**
	 * {@inheritdoc}
	 */
	public function blockForm($form, FormStateInterface $form_state) {
		$form = parent::blockForm($form, $form_state);

		/* Get configs from two different sources */
		$block_config = $this->getConfiguration();
		$screenset_config = json_decode($this->gigya_config->get('gigya.custom_screensets'));

		/* Convert screen-set config to acceptable format */
		$desktop_screensets = array();
		foreach ($screenset_config as $screenset_config_item) {
			$screenset = (array)$screenset_config_item;
			if (!empty($screenset['desktop'])) {
				$desktop_screensets[$screenset['desktop']] = $screenset['desktop'];
			}
		}

		$form['container_id'] = array(
			'#type' => 'textfield',
			'#title' => 'Container ID',
			'#required' => true,
			'#default_value' => isset($block_config['container_id']) ? $block_config['container_id'] : '',
		);

		$display_options = array('popup' => $this->t('Popup'), 'embed' => $this->t('Embed'));
		$form['display_type'] = array(
			'#type' => 'select',
			'#title' => $this->t('Display Type'),
			'#description' => $this->t('Controls whether the screen-set will show up directly on the page (embed) or as a link with a modal popup (popup).'),
			'#options' => $display_options,
			'#default_value' => (isset($block_config['display_type']) and array_key_exists($block_config['display_type'], $display_options)) ? $block_config['display_type'] : 'embed'
		);

		$form['link_id'] = array(
			'#type' => 'textfield',
			'#title' => 'Link ID',
			'#default_value' => isset($block_config['link_id']) ? $block_config['link_id'] : '',
			'#states' => array(
				'visible' => array(
					':input[name="settings[display_type]"]' => array('value' => 'popup'),
				),
				'required' => array(
					':input[name="settings[display_type]"]' => array('value' => 'popup'),
				),
			),
		);

		$form['link_class'] = array(
			'#type' => "textfield",
			'#title' => 'Link CSS Class',
			'#default_value' => isset($block_config['link_class']) ? $block_config['link_class'] : '',
			'#states' => array(
				'visible' => array(
					':input[name="settings[display_type]"]' => array('value' => 'popup'),
				),
			),
		);

		$form['desktop_screenset'] = array(
			'#type' => 'select',
			'#title' => $this->t('Desktop Screen-Set'),
			'#options' => $desktop_screensets,
			'#default_value' => (isset($block_config['desktop_screenset']) and array_key_exists($block_config['desktop_screenset'], $desktop_screensets)) ? $block_config['desktop_screenset'] : '',
		);

		return $form;
	}

	/**
	 * {@inheritdoc}
	 */
	public function blockSubmit($form, FormStateInterface $form_state) {
		parent::blockSubmit($form, $form_state);
		$values = $form_state->getValues();

		$this->configuration['display_type'] = $values['display_type'];
		$this->configuration['desktop_screenset'] = $values['desktop_screenset'];
		$this->configuration['container_id'] = $values['container_id'];
		$this->configuration['link_id'] = $values['link_id'];
		$this->configuration['link_class'] = $values['link_class'];
	}
}
