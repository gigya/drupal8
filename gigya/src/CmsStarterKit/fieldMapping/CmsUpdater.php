<?php

namespace Drupal\gigya\CmsStarterKit\fieldMapping;

abstract class CmsUpdater {

  /**
   * @var \Drupal\gigya\CmsStarterKit\user\GigyaUser
   */
  private $gigyaUser;

  private $gigyaMapping;

  /**
   * @var bool
   */
  private $mapped = FALSE;

  private $path;

  /**
   * CmsUpdater constructor
   *
   * @param \Drupal\gigya\CmsStarterKit\User\GigyaUser $gigyaAccount
   * @param string $mappingFilePath
   */
  public function __construct($gigyaAccount, $mappingFilePath) {
    $this->gigyaUser = $gigyaAccount;
    $this->path = (string) $mappingFilePath;
    $this->mapped = !empty($this->path);
  }

  /**
   * @param mixed $cmsAccount
   * @param       $cmsAccountSaver
   *
   * @throws \Drupal\gigya\CmsStarterKit\fieldMapping\CmsUpdaterException
   */
  public function updateCmsAccount(&$cmsAccount, $cmsAccountSaver = NULL) {
    if (!isset($this->gigyaMapping)) {
      $this->retrieveFieldMappings();
    }

    if (method_exists($this, 'callCmsHook')) {
      $this->callCmsHook();
    }
    $this->setAccountValues($cmsAccount);
    $this->saveCmsAccount($cmsAccount, $cmsAccountSaver);
  }

  /**
   * @return boolean
   */
  public function isMapped() {
    return $this->mapped;
  }

  abstract protected function callCmsHook();

  abstract protected function saveCmsAccount(&$cmsAccount, $cmsAccountSaver);

  /**
   * @throws \Drupal\gigya\CmsStarterKit\fieldMapping\CmsUpdaterException
   */
  public function retrieveFieldMappings() {
    if (file_exists($this->path)) {
      $mappingJson = file_get_contents($this->path);
    }
    else {
      throw new CmsUpdaterException("Field Mapping file could not be found at " . $this->path);
    }
    if (FALSE === $mappingJson) {
      $err = error_get_last();
      $message = "Could not retrieve field mapping configuration file. message was:" . $err['message'];
      throw new CmsUpdaterException("$message");
    }
    $conf = new Conf($mappingJson);
    $this->gigyaMapping = $conf->getGigyaKeyed();
  }

  /**
   * @param mixed $account
   */
  abstract protected function setAccountValues(&$account);

  /**
   * @param $path
   *
   * @return \Drupal\gigya\CmsStarterKit\user\GigyaUser|null|string
   */
  public function getValueFromGigyaAccount($path) {
    $userData = $this->getGigyaUser();
    $value = $userData->getNestedValue($path);
    return $value;
  }

  /**
   * @param mixed $value
   * @param ConfItem $conf
   *
   * @return mixed
   */
  protected function castValue($value, $conf) {
    switch ($conf->getCmsType()) {
      case "decimal":
        $value = (float) $value;
        break;
      case "int":
        $value = (int) $value;
        break;
      case "text":
        $value = (string) $value;
        break;
      case "varchar":
        $value = (string) $value;
        break;
      case "bool":
        $value = boolval($value); /* PHP 5.5+ */
        break;
    }

    return $value;
  }

  /**
   * @return string
   */
  public function getPath() {
    return $this->path;
  }

  /**
   * @param string $path
   */
  public function setPath($path) {
    $this->path = $path;
  }

  /**
   * @return \Drupal\gigya\CmsStarterKit\user\GigyaUser
   */
  public function getGigyaUser() {
    return $this->gigyaUser;
  }

  /**
   * @param array $gigyaUser
   */
  public function setGigyaUser($gigyaUser) {
    $this->gigyaUser = $gigyaUser;
  }

  /**
   * @return mixed
   */
  public function getGigyaMapping() {
    return $this->gigyaMapping;
  }

  /**
   * @param mixed $gigyaMapping
   */
  public function setGigyaMapping($gigyaMapping) {
    $this->gigyaMapping = $gigyaMapping;
  }
}
