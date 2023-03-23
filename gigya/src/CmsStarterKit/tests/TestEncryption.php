<?php
use Drupal\gigya\CmsStarterKit\GigyaApiHelper;
use PHPUnit\Framework\TestCase;

/**
	 * Created by PhpStorm.
	 * User: Yaniv Aran-Shamir
	 * Date: 4/7/16
	 * Time: 8:47 PM
	 */
	class TestEncryption extends TestCase
	{
		private $key;

		public function testEnc() {
			$toEnc = "testing testing 123";
			$encStr = GigyaApiHelper::enc($toEnc, $this->key);
			$decStr = GigyaApiHelper::decrypt($encStr, $this->key);
			$this->assertEquals($toEnc, trim($decStr));
		}

		protected function setUp(): void {
			$this->key = GigyaApiHelper::genKeyFromString("testGenKey");
		}
	}
