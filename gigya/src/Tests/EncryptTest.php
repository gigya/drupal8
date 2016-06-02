<?php

/**
 * @file
 * Contains \Drupal\gigya\Tests\GigyaTest.
 */


namespace Drupal\gigya\Tests;

use Drupal\simpletest\BrowserTestBase;

/**
 *
 * @group gigya
 */
class GigyaTest extends BrowserTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = array('gigya', 'color_test', 'block', 'file');

}


