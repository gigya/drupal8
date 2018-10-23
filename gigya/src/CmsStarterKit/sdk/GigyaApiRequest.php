<?php

namespace Drupal\gigya\CmsStarterKit\sdk;

class GigyaApiRequest extends GSRequest {
  /**
   * @param null $timeout
   *
   * @return GSResponse
   *
   * @throws \Exception
   * @throws GSApiException
   */
  public function send($timeout = NULL) {
    $res = parent::send($timeout);
    if ($res->getErrorCode() == 0) {
      return $res;
    }

    throw new GSApiException($res->getErrorMessage(), $res->getErrorCode(), $res->getResponseText(), $res->getString("callId", "N/A"));
  }

  /**
   * GSRequestNg constructor.
   *
   * @param string $apiKey
   * @param string $secret
   * @param string $apiMethod
   * @param GSObject $params
   * @param string $dataCenter
   * @param bool $useHTTPS
   * @param null $userKey
   *
   * @throws \Exception
   */
  public function __construct($apiKey, $secret, $apiMethod, $params, $dataCenter, $useHTTPS = TRUE, $userKey = NULL) {
    parent::__construct($apiKey, $secret, $apiMethod, $params, $useHTTPS, $userKey);
    $this->setAPIDomain($dataCenter);
    $this->setCAFile(realpath(dirname(__FILE__) . "/cacert.pem"));
  }
}