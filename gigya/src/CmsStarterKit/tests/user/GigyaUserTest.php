<?php

include_once 'GigyaJsonObject.php';
include_once 'user/GigyaProfile.php';
include_once 'user/GigyaUser.php';
include_once 'user/GigyaUserFactory.php';

use Drupal\gigya\CmsStarterKit\user\GigyaUserFactory;
use Gigya\PHP\GSObject;


class GigyaUserTest extends PHPUnit_Framework_TestCase {


  public function testCreateGigyaUserFromJson() {
    $json      = file_get_contents(
      __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR
      . "resources/account.json"
    );
    $gigyaUser = GigyaUserFactory::createGigyaUserFromJson($json);
    $this->assertEquals(
      "9b792cd0d4df4c9d938402ea793f33e6", $gigyaUser->getUID, "checking UID"
    );
    $this->assertTrue($gigyaUser->getIsActive(), "Checking active");
  }

  /**
   * @throws \Gigya\PHP\GSException
   */
  public function testCreateGigyaUserFromArray() {
    $json      = file_get_contents(
      __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR
      . "resources/account.json"
    );
    $gso       = new GSObject($json);
    $gigyaUser = GigyaUserFactory::createGigyaUserFromArray(
      $gso->serialize()
    );
    $this->assertEquals(
      "9b792cd0d4df4c9d938402ea793f33e6", $gigyaUser->getUID, "checking UID"
    );
    $this->assertTrue($gigyaUser->getIsActive(), "Checking active");

  }

  public function testGetNestedValue() {
    $json      = file_get_contents(
      __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR
      . "resources/account.json"
    );
    $gigyaUser = GigyaUserFactory::createGigyaUserFromJson($json);
    $this->assertEquals(
      'ibm', $gigyaUser->getNestedValue('profile.work.company'),
      "Testing get from profile"
    );
    $this->assertEquals(
      'true', $gigyaUser->getNestedValue('data.TSN.myTsnEmailEnabled'),
      "Test getting from data"
    );
    $this->assertEquals(
      39.97569274902344,
      $gigyaUser->getNestedValue('lastLoginLocation.coordinates.lat'),
      "Test getting from account"
    );
  }


  public function testAgeGetter() {
    $json      = file_get_contents(
      __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR
      . "resources/account.json"
    );
    $gigyaUser = GigyaUserFactory::createGigyaUserFromJson($json);
    $profile   = $gigyaUser->getProfile();
    $this->assertEquals(33, $profile->getAge());
  }

  public function testMagicGetterAndSetters() {
    $json      = file_get_contents(
      __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR
      . "resources/account.json"
    );
    $gigyaUser = GigyaUserFactory::createGigyaUserFromJson($json);
    $randKey   = $this->generateRandomString(4);
    $randVal   = $this->generateRandomString(7);
    $gigyaUser->__set($randKey, $randVal);
    $key = "get" . ucfirst($randKey);
    $this->assertEquals($randVal, $gigyaUser->$key);
  }

  public function testMagicGetterAndSettersOnProfile() {
    $json      = file_get_contents(
      __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR
      . "resources/account.json"
    );
    $gigyaUser = GigyaUserFactory::createGigyaUserFromJson($json);
    $profile   = $gigyaUser->getProfile();
    $randKey   = $this->generateRandomString(4);
    $randVal   = $this->generateRandomString(7);
    $profile->__set($randKey, $randVal);
    $key = "get" . ucfirst($randKey);
    $this->assertEquals($randVal, $profile->$key);
  }

  public function testNonExistentProperty() {
    $json      = file_get_contents(
      __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR
      . "resources/account.json"
    );
    $gigyaUser = GigyaUserFactory::createGigyaUserFromJson($json);
    $randKey   = $this->generateRandomString(4);
    $key       = "get" . ucfirst($randKey);
    $this->assertEquals(NULL, $gigyaUser->$key);
  }

  private function generateRandomString($length = 10) {
    return substr(
      str_shuffle(
        str_repeat(
          $x = 'abcdefghijklmnopqrstuvwxyz',
          ceil($length / strlen($x))
        )
      ), 1, $length
    );
  }

}
