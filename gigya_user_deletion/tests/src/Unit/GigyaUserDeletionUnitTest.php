<?php
	/**
	 * Created by PhpStorm.
	 * User: Yan Nasonov
	 * Date: 23/11/2017
	 * Time: 10:46
	 */

	use Drupal\Tests\UnitTestCase;
	use Drupal\gigya_user_deletion\Helper\GigyaUserDeletionHelper;
	use Drupal\Core\DependencyInjection\ContainerBuilder;
	use Drupal\Core\Logger\LoggerChannelFactory;

	class GigyaUserDeletionUnitTest extends UnitTestCase
	{
		private $configFactory;
		private $configContainer;
		private $loggerFactory;
		private $mailFactory;
		private $config;

		private $coreModuleHelperMock;

		/* Set AWS details here for test */
		/**
		 * @var bool
		 */
		private $testRealAws = true;

		/**
		 * @var string
		 */
		private $secretKey = '';

		/**
		 * @var string
		 */
		private $awsBucketName = '';

		/**
		 * @var string
		 */
		private $awsAccessKey = '';

		/**
		 * @var string
		 */
		private $awsExpectedRegion = '';

		/**
		 * @var string
		 */
		private $awsFileName = '';

		/**
		 * @var string
		 */
		private $awsFolderName = '';

		/**
		 * @var string
		 */
		private $awsFileContents = '';

		public function setUp() {
			/* Service mocks */
			$this->config = $this->getMockBuilder('\Drupal\Core\Config\ImmutableConfig')
				->disableOriginalConstructor()
				->getMock();
			$this->configFactory = $this->getConfigFactoryStub(array(
																   'gigya_user_deletion.job' => array(
																	   'gigya_user_deletion.storageDetails' => array(
																		   'bucketName' => $this->awsBucketName,
																		   'accessKey' => $this->awsAccessKey,
																		   'secretKey' => 'test_key',
																		   'objectKeyPrefix' => $this->awsFolderName,
																	   ),
																   ),
																   'gigya.global' => array(
																	   'gigya.keyPath' => '/path/to/key/file.key',
																   ),
															   ));
			$this->loggerFactory = new LoggerChannelFactory();
			$this->mailFactory = new \Drupal\Tests\Core\Mail\MailManagerTest();
			$this->configContainer = new ContainerBuilder();
			$this->configContainer->set('config.factory', $this->configFactory);
			$this->configContainer->set('logger.factory', $this->loggerFactory);
			$this->configContainer->set('plugin.manager.mail', $this->mailFactory);
			\Drupal::setContainer($this->configContainer);

			/* Gigya Helper mocks */
			$key = $this->secretKey;
			$this->coreModuleHelperMock = $this->getMockBuilder('\Drupal\gigya\Helper\GigyaHelper')
				->setMethods(array('getEncKeyFile', 'decrypt'))
				->getMock();
			$this->coreModuleHelperMock->expects($this->any())->method('getEncKeyFile')->willReturn(false);
			$this->coreModuleHelperMock->expects($this->any())->method('decrypt')->will($this->returnCallback(function() use ($key) {
				return $this->secretKey;
			}));
		}

		public function testGetRegion() {
			$userDeletionHelper = new GigyaUserDeletionHelper($this->coreModuleHelperMock);

			if ($this->testRealAws)
			{
				$expected = $this->awsExpectedRegion;
				$this->assertEquals($expected, $userDeletionHelper->getRegion());
			}
			else
				$this->assertEquals(true, true); // TODO: Implement mock for S3 service
		}

		public function testLoadFileFromServer() {
			$userDeletionHelper = new GigyaUserDeletionHelper($this->coreModuleHelperMock);

			if ($this->testRealAws)
			{
				$expected = $this->awsFileContents;
				$this->assertEquals($expected, $userDeletionHelper->loadFileFromServer($this->awsFolderName . '/' . $this->awsFileName));
			}
			else
				$this->assertEquals(true, true); // TODO: Implement mock for S3 service
		}

		private function uid_csv_parse($csv_file_string) {
			$csv_lines = explode("\n", $csv_file_string);
			array_shift($csv_lines);
			foreach ($csv_lines as $key => $line)
			{
				$csv_lines[$key] = array('UID' => trim($line));
			}
			return $csv_lines;
		}

		public function testGetUsers() {
			$userDeletionHelper = new GigyaUserDeletionHelper($this->coreModuleHelperMock);

			if ($this->testRealAws)
			{
				$expected = $this->uid_csv_parse($this->awsFileContents);
				$this->assertEquals($expected, $userDeletionHelper->getUsers($this->awsFolderName . '/' . $this->awsFileName));
			}
			else
				$this->assertEquals(true, true); // TODO: Implement mock for S3 service
		}

		public function testGetFileList() {
			$userDeletionHelper = new GigyaUserDeletionHelper($this->coreModuleHelperMock);

			if ($this->testRealAws)
			{
				$file_list = $userDeletionHelper->getFileList();
				array_shift($file_list);
				$expected = $this->awsFolderName . '/' . $this->awsFileName;
				$this->assertEquals($expected, $file_list[0]['Key']);
			}
			else
				$this->assertEquals(true, true); // TODO: Implement mock for S3 service
		}
	}
