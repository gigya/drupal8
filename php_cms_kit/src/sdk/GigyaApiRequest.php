<?php

namespace gigya\sdk;
/**
 * Created by PhpStorm.
 * User: Yaniv Aran-Shamir
 * Date: 1/4/16
 * Time: 4:59 PM
 */

class GigyaApiRequest extends GSRequest

{
	public function send( $timeout = null ) {
		$res = parent::send( $timeout );
		if ($res->getErrorCode() == 0) {
			return $res;
		}
		throw new GSApiException($res->getErrorMessage(), $res->getErrorCode(), $res->getResponseText(), $res->getString("callId", "N/A"));
	}

	/**
	 * GSRequestNg constructor.
	 *
	 * @param string   $apiKey
	 * @param string   $secretKey
	 * @param string   $apiMethod
	 * @param GSObject $params
	 * @param bool     $useHTTPS
	 * @param null     $userKey
	 */
	public function __construct( $apiKey, $secretKey, $apiMethod, $params, $dataCenter, $useHTTPS = true, $userKey = null ) {
		parent::__construct( $apiKey, $secretKey, $apiMethod, $params, $useHTTPS, $userKey );
        $this->setAPIDomain($dataCenter);
		$this->setCAFile("../sdk/cacert.pem");
	}
}