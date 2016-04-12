<?php
/**
 * Created by PhpStorm.
 * User: Yaniv Aran-Shamir
 * Date: 4/6/16
 * Time: 5:09 PM
 */

namespace gigya\user;


use gigya\user\GigyaProfile;

class GigyaUserFactory
{
    static function createGigyaUserFromJson($json) {
        $gigyaArray = json_decode($json);
        return self::createGigyaUserFromArray($gigyaArray);
    }

    static function createGigyaUserFromArray($array) {
        $gigyaUser = new GigyaUser();
        foreach ($array as $key => $value) {
            $gigyaUser->__set($key, $value);
        }
        $profileArray = $array['profile'];
        $gigyaProfile = self::createGigyaProfileFromArray($profileArray);
        $gigyaUser->setProfile($gigyaProfile);
        return $gigyaUser;
    }
    
    static function createGigyaProfileFromJson($json) {
        $gigyaArray = json_decode($json);
        return self::createGigyaProfileFromArray($gigyaArray);
    }
    
    static function createGigyaProfileFromArray($array) {
        $gigyaProfile = new GigyaProfile();
        foreach ($array as $key => $value) {
            $gigyaProfile->__set($key, $value);
        }
        return $gigyaProfile;
    }

}