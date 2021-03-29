<?php
	/**
	 * Created by PhpStorm.
	 * User: Yan Nasonov
	 * Date: 23/11/2017
	 * Time: 11:56
	 */

	namespace Drupal\gigya_user_deletion\Helper;

	use Aws\S3\S3Client;
	use Aws\S3\Exception\S3Exception;
	use Drupal;
	use Drupal\gigya\Helper\GigyaHelper;
	use Exception;

	class GigyaUserDeletionHelper implements GigyaUserDeletionHelperInterface
	{
		private GigyaHelper $helper;

		protected string $region;

		public function __construct($helper = NULL) {
			if ($helper) {
				$this->helper = $helper;
			}
		}

		/**
		 * Function connect to S3 and retrieves all files in bucket (name only)
		 */
		public function getFileList() {
			try
			{
				$secretKey = '';
				$storageDetails = Drupal::config('gigya_user_deletion.job')->get('gigya_user_deletion.storageDetails');
				if (isset($this->helper))
					$helper = $this->helper;
				else
					$helper = new GigyaHelper();
				$bucketName = $storageDetails['bucketName'];
				$accessKey = $storageDetails['accessKey'];
				$secretKeyEnc = $storageDetails['secretKey'];
				if (!empty($secretKeyEnc))
				{
					$secretKey = $helper->decrypt($secretKeyEnc);
				}
				$objectKeyPrefix = (!empty($storageDetails['objectKeyPrefix'])) ? rtrim($storageDetails['objectKeyPrefix'], '/') . '/' : '';
				if (empty($this->region)) {
					$region = $this->region = $this->getRegion();
				} else {
					$region = $this->region;
				}
				$s3Client = new S3Client([
					'credentials' => [
						'key'    => $accessKey,
						'secret' => $secretKey,
					],
					'version'     => 'latest',
					'region'      => $region,
				]);

				/* Max of 15 files */
				$response = $s3Client->listObjects(array(
													   'Bucket' => $bucketName,
													   'MaxKeys' => 15,
													   'Prefix' => $objectKeyPrefix)
				);

				return $response->search('Contents');
			} catch (S3Exception $e) {
				Drupal::logger('gigya_user_deletion')->error('Failed to get the list of files from S3 server. Error: '
					. $e->getMessage());
				return FALSE;
			} catch (Exception $e) {
				Drupal::logger('gigya_user_deletion')
					->error('General error connecting to S3. A possible reason is a missing required parameter. Error code: '
						. $e->getCode() . '. Message: ' . $e->getMessage());
				return FALSE;
			}
		}

		/**
		 * Function return file content
		 *
		 * @param string $fileName File name
		 *
		 * @return bool File content
		 */
		public function loadFileFromServer(string $fileName) {
			/* Get S3 connection details from DB */
			$secretKey      = '';
			$storageDetails = Drupal::config('gigya_user_deletion.job')->get('gigya_user_deletion.storageDetails');
			if (isset($this->helper)) {
				$helper = $this->helper;
			}
			else {
				$helper = new GigyaHelper();
			}
			$bucketName = $storageDetails['bucketName'];
			$accessKey = $storageDetails['accessKey'];
			$secretKeyEnc = $storageDetails['secretKey'];
			if (!empty($secretKeyEnc))
			{
				$secretKey = $helper->decrypt($secretKeyEnc);
			}

			$region = $this->region ?? (($storageDetails['region']) ?? $this->getRegion());

			/* Read file from S3 */
			try {
				$s3Client = new S3Client([
					'credentials' => [
						'key'    => $accessKey,
						'secret' => $secretKey,
					],
					'region'      => $region,
					'version'     => 'latest',
				]);

				$result = $s3Client->getObject([
					'Bucket' => $bucketName,
					'Key'    => $fileName,
				]);
				$body   = $result->get('Body');
				$body->rewind();

				return $body->read($result['ContentLength']);
			} catch (S3Exception $e) {
				Drupal::logger('gigya_user_deletion')->error('Failed to get file from S3 server - ' . $e->getMessage());
				return FALSE;
			} catch (Exception $e) {
				Drupal::logger('gigya_user_deletion')
					->error('Unknown error when trying to get a file from S3. A possible reason is a missing required parameter. Error code: '
						. $e->getCode() . '. Message: ' . $e->getMessage());
				return FALSE;
			}
		}

		/**
		 * Parse file content to array of Gigya UIDs. The final format is:
		 * array ( 0 => array (
		 * 											'UID' => '[GIGYA_UID]'
		 * 										),
		 * 					1 => array (
		 * 											'UID' => '85[GIGYA_UID]'
		 * 											)
		 * 				)
		 *
		 * @param    $fileName
		 *
		 * @return array | null
		 */
		public function getUsers($fileName) {
			$file = $this->loadFileFromServer($fileName);
			$users = [];

			if ($file !== null)
			{
				$raw_users = array_map('str_getcsv', explode("\n", $file));
				$col_header = array_shift($raw_users); /* Retrieve and remove column header (e.g. UID) */

				foreach ($raw_users as $user) {
					if ($user and !empty(array_filter(array_values($user)))) { /* Checks that the user record is not empty--sometimes something like ['gigya_uid' => NULL] is passed */
						$users[] = array_combine($col_header, $user);
					}
				}

				return $users;
			}
			return null;
		}

		/**
		 * Send email
		 *
		 * @param    $subject
		 * @param    $body
		 * @param    $to
		 *
		 * @return bool
		 */
		public function sendEmail($subject, $body, $to) {
			if (!empty($to)) {
				$mailManager = Drupal::service('plugin.manager.mail');
				$module = 'gigya_user_deletion';
				$params['from'] = 'Gigya IdentitySync';
				$params['subject'] = $subject;
				$params['message'] = $body;
				$key = 'job_email';

				try /* For testability */ {
					$langcode = Drupal::currentUser()->getPreferredLangcode();
				} catch (Exception $e) {
					$langcode = 'en';
				}
				if (!isset($langcode)) {
					$langcode = 'en';
				}

				try {
					foreach (explode(',', $to) as $email) {
						$result = $mailManager->mail($module, $key, trim($email), $langcode, $params, NULL, $send = TRUE);
						if (!$result) {
							Drupal::logger('gigya_user_deletion')
								->error('Failed to send email to ' . $email);
						}
					}
				} catch (Exception $e) {
					Drupal::logger('gigya_user_deletion')
						->error('Failed to send emails - ' . $e->getMessage());
					return FALSE;
				}
				return TRUE;
			}
			else {
				Drupal::logger('gigya_user_deletion')
					->warning('Unable to send email with subject: ' . $subject . '. No destination address specified.');
				return FALSE;
			}
		}

		/**
		 * Get S3 Region
		 *
		 * @return string | false
		 */
		public function getRegion() {
			/* Get S3 connection details from DB */
			$storedAwsParams = Drupal::config('gigya_user_deletion.job')->get('gigya_user_deletion.storageDetails');
			$secretKeyEnc = Drupal::config('gigya_user_deletion.job')->get('gigya_user_deletion.storageDetails.secretKey');
			if (isset($this->helper)) {
				$helper = $this->helper;
			}
			else {
				$helper = new GigyaHelper();
			}

			/* Build AWS params */
			$bucketName = $storedAwsParams['bucketName'];
			$awsParams = [
				'credentials' => [
					'key'    => $storedAwsParams['accessKey'],
					'secret' => $storedAwsParams['secretKey'],
				],
				'region'      => 'us-east-1',
				'version'     => 'latest',
			];

			/* Decrypt S3 secret */
			if (!empty($secretKeyEnc))
			{
				$awsParams['credentials']['secret'] = $helper->decrypt($secretKeyEnc);
			}

			try {
				$s3Client = new S3Client($awsParams);
				$response = $s3Client->GetBucketLocation(['Bucket' => $bucketName,]);

				return $response->get('Location') ?? $response->get('LocationConstraint');
			} catch (S3Exception $e) {
				try {
					$awsParams['region'] = $this->getRegionFromAwsError($e->getMessage());
					if (!$awsParams['region']) {
						throw $e;
					}

					$s3Client = new S3Client($awsParams);
					$response = $s3Client->GetBucketLocation(['Bucket' => $bucketName,]);

					Drupal::logger('gigya_user_deletion')
						->warning('AWS S3 region incorrectly set in the cron configuration. We were able to retrieve the region based on your bucket name, but this may stop working in the future. Please configure the AWS region in the Gigya user deletion configuration.');

					return $response->get('Location') ?? $response->get('LocationConstraint');
				} catch (Exception $e) {
					Drupal::logger('gigya_user_deletion')->error('Failed to get region from S3 server - ' . nl2br($e->getMessage()));
					return FALSE;
				}
			}
		}

		/**
		 * @param string $error
		 *
		 * @return false|mixed
		 */
		public function getRegionFromAwsError(string $error) {
			if (preg_match('/the region \'([a-zA-Z0-9-]+)\' is wrong; expecting \'([a-z0-9-]+)\'/', $error, $matches)) {
				return $matches[2];
			}

			return FALSE;
		}
	}
