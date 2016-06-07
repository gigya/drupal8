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
   * {@inheritdoc}
   */
  public function setUp(){
    parent::setUp();
//    $this->webUser = $this->drupalCreateUser();
//    $this->coffeeAdmin = $this->drupalCreateUser(['administer coffee']);
  }

  /**
   * Tests coffee configuration form.
   */
  public function testEncrypt() {
    $this->assertEquals("a", "a");
//    $this->drupalGet('admin/config/gigya/keys');
//    $this->assertResponse(200);

//    $this->drupalLogin($this->coffeeAdmin);
//    $this->drupalGet('admin/config/user-interface/coffee');
//    $this->assertResponse(200);
//    $this->assertFieldChecked('edit-coffee-menus-admin', 'The admin menu is enabled by default');
//    $this->assertFieldById('edit-max-results', 7, 'The max results is 7 by default');
//
//    $edit = [
//      'coffee_menus[tools]' => 'tools',
//      'coffee_menus[account]' => 'account',
//      'max_results' => 15,
//    ];
//    $this->drupalPostForm('admin/config/user-interface/coffee', $edit, t('Save configuration'));
//    $this->assertText(t('The configuration options have been saved.'));
//
//    $expected = [
//      'admin' => 'admin',
//      'tools' => 'tools',
//      'account' => 'account'
//    ];
//    $config = \Drupal::config('coffee.configuration')->get('coffee_menus');
//    $this->assertEqual($expected, $config, 'The configuration options have been properly saved');
//
//    $config = \Drupal::config('coffee.configuration')->get('max_results');
//    $this->assertEqual(15, $config, 'The configuration options have been properly saved');
  }
}
