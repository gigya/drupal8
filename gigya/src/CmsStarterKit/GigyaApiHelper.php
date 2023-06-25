<?php

namespace Drupal\gigya\CmsStarterKit;

use Exception;
use Gigya\PHP\GSException;
use Gigya\PHP\GSObject;
use Gigya\PHP\GSResponse;
use Gigya\PHP\JWTUtils;
use Gigya\PHP\SigUtils;
use Drupal\gigya\CmsStarterKit\user\GigyaUserFactory;

class GigyaApiHelper {
  private string $userKey;
  private string $authKey;
  private string $authMode;
  private string $apiKey;
  private string $dataCenter;
  private $defConfigFilePath;

  const IV_SIZE = 16;

  /**
   * GigyaApiHelper constructor.
   *
   * @param string $apiKey
   *   Gigya API key.
   * @param string $userKey
   *   Gigya app/user key.
   * @param string $authKey
   *   Gigya app/user secret or RSA private key.
   * @param string $dataCenter
   *   Gigya data center.
   * @param string $authMode
   *   Authentication method: user_secret or user_rsa.
   */
  public function __construct(string $apiKey, string $userKey, string $authKey, string $dataCenter, string $authMode = 'user_secret') {
    $this->defConfigFilePath = ".." . DIRECTORY_SEPARATOR . "configuration/DefaultConfiguration.json";
    $defaultConf             = @file_get_contents($this->defConfigFilePath);
    if (!$defaultConf) {
      $confArray = [];
    }
    else {
      $confArray = json_decode(file_get_contents($this->defConfigFilePath) !== NULL ? : '');
    }
    $this->userKey  = ($userKey ?? $confArray['appKey']) ?? '';
    $this->authMode = $authMode;
    if ($authMode === 'user_secret') {
      $this->authKey = $authKey ?? ((!empty($confArray['appSecret'])) ? self::decrypt($confArray['appSecret']) : '');
    }
    else {
      $this->authKey = $authKey;
    }

    $this->apiKey     = ($apiKey ?? $confArray['apiKey']) ?? '';
    $this->dataCenter = ($dataCenter ?? $confArray['dataCenter']) ?? '';
  }

  /**
   * @param string         $method
   * @param array|GSObject $params
   *
   * @return GSResponse
   *
   * @throws GSApiException
   * @throws GSException
   * @throws Exception
   */
  public function sendApiCall(string $method, $params): GSResponse {
    if ($this->authMode === 'user_rsa') {
      $req = GSFactory::createGSRequestPrivateKey($this->apiKey, $this->userKey, $this->authKey, $method,
        GSFactory::createGSObjectFromArray($params), $this->dataCenter);
    }
    else {
      $req = GSFactory::createGSRequestAppKey($this->apiKey, $this->userKey, $this->authKey, $method,
        GSFactory::createGSObjectFromArray($params), $this->dataCenter);
    }

    return $req->send();
  }

  /**
   * Validate and get Gigya user.
   *
   * @param string $uid
   * @param string $uidSignature
   * @param $signatureTimestamp
   * @param $include
   * @param $extraProfileFields
   * @param array $orgParams
   *
   * @return \Drupal\gigya\CmsStarterKit\user\GigyaUser|false
   *
   * @throws Exception
   * @throws GSApiException
   */
  public function validateUid(string $uid, string $uidSignature, $signatureTimestamp, $include = NULL, $extraProfileFields = NULL, array $orgParams = []) {
    $params                       = $orgParams;
    $params['UID']                = $uid;
    $params['UIDSignature']       = $uidSignature;
    $params['signatureTimestamp'] = $signatureTimestamp;
    $res                          = $this->sendApiCall("socialize.exchangeUIDSignature", $params);
    $sig                          = $res->getData()->getString("UIDSignature", NULL);
    $sigTimestamp                 = $res->getData()->getString("signatureTimestamp", NULL);

    if (NULL !== $sig && NULL !== $sigTimestamp) {
      if (SigUtils::validateUserSignature($uid, $sigTimestamp, $this->authKey, $sig)) {
        return $this->fetchGigyaAccount($uid, $include, $extraProfileFields, $orgParams);
      }
    }

    return FALSE;
  }

