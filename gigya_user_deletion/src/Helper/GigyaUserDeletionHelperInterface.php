<?php

namespace Drupal\gigya_user_deletion\Helper;

/**
 *
 */
interface GigyaUserDeletionHelperInterface {

  /**
   *
   */
  public function getFileList();

  /**
   *
   */
  public function loadFileFromServer(string $fileName);

  /**
   *
   */
  public function getUsers($fileName);

  /**
   *
   */
  public function sendEmail($subject, $body, $to);

  /**
   *
   */
  public function getRegion();

}
