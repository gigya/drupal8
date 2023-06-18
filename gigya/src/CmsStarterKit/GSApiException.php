<?php

namespace Drupal\gigya\CmsStarterKit;

/**
 *
 */

use Exception;

class GSApiException extends Exception {

  private $longMessage;
  private $callId;

  /**
   * GSApiException constructor.
   *
   * @param string $message
   * @param int $errorCode
   * @param string|NULL $longMessage
   * @param string|NULL $callId
   */
  public function __construct(string $message, $errorCode, string $longMessage = NULL, string $callId = NULL) {
    parent::__construct($message, $errorCode);
    $this->longMessage = $longMessage;
    $this->callId = $callId;
  }

  /**
   * @return string|null
   */
  public function getErrorCode(): string {
    return $this->getCode();
  }

  /**
   * @return string|null
   */
  public function getLongMessage(): ?string {
    return $this->longMessage;
  }

  /**
   * @return string|null
   */
  public function getCallId(): ?string {
    return $this->callId;
  }

}
