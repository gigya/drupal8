<?php

namespace Drupal\gigya\CmsStarterKit;

use Gigya\PHP\GSException;
use Gigya\PHP\GSRequest;
use Gigya\PHP\GSResponse;

class GigyaAuthRequest extends GSRequest {

  /**
   * GSApiRequest constructor.
   *
   * @param string $apiKey
   * @param string $privateKey
   * @param string $apiMethod
   * @param \Gigya\PHP\GSObject $params
   * @param string $dataCenter
   * @param bool $useHTTPS
   * @param string|null $userKey
   *
   * @throws \Gigya\PHP\GSKeyNotFoundException
   */
  public function __construct($apiKey, $privateKey, $apiMethod, $params, $dataCenter, $useHTTPS = TRUE, $userKey = NULL) {
    parent::__construct($apiKey, NULL, $apiMethod, $params, $useHTTPS, $userKey, $privateKey);
    $this->setAPIDomain($dataCenter);
  }

  /**
   * @param int $timeout
   *
   * @return \Gigya\PHP\GSResponse
   *
   * @throws \Gigya\PHP\GSException
   * @throws GSApiException
   * @throws \Gigya\PHP\GSKeyNotFoundException
   */
  public function send($timeout = NULL): GSResponse {
    $res = parent::send($timeout);

    if ($res->getErrorCode() == 0) {
      return $res;
    }

    if (!empty($res->getData())) { /* Actual error response from Gigya */
      throw new GSApiException($res->getErrorMessage(), $res->getErrorCode(), $res->getResponseText(), $res->getString("callId", "N/A"));
    }
    else { /* Hard-coded error in PHP SDK, or another failure */
      throw new GSException($res->getErrorMessage(), $res->getErrorCode());
    }
  }

}
