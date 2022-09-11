<?php
namespace Drupal\gigya_raas\Form;

use Drupal;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gigya\Helper\GigyaHelper;
use Drupal\gigya\Helper\GigyaHelperInterface;

class GigyaSessionForm extends ConfigFormBase {

  /**
   * @param $form_state
   * @param $prop_name
   * @param $helper
   *
   * @return string
   */

  public $helper = FALSE;

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
  public function buildForm(array $form, FormStateInterface $form_state, GigyaHelperInterface $helper = NULL) {

    if ($helper == NULL) {
      $this->helper = new GigyaHelper();
    }
    else {
      $this->helper = $helper;
    }

    if (!$this->helper->checkEncryptKey()) {
      $messenger = Drupal::service('messenger');
      $messenger->addWarning($this->t('Define Gigya\'s encryption key: Go to Gigya\'s general settings, copy the key below and place it in the setting.php file as "gigya_encryption_key".'));
    }

      $form                 = parent::buildForm( $form, $form_state );
      $config               = $this->config( 'gigya_raas.settings' );
      $sessions_types       = [
        'fixed'               => $this->t( 'Fixed' ),
        'dynamic'             => $this->t( 'Dynamic' ),
        'forever'             => $this->t( 'Valid Forever' ),
        'until_browser_close' => $this->t( 'Until browser closes' ),
      ];
      $form['session_type'] = [
        '#type'          => 'select',
        '#title'         => $this->t( 'Regular Session Type' ),
        '#description'   => $this->t( 'If you choose "Fixed", the user session lasts for the duration specified below. If you choose “Dynamic”, the user session lasts the specified duration, and restarts with every server-side interaction.' ),
        '#options'       => $sessions_types,
        '#default_value' => $config->get( 'gigya_raas.session_type' ),
      ];

      $form['session_time'] = [
        '#type'          => 'textfield',
        '#title'         => $this->t( 'Regular Session Duration (in seconds)' ),
        '#description'   => $this->t( 'The session is led by Gigya. For more information visit <a href="@Gigya documentation"><u>Gigya\'s documentation</u></a>.', [ '@Gigya documentation' => 'https://help.sap.com/docs/SAP_CUSTOMER_DATA_CLOUD/8b8d6fffe113457094a17701f63e3d6a/4157d5d370b21014bbc5a10ce4041860.html?q=%2FGConnector%20CMS%2Band%2BE-' ] ),
        '#default_value' => $config->get( 'gigya_raas.session_time' ),
        '#states'        => [
          'visible' => [
            ':input[name="session_type"]' => [
              [ 'value' => 'fixed' ],
              'or',
              [ 'value' => 'dynamic' ],
            ],
          ],
        ],
      ];

      $form['session_section_remember_me'] = [
        '#type' => 'html_tag',
        '#tag'  => 'hr',
      ];

      $form['remember_me_session_type'] = [
        '#type'          => 'select',
        '#title'         => $this->t( 'Remember Me Session Type' ),
        '#description'   => $this->t( 'If you choose "Fixed", the user session lasts for the duration specified below. If you choose “Dynamic”, the user session lasts the specified duration, and restarts with every server-side interaction.' ),
        '#options'       => $sessions_types,
        '#default_value' => $config->get( 'gigya_raas.remember_me_session_type' ),
      ];
      $form['remember_me_session_time'] = [
        '#type'          => 'textfield',
        '#title'         => $this->t( 'Remember Me Session Duration (in seconds)' ),
        '#default_value' => $config->get( 'gigya_raas.remember_me_session_time' ),
        '#states'        => [
          'visible' => [
            ':input[name="remember_me_session_type"]' => [
              [ 'value' => 'fixed' ],
              'or',
              [ 'value' => 'dynamic' ],
            ],
          ],
        ],
      ];

      $form['session_section_redirection'] = [
        '#type' => 'html_tag',
        '#tag'  => 'hr',
      ];

      $form['login_redirect_mode'] = [
        '#type'          => 'select',
        '#title'         => $this->t( 'Post login redirect' ),
        '#options'       => [
          'current' => $this->t( 'Current path' ),
          'custom'  => $this->t( 'Custom path' ),
        ],
        '#default_value' => $config->get( 'gigya_raas.login_redirect_mode' ),
      ];

      $form['login_redirect']  = [
        '#type'          => 'textfield',
        '#title'         => $this->t( 'Post login redirect URL' ),
        '#description'   => $this->t( 'A relative URI path or full URL to redirect the user after a successful login.' ),
        '#default_value' => $config->get( 'gigya_raas.login_redirect' ),
        '#states'        => [
          'visible' => [
            ':input[name="login_redirect_mode"]' => [ 'value' => 'custom' ],
          ],
        ],
      ];
      $form['logout_redirect'] = [
        '#type'          => 'textfield',
        '#title'         => $this->t( 'Post logout redirect URL' ),
        '#description'   => $this->t( 'A relative URI path or full URL to redirect the user after a successful logout.' ),
        '#default_value' => $config->get( 'gigya_raas.logout_redirect' ),
      ];

      return $form;
    }

    /**
     * @param array $form
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *
     * @throws \Exception
     */
    public
    function validateForm( array &$form, FormStateInterface $form_state ) {
      parent::validateForm( $form, $form_state );

      $session_time             = $form_state->getValue( 'session_time' );
      $remember_me_session_time = $form_state->getValue( 'remember_me_session_time' );

      $minimum_session_time = 61;
      if ( intval( $session_time ) < $minimum_session_time or intval( $remember_me_session_time ) < $minimum_session_time ) {
        $form_state->setErrorByName( 'gigya-raas-sessions', $this->t( 'Session durations should be at least ' . $minimum_session_time . ' seconds.' ) );
      }
    }

    public
    function submitForm( array &$form, FormStateInterface $form_state ) {
      $config = $this->config( 'gigya_raas.settings' );
      $config->set( 'gigya_raas.session_type', $this->getValue( $form_state, 'session_type' ) );
      $config->set( 'gigya_raas.session_time', $this->getValue( $form_state, 'session_time' ) );
      $config->set( 'gigya_raas.remember_me_session_type', $this->getValue( $form_state, 'remember_me_session_type' ) );
      $config->set( 'gigya_raas.remember_me_session_time', $this->getValue( $form_state, 'remember_me_session_time' ) );
      $config->set( 'gigya_raas.login_redirect_mode', $this->getValue( $form_state, 'login_redirect_mode' ) );
      $config->set( 'gigya_raas.login_redirect', $this->getValue( $form_state, 'login_redirect' ) );
      $config->set( 'gigya_raas.logout_redirect', $this->getValue( $form_state, 'logout_redirect' ) );
      $config->save();

      parent::submitForm( $form, $form_state );
    }

}