  /**
   * @param string      $uid
   * @param string      $idToken
   *
   * @param string|NULL $include
   * @param             $extraProfileFields
   * @param             $orgParams
   *
   * @return \Drupal\gigya\CmsStarterKit\user\GigyaUser|false
   *
   * @throws Exception|GSApiException
   */
  public function validateJwtAuth(string $uid, string $idToken, string $include = NULL, $extraProfileFields = NULL, $orgParams = NULL) {
    $jwt = JWTUtils::validateSignature($idToken, $this->apiKey, $this->dataCenter);

    if ($jwt && !empty($jwt->sub) && $jwt->sub === $uid) {
      return $this->fetchGigyaAccount($uid, $include, $extraProfileFields, $orgParams);
    }
    else {
      return FALSE;
    }
  }

  /**
   * @param string $uid
   *   UID.
   * @param string $include
   *   Fields to include in the response.
   * @param string $extraProfileFields
   *   Profile fields to include in the response.
   * @param array $params
   *   Params.
   *
   * @return \Drupal\gigya\CmsStarterKit\user\GigyaUser
   *
   * @throws Exception
   * @throws GSApiException
   */
  public function fetchGigyaAccount($uid, $include = NULL, $extraProfileFields = NULL, $params = []) {
    if (NULL === $include) {
      $include = 'identities-active,identities-all,identities-global,loginIDs,emails,profile,data,password,isLockedOut,'
      . 'lastLoginLocation,regSource,irank,rba,subscriptions,userInfo,preferences';
    }

    if (NULL === $extraProfileFields) {
      $extraProfileFields = 'languages,address,phones,education,educationLevel,honors,publications,patents,certifications,'
      . 'professionalHeadline,bio,industry,specialties,work,skills,religion,politicalView,interestedIn,relationshipStatus,'
      . 'hometown,favorites,followersCount,followingCount,username,name,locale,verified,timezone,likes,samlData';
    }

    $params['UID']                = $uid;
    $params['include']            = $include;
    $params['extraProfileFields'] = $extraProfileFields;

    $res       = $this->sendApiCall('accounts.getAccountInfo', $params);
    $dataArray = $res->getData()->serialize();

    $profileArray = $dataArray['profile'];
    $gigyaUser    = GigyaUserFactory::createGigyaUserFromArray($dataArray);
    $gigyaProfile = GigyaUserFactory::createGigyaProfileFromArray($profileArray);
    $gigyaUser->setProfile($gigyaProfile);

    return $gigyaUser;
  }

  /**
   * Queries Gigya with the accounts.search call.
   *
   * @param string|array $query
   *   The literal query to send to accounts.search, or a set of params to send instead (useful for cursors)
   * @param bool $useCursor
   *
   * @return \Drupal\gigya\CmsStarterKit\user\GigyaUser[]
   *
   * @throws GSApiException
   * @throws GSException
   */
  public function searchGigyaUsers($query, $useCursor = FALSE) {
    $gigyaUsers = [];

    if (is_array($query)) {/* Query is actually a set of params. Useful for setting cursor ID instead of query */
      $params = $query;
    }
    else {
      $params = ['query' => $query];
      if ($useCursor) {/* openCursor in Gigya only supports true but not false */
        $params['openCursor'] = TRUE;
      }
    }

    $gigyaData = $this->sendApiCall('accounts.search', $params)->getData()->serialize();

    foreach ($gigyaData['results'] as $userData) {
      if (isset($userData['profile'])) {
        $profileArray = $userData['profile'];
        $gigyaUser    = GigyaUserFactory::createGigyaUserFromArray($userData);
        $gigyaProfile = GigyaUserFactory::createGigyaProfileFromArray($profileArray);
        $gigyaUser->setProfile($gigyaProfile);

        $gigyaUsers[] = $gigyaUser;
      }
    }

    if (!empty($gigyaData['nextCursorId'])) {
      $cursorId = $gigyaData['nextCursorId'];
      return array_merge($gigyaUsers, $this->searchGigyaUsers(['cursorId' => $cursorId]));
    }

    return $gigyaUsers;
  }

  /**
   * Send all the Gigya data for the user specified by the UID.
   *
   * Data format example :
   * Array
   *    (
   *        [UID] => 60b1084f2ee846b883e84d6183575f71
   *        [data] => Array
   *            (
   *                [hasChildren] => 1
   *                [age] => 40
   *             )
   *        [isVerified] => 1
   *        [profile] => Array
   *            (
   *                [gender] => u
   *                [nickname] => Test6
   *            )
   *        [subscriptions] => Array
   *            (
   *                [demo] => Array
   *                    (
   *                        [email] => Array
   *                            (
   *                                [isSubscribed] => 1
   *                                [tags] => Array
   *                                    (
   *                                        [0] => test1
   *                                        [1] => test3
   *                                    )
   *                            )
   *                    )
   *            )
   *    )
   *
   * @param string $uid
   *   UID.
   * @param array $data
   *   data.
   *
   * @throws Exception
   * @throws \InvalidArgumentException
   * @throws GSApiException
   */
  public function updateGigyaAccount($uid, $data) {
    if (empty($uid)) {
      throw new \InvalidArgumentException('UID cannot be empty');
    }

    $paramsArray['UID'] = $uid;
    $paramsArray        = array_merge($paramsArray, $data);

    $jsonObjects = ['data', 'preferences', 'profile', 'subscriptions', 'rba'];
    foreach ($jsonObjects as $jsonObject) {
      if (isset($paramsArray[$jsonObject])) {
        if (empty($paramsArray[$jsonObject])) {
          unset($paramsArray[$jsonObject]);
        }
        elseif (is_array($paramsArray[$jsonObject])) {
          array_walk_recursive($paramsArray[$jsonObject], function (&$value) {
            $value = strval($value);
          });
          $paramsArray[$jsonObject] = json_encode($paramsArray[$jsonObject]);
        }
      }
    }

    $this->sendApiCall('accounts.setAccountInfo', $paramsArray);
  }

  /**
   * @throws Exception
   * @throws GSApiException
   */
  public function getSiteSchema() {
    $params = GSFactory::createGSObjectFromArray(["apiKey" => $this->apiKey]);
    $res = $this->sendApiCall("accounts.getSchema", $params);

    return $res;
  }

  /**
   * @param null $apiKey
   *
   * @return bool
   *
   * @throws Exception
   * @throws GSException
   */
  public function isRaasEnabled($apiKey = NULL) {
    if (NULL === $apiKey) {
      $apiKey = $this->apiKey;
    }
    $params = GSFactory::createGSObjectFromArray(["apiKey" => $apiKey]);
    try {
      $this->sendApiCall("accounts.getGlobalConfig", $params);

      return TRUE;
    }
    catch (GSApiException $e) {
      if ($e->getErrorCode() == 403036) {
        return FALSE;
      }

      throw new GSException($e->getMessage(), $e->getCode());
    }
  }

  public function queryDs($uid, $table, $fields) {

  }

  public function userObjFromArray($user_arr) {
    $obj = GigyaUserFactory::createGigyaUserFromArray($user_arr);

    return $obj;
  }

  /***
   * Static .***/

  /**
   * @param string $str
   * @param null | string $key
   *
   * @return string
   */
  public static function decrypt(string $str, $key = NULL): string {
    if (NULL == $key) {
      $key = getenv("KEK");
    }
    if (!empty($key)) {
      $strDec        = base64_decode($str);
      $iv            = substr($strDec, 0, self::IV_SIZE);
      $text_only     = substr($strDec, self::IV_SIZE);
      $plaintext_dec = openssl_decrypt($text_only, 'AES-256-CBC', $key, 0, $iv);

      return $plaintext_dec;
    }

    return $str;
  }

  /**
   * @param string $str
   * @param null | string $key
   *
   * @return string
   */
  public static function enc(string $str, $key = NULL): string {
    if (NULL == $key) {
      $key = getenv("KEK");
    }
    $iv    = openssl_random_pseudo_bytes(self::IV_SIZE);
    $crypt = openssl_encrypt($str, 'AES-256-CBC', $key, 0, $iv);

    return trim(base64_encode($iv . $crypt));
  }

  /**
   * Alias of enc()
   *
   * @param string $str
   * @param null | string $key
   *
   * @return string
   */
  public static function encrypt($str, $key = NULL) {
    return GigyaApiHelper::enc($str, $key);
  }

  /**
   * @param null | string $str
   *
   * @return mixed
   */
  public static function genKeyFromString($str = NULL, $size = 32) {
    if (NULL == $str) {
      $str = openssl_random_pseudo_bytes($size);
    }
    $salt = openssl_random_pseudo_bytes($size);
    $key  = hash_pbkdf2("sha256", $str, $salt, 1000, $size);

    return $key;
  }

}
