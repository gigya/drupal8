<?php

/**
 * @file
 * Contains \Drupal\Tests\gigya\Functional\GigyaTest.
 */

namespace Drupal\Tests\gigya\Functional;

use Drupal\simpletest\BrowserTestBase;

/**
 * Tests Gigya module functionality.
 *
 * @group gigya
 */
class GigyaTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['gigya'];

  /**
   * The user for tests.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * The user for tests.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $gigyaAdmin;


  /**
   * {@inheritdoc}
   */
  public function setUp(){
    parent::setUp();
    $this->gigyaAdmin = $this->drupalCreateUser(['gigya major admin']);
  }

  /**
   * Tests coffee configuration form.
   */
  public function testEncrypt() {
    $this->drupalGet('admin/config/gigya/keys');
    $this->assertSession()->statusCodeEquals('403');
    $this->drupalLogin($this->gigyaAdmin);
    $this->assertSession()->statusCodeEquals('200');
  }
}
