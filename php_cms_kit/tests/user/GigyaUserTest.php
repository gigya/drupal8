<?php

/**
 * Created by PhpStorm.
 * User: Yaniv Aran-Shamir
 * Date: 4/6/16
 * Time: 4:38 PM
 */

//include_once "../vendor/autoload.php";

class GigyaUserTest extends PHPUnit_Framework_TestCase
{


    public function testCreateGigyaUserFromJson()
    {
        $json = file_get_contents(__DIR__ . "/../resources/account.json");
        $gigyaUser = gigya\user\GigyaUserFactory::createGigyaUserFromJson($json);
        $this->assertEquals("9b792cd0d4df4c9d938402ea793f33e6", $gigyaUser->getUID, "checking UID");
        $this->assertTrue($gigyaUser->getIsActive(), "Checking active");
    }

    public function testCreateGigyaUserFromArray()
    {
        $json = file_get_contents(__DIR__ . "/../resources/account.json");
        $gso = new \gigya\sdk\GSObject($json);
        $gigyaUser = \gigya\user\GigyaUserFactory::createGigyaUserFromArray($gso->serialize());
        $this->assertEquals("9b792cd0d4df4c9d938402ea793f33e6", $gigyaUser->getUID, "checking UID");
        $this->assertTrue($gigyaUser->getIsActive(), "Checking active");
        
    }


}
