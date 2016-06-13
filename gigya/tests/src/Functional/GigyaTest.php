<?php

/**
 * @file
 * Contains \Drupal\Tests\gigya\Functional\GigyaTest.
 */

namespace Drupal\Tests\gigya\Functional;

use Drupal;
use Drupal\Core\Form\FormState;
use Drupal\simpletest\BrowserTestBase;
use Gigya\GigyaApiHelper;
use Gigya\sdk\GSApiException;
use Gigya\sdk\GSObject;
use Gigya\sdk\GSResponse;

/**
 * Tests Gigya module functionality.
 *
 * @group gigya
 */
class GigyaTest extends BrowserTestBase {


  private $key = "24c370c0d169a482ae1c5db1932b4b29";

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['gigya'];

  /**
   * The user for tests.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * The user for tests.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $gigyaAdmin;


  protected $helperMock;
  /**
   * {@inheritdoc}
   */
  public function setUp(){
    parent::setUp();
    $this->gigyaAdmin = $this->drupalCreateUser(['gigya major admin']);
    $this->helperMock = $this->getMockBuilder('\Drupal\gigya\Helper\GigyaHelper')
                          ->setMethods(array('getEncryptKey', 'checkEncryptKey', 'sendApiCall'))
                          ->getMock();
    $this->helperMock->expects($this->any())->method('checkEncryptKey')->will($this->returnValue(TRUE));
    $this->helperMock->expects($this->any())->method('getEncryptKey')->will($this->returnValue($this->key));

    $this->helperMock
      ->expects($this->any())
      ->method('sendApiCall')
      ->will($this->returnCallback(function($method, $params, $access_params) {
        $aparams = array();
        $aparams['api_key'] = 'apikey';
        $aparams['app_secret'] = 'appsecret';
        $aparams['app_key'] = 'appkey';
        $aparams['data_center'] = 'us1.gigya.com';
        if ($access_params !== $aparams) {
          if ($access_params['api_key'] !== $aparams['api_key']) {
            $err_number = 400093;
            $err_message = "Invalid ApiKey parameter";
          }
          else if ($access_params['app_key'] !== $aparams['app_key']) {
            $err_number = 403005;
            $err_message = "Unauthorized user";
          }
          else if ($access_params['app_key'] !== $aparams['app_key']) {
            $err_number = 403003;
            $err_message = "Invalid request signature";
          }
          else if ($access_params['app_key'] !== $aparams['app_key']) {
            $err_number = 301001;
            $err_message = "Invalid data center";
          }

          $res = new GSApiException($err_message, $err_number, $err_message);
        }
        else if($method == 'shortenURL') {
          $responseStr = '{"shortURL": "http://fw.to/8WgRfqE","statusCode": 200,"errorCode": 0,"statusReason": "OK","callId": "968875481ea94aadb8dc146a7165926c","time": "2016-06-09T13:07:20.861Z"}';
          $res = new GSResponse('shortenURL', $responseStr);

        }

        return $res;
      }));
  }

  /**
   * Tests encrypt.
   */
  public function testEncrypt() {
//    1. Go to Drupal admin site (not user 1), Gigya settings not with admin user without Gigya Role permission (create new permission group)
//       Expected: secret key text box is hidden

    $this->drupalGet('admin/config/gigya/keys');
    $this->assertSession()->statusCodeEquals('403');
    $this->drupalLogin($this->gigyaAdmin);
//    2. Give the user permission in Gigya Role and load Gigya settings page
//       Expected: 1. secret keys is visible.

    $this->drupalGet('admin/config/gigya/keys');
    $this->assertSession()->statusCodeEquals('200');
//    $this->assertSession()->elementExists('css', '#edit-gigya-application-secret-key');
//    $this->assertSession()->fieldValueEquals('edit-gigya-application-secret-key', '*********');


    //    $config = Drupal::service('config.factory')->getEditable('gigya.settings')->set('gigya.gigya_application_secret_key', 'a');
//    $config->save();

//  3. Set Gigya apikey, user app and secret and DC and save settings
//     Expected: Settings saved secret encrypt on DB and in logs doesnt appear


    $form_state = new FormState();
    $values['gigya_api_key'] = 'apikey';
    $values['gigya_application_key'] = 'appkey';
    $values['gigya_application_secret_key'] = 'appsecret';
    $values['gigya_data_center'] = 'us1.gigya.com';
    $form_state->setValues($values);

    \Drupal::formBuilder()->submitForm('Drupal\gigya\Form\GigyaKeysForm', $form_state, $this->helperMock);

    $key = Drupal::service('config.factory')->getEditable('gigya.settings')->get('gigya.gigya_application_secret_key');
    $this->assertNotEquals($values['gigya_application_secret_key'], $key, 'Key is not encrypted');

    $this->drupalGet('admin/config/gigya/keys');
    $this->assertSession()->statusCodeEquals('200');
    $this->assertSession()->elementExists('css', '#edit-gigya-application-secret-key');
    $this->assertSession()->fieldValueEquals('edit-gigya-application-secret-key', '*********');

  }

}
