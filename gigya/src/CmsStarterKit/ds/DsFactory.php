<?php

namespace Drupal\gigya\CmsStarterKit\ds;

class DsFactory {

  private $apiHelper;

  /**
   * DsFactory constructor.
   *
   * @param $helper
   *
   */
  public function __construct($helper) {
    $this->apiHelper;
  }

  public function createDsqFromQuery($query) {
    $dsQueryObj = new DsQueryObject($this->apiHelper);
    $dsQueryObj->setQuery($query);
    return $dsQueryObj;
  }

  public function createDsqFromFields($type, $fields) {
    $dsQueryObj = new DsQueryObject($this->apiHelper);
    $dsQueryObj->setFields($fields);
    $dsQueryObj->setTable($type);
    return $dsQueryObj;

  }

  public function createDsqFromWhere($type, $fields, $where, $op, $value, $valueType = "string") {
    $dsQueryObj = new DsQueryObject($this->apiHelper);
    $dsQueryObj->setFields($fields);
    $dsQueryObj->setTable($type);
    $dsQueryObj->addWhere($where, $op, $value, $valueType);
    return $dsQueryObj;
  }

  public function createDsqFromOid($oid, $type) {
    $dsQueryObj = new DsQueryObject($this->apiHelper);
    $dsQueryObj->setOid($oid);
    $dsQueryObj->setTable($type);
    return $dsQueryObj;
  }
}