<?php
	/**
	 * Created by PhpStorm.
	 * User: Yan Nasonov
	 * Date: 21/11/2017
	 * Time: 12:02
	 */

	use Drupal\Tests\BrowserTestBase;

	class GigyaUserDeletionTest extends BrowserTestBase
	{
		public function setUp() {
			parent::setUp();
		}

		public function testDelete() {
			self::assertContains(4, [1, 2, 3, 4]);
		}
	}