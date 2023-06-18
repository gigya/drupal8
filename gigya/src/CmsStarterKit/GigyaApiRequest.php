<?php

namespace Drupal\gigya\CmsStarterKit;

use Gigya\PHP\GSException;
use Gigya\PHP\GSRequest;
use Gigya\PHP\GSResponse;

/**
 *
 */
class GigyaApiRequest extends GSRequest {

  /**
   * @param null $timeout
   *
   * @return GSResponse
   *
   * @throws GSException
   * @throws GSApiException
   */
  public function send($timeout = NULL): GSResponse {
    $res = parent::send($timeout);
    if ($res->getErrorCode() == 0) {
      return $res;
    }

    if (!empty($res->getData())) {
      throw new GSApiException($res->getErrorMessage(), $res->getErrorCode(), $res->getResponseText(), $res->getString("callId", "N/A"));
    }
    else {
      throw new GSException($res->getErrorMessage(), $res->getErrorCode());
    }
  }

  /**
   * GSRequestNg constructor.
   *
   * @param string $apiKey
   * @param string $secret
   * @param string $apiMethod
   * @param \Gigya\PHP\GSObject $params
   * @param string $dataCenter
   * @param bool $useHTTPS
   * @param null $userKey
   *
   * @throws \Gigya\PHP\GSKeyNotFoundException
   */
  public function __construct($apiKey, $secret, $apiMethod, $params, $dataCenter, $useHTTPS = TRUE, $userKey = NULL) {
    parent::__construct($apiKey, $secret, $apiMethod, $params, $useHTTPS, $userKey);
    $this->setAPIDomain($dataCenter);
  }

}
