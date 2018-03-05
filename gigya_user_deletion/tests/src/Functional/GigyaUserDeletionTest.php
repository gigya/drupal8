<?php
	/**
	 * Created by PhpStorm.
	 * User: Yan Nasonov
	 * Date: 21/11/2017
	 * Time: 12:02
	 */

	use Drupal\Tests\BrowserTestBase;
	use Drupal\Core\Form\FormState;

	class GigyaUserDeletionTest extends BrowserTestBase
	{
		/**
		 * Modules to enable on setUp()
		 *
		 * @var array
		 */
		protected static $modules = ['gigya', 'gigya_raas', 'gigya_user_deletion'];

		/**
		 * @var    \Drupal\user\UserInterface
		 */
		protected $gigyaAdmin;

		/**
		 * {@inheritdoc}
		 */
		public function setUp() {
			parent::setUp();

			$this->gigyaAdmin = $this->drupalCreateUser(['gigya major admin', 'bypass gigya raas']);
		}

		public function testConfigPageUI() {
			/* Checks that admin config page is not accessible without logging in */
			$this->drupalGet('admin/config/gigya/cron');
			$this->assertSession()->statusCodeEquals('403');
			$this->drupalLogin($this->gigyaAdmin);

			/* Same action, only after login */
			$this->drupalGet('admin/config/gigya/cron');
			$this->assertSession()->statusCodeEquals('200');

			/* Form details */
			$form_state = new FormState();
			$values = $this->getSampleFormData();
			$form_state->setValues($values);
			\Drupal::formBuilder()->submitForm('Drupal\gigya_user_deletion\Form\GigyaCronForm', $form_state);

			/* Get page again after values update */
			$this->drupalGet('admin/config/gigya/cron');
			$this->assertSession()->statusCodeEquals('200');

			/* Form UI test */
			$this->assertSession()->elementsCount('css', 'input[type=text]', 7);
			$this->assertSession()->elementsCount('css', 'input[type=checkbox]', 1);
			foreach ($this->getSampleFormData() as $form_key => $form_value)
			{
				$this->assertSession()->elementExists('css', '#edit-'.strtolower($form_key));
			}
			foreach ($this->getSampleFormData() as $form_key => $form_value)
			{
				$this->assertSession()->fieldValueEquals('edit-'.strtolower($form_key), $form_value);
			}
			$this->assertSession()->elementExists('css', '#edit-secretkey');

			$this->drupalLogout();
		}

		public function getSampleFormData() {
			$data = [
				'jobFrequency' => 120,
				'emailOnSuccess' => 'yan.na@gigya-inc.com, yan@mailinator.com',
				'emailOnFailure' => 'yan.na@gigya-inc.com',
				'bucketName' => 'mock-test',
				'accessKey' => 'access-key',
				'objectKeyPrefix' => 'sample-dir',
			];

			return $data;
		}
	}