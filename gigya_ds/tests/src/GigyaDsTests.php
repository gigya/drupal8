<?php
/**
 * Created by PhpStorm.
 * User: Inbal.Zi
 * Date: 11/19/2017
 * Time: 2:41 PM
 *
 *  @file
 * Contains \Drupal\Tests\gigya_ds\src\GigyaDsTests.
 */


namespace Drupal\modules\gigya\gigya_ds\tests;

use Drupal;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\gigya_raas\GigyaController;
use Drupal\Tests\BrowserTestBase;
use Drupal\gigya\CmsStarterKit\GSApiException;
use Drupal\gigya\CmsStarterKit\user\GigyaUserFactory;
use Gigya\PHP\GSResponse;

/**
 * Tests Gigya module functionality.
 *
 * @group gigya
 */
class GigyaTest extends BrowserTestBase
{

    private $gigyaControl = "";

    private $requestMock = "";

    private $successResponse = "";

    /**
     * Modules to enable.
     *
     * @var array
     */
    public static $modules = array('gigya', 'gigya_raas', 'gigya_ds');

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
    protected $gigyaHelper;


    protected $helperMock;

    protected $gigyaUser;

    /**
     * {@inheritdoc}
     */
    public function setUp(){
        parent::setUp();

        $this->successResponse = new AjaxResponse();
        $this->successResponse->addCommand(new RedirectCommand("/"));

        $this->requestMock = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->getMock();

        $this->gigyaAdmin = $this->drupalCreateUser(['gigya major admin', 'bypass gigya raas']);
        $this->helperMock = $this->getMockBuilder('\Drupal\gigya\Helper\GigyaHelper')
            ->setMethods(array('getEncryptKey', 'checkEncryptKey', 'sendApiCall', 'validateUid'))
            ->getMock();

        $method = "accounts.getAccountInfo";
        $json = '{
  "UID": "_guid_-SPzNo7usObOUAUKT0KC-yijanD1CTN4n-syLkRjj5k=",
  "UIDSignature": "cTXiwusSZP3b/VpAZ9ik2PDvAI0=",
  "signatureTimestamp": "1465860265",
  "loginProvider": "googleplus",
  "isRegistered": true,
  "isActive": true,
  "isLockedOut": false,
  "isVerified": true,
  "iRank": 99.5700,
  "loginIDs": {
    "emails": [
      "perelman.yuval@gmail.com",
      "perelman.y.uval@gmail.com",
      "perel.man.y.uval@gmail.com"
    ],
    "unverifiedEmails": []
  },
  "emails": {
    "verified": [
      "perelman.yuval@gmail.com"
    ],
    "unverified": [
      "perelman.y.uval@gmail.com",
      "perel.man.y.uval@gmail.com"
    ]
  },
  "socialProviders": "googleplus,site",
  "profile": {
    "education": [],
    "work": [
      {
        "endDate": "2014",
        "isCurrent": false
      }
    ],
    "firstName": "abc3",
    "lastName": "aaaa",
    "photoURL": "https://lh6.googleusercontent.com/-N_wH0sdrS3Q/AAAAAAAAAAI/AAAAAAAAAD8/LBd_BxNlYl8/photo.jpg?sz=500",
    "thumbnailURL": "https://lh6.googleusercontent.com/-N_wH0sdrS3Q/AAAAAAAAAAI/AAAAAAAAAD8/LBd_BxNlYl8/photo.jpg?sz=50",
    "birthYear": 1934,
    "profileURL": "https://plus.google.com/108786506095496543332",
    "country": "Israel",
    "zip": "334445a",
    "gender": "m",
    "age": 81,
    "email": "perel.man.y.uval@gmail.com"
  },
  "identities": [
    {
      "provider": "googleplus",
      "providerUID": "108786506095496543332",
      "isLoginIdentity": true,
      "photoURL": "https://lh6.googleusercontent.com/-N_wH0sdrS3Q/AAAAAAAAAAI/AAAAAAAAAD8/LBd_BxNlYl8/photo.jpg?sz=500",
      "thumbnailURL": "https://lh6.googleusercontent.com/-N_wH0sdrS3Q/AAAAAAAAAAI/AAAAAAAAAD8/LBd_BxNlYl8/photo.jpg?sz=50",
      "firstName": "Yuval",
      "lastName": "Perelman",
      "gender": "m",
      "email": "perelman.yuval@gmail.com",
      "city": "israel",
      "profileURL": "https://plus.google.com/108786506095496543332",
      "proxiedEmail": "",
      "allowsLogin": true,
      "isExpiredSession": false,
      "education": [],
      "work": [{ "endDate" : "2014", "isCurrent" : false }],
      "lastUpdated": "2016-06-13T23:23:52.512Z",
      "lastUpdatedTimestamp": 1465860232512,
      "oldestDataUpdated": "2016-06-13T23:23:52.168Z",
      "oldestDataUpdatedTimestamp": 1465860232168
    },
    {
      "provider": "site",
      "providerUID": "_guid_-SPzNo7usObOUAUKT0KC-yijanD1CTN4n-syLkRjj5k=",
      "isLoginIdentity": false,
      "firstName": "abc3",
      "lastName": "aaaa",
      "age": "81",
      "birthYear": "1934",
      "email": "perel.man.y.uval@gmail.com",
      "country": "Israel",
      "zip": "334445a",
      "allowsLogin": true,
      "isExpiredSession": false,
      "lastUpdated": "2016-05-26T09:38:21.666Z",
      "lastUpdatedTimestamp": 1464255501666,
      "oldestDataUpdated": "2016-05-26T09:38:21.666Z",
      "oldestDataUpdatedTimestamp": 1464255501666
    }
  ],
  "data": {
    "subscribe": true
  },
  "password": {
    "hash": "v/bhhMMvgt0N4rkEn68hfg==",
    "hashSettings": {
      "algorithm": "pbkdf2",
      "rounds": 3000,
      "salt": "Lhuh/MG7gpADy9f+O5rLog=="
    }
  },
  "created": "2016-02-24T13:51:56.843Z",
  "createdTimestamp": 1456321916843,
  "lastLogin": "2016-06-13T23:23:52.200Z",
  "lastLoginTimestamp": 1465860232200,
  "lastUpdated": "2016-06-13T23:23:52.512Z",
  "lastUpdatedTimestamp": 1465860232512,
  "oldestDataUpdated": "2016-05-26T09:38:21.666Z",
  "oldestDataUpdatedTimestamp": 1464255501666,
  "registered": "2016-02-24T13:51:56.905Z",
  "registeredTimestamp": 1456321916905,
  "verified": "2016-02-24T13:51:56.874Z",
  "verifiedTimestamp": 1456321916874,
  "regSource": "",
  "lastLoginLocation": {
    "country": "IL",
    "coordinates": {
      "lat": 31.5,
      "lon": 34.75
    }
  },
  "rbaPolicy": {
    "riskPolicyLocked": false
  },
  "statusCode": 200,
  "errorCode": 0,
  "statusReason": "OK",
  "callId": "fd4e6b449201472694202106fa64ded6",
  "time": "2016-06-13T23:24:25.466Z"
}';

        $res = new GSResponse($method, $json, null, 0, null, array());

        $dataArray = $res->getData()->serialize();
        $profileArray = $dataArray['profile'];
        $gigyaUser    = GigyaUserFactory::createGigyaUserFromArray($dataArray);
        $gigyaProfile = GigyaUserFactory::createGigyaProfileFromArray($profileArray);
        $gigyaUser->setProfile($gigyaProfile);
        $this->gigyaUser = $gigyaUser;



        $this->helperMock
            ->expects($this->any())
            ->method('sendApiCall')
            ->will($this->returnCallback(function($method, $params, $access_params) {
                $aparams = array();
                $aparams['api_key'] = 'apikey';
                $aparams['session_time'] = 'appsecret';
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
                    else if ($access_params['app_secret'] !== $aparams['app_secret']) {
                        $err_number = 403003;
                        $err_message = "Invalid request signature";
                    }
                    else if ($access_params['data_center'] !== $aparams['data_center']) {
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

        $this->gigyaControl = new GigyaController($this->helperMock);
    }

    /**
     * Tests ds.search simple
     */
    public function testDsSearch_simple()
    {
        $email = $this->gigyaUser->getProfile()->getEmail();
        $this->gigyaUser->getProfile()->setEmail($email);
        $res = $this->gigyaControl->gigyaRaasLoginAjax($this->requestMock);
        $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
        $this->assertTrue(\Drupal::currentUser()->isAuthenticated());

        $this->assertEquals($this->successResponse->getCommands(), $res->getCommands());
        $this->assertEquals($this->gigyaUser->getProfile()->getEmail(), $user->getEmail());
    }
}