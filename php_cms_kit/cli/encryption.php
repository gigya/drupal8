<?php
/**
 * Created by PhpStorm.
 * User: Yaniv Aran-Shamir
 * Date: 4/7/16
 * Time: 3:49 PM
 */

include_once "../src/GigyaApiHelper.php";

if ($argv[1] == "-e") {
    $encStr = \gigya\GigyaApiHelper::enc($argv[2]);

    echo $encStr . PHP_EOL;
} elseif ($argv[1] == "-d") {
    $dec = \gigya\GigyaApiHelper::decrypt($argv[2]);

    echo $dec . PHP_EOL;
} elseif ($argv[1] == "-gen") {
    $str = isset($argv[2]) ? $argv[2] : null;
    $key = \gigya\GigyaApiHelper::genKeyFromString($str);

    echo $key . PHP_EOL;
}