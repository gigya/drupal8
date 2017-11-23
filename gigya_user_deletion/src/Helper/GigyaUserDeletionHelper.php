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
	use Drupal\gigya\Helper\GigyaHelper;

	class GigyaUserDeletionHelper implements GigyaUserDeletionHelperInterface
	{
		/**
		 * Function connect to S3 and retrieves all files in bucket (name only)
		 */
		public function getFileList() {
			try
			{
				$secretKey = '';
				$storageDetails = \Drupal::config('gigya.job')->get('gigya.storageDetails');
				$helper = new GigyaHelper();
				$bucketName = $storageDetails['bucketName'];
				$accessKey = $storageDetails['accessKey'];
				$secretKeyEnc = $storageDetails['secretKey'];
				if (!empty($secretKeyEnc))
				{
					$secretKey = $helper->decrypt($secretKeyEnc);
				}
				$objectKeyPrefix = $storageDetails['objectKeyPrefix'] . "/";
				$region = $this->getRegion();
				$s3Client = S3Client::factory(array(
												  'key' => $accessKey,
												  'secret' => $secretKey,
												  'signature' => 'v4',
												  'region' => $region,
											  ));

				/* Max of 15 files */
				$response = $s3Client->listObjects(array(
													   'Bucket' => $bucketName,
													   'MaxKeys' => 15,
													   'Prefix' => $objectKeyPrefix)
				);
				return $files = $response->getPath('Contents');
			}
			catch (S3Exception $e)
			{
				\Drupal::logger('gigya_user_deletion')->error("Failed to get files list from S3 server. Error: " . $e->getMessage());
				return false;
			}
			catch (\Exception $e)
			{
				\Drupal::logger('gigya_user_deletion')->error("Missing required parameter. Error code: " . $e->getCode() . ". Message: " . $e->getMessage());
				return false;
			}
		}

		/**
		 * Function return file content
		 *
		 * @param    array $file_name File name
		 *
		 * @return    bool                File content
		 */
		public function loadFileFromServer($file_name) {
			/* Get S3 connection details from DB */
			$secretKey = '';
			$storageDetails = \Drupal::config('gigya.job')->get('gigya.storageDetails');
			$helper = new GigyaHelper();
			$bucketName = $storageDetails['bucketName'];
			$accessKey = $storageDetails['accessKey'];
			$secretKeyEnc = $storageDetails['secretKey'];
			if (!empty($secretKeyEnc))
			{
				$secretKey = $helper->decrypt($secretKeyEnc);
			}
			$region = $this->getRegion();
			$s3Client = S3Client::factory(array(
											  'key' => $accessKey,
											  'secret' => $secretKey,
											  'region' => $region,
											  'signature' => 'v4',
										  ));

			/* Read file from S3 */
			try
			{
				$result = $s3Client->getObject(array(
												   'Bucket' => $bucketName,
												   'Key' => $file_name,
											   ));
				$body = $result->get('Body');
				$body->rewind();
				$content = $body->read($result['ContentLength']);
				return $content;
			}
			catch (S3Exception $e)
			{
				\Drupal::logger('gigya_user_deletion')->error("Failed to get file from S3 server - " . $e->getMessage());
				return false;
			}
		}

		/**
		 * Parse file content to array of Gigya UIDs
		 *
		 * @param    $fileName
		 *
		 * @return array | null
		 */
		public function getUsers($fileName) {
			$file = $this->loadFileFromServer($fileName);
			if ($file !== null)
			{
				$array = array_map('str_getcsv', explode("\n", $file));
				array_walk($array, function(&$a) use ($array) {
					$a = array_combine($array[0], $a);
				});
				array_shift($array); /* Remove column header */
				return $array;
			}
			return null;
		}

		/**
		 * Send email
		 *
		 * @param    $subject
		 * @param    $body
		 * @param    $to
		 */
		public function sendEmail($subject, $body, $to) {
			$mailManager = \Drupal::service('plugin.manager.mail');
			$module = 'gigya_user_deletion';
			$params['from'] = 'Gigya IdentitySync';
			$params['subject'] = $subject;
			$params['message'] = $body;
			$key = 'job_email';
			$langcode = \Drupal::currentUser()->getPreferredLangcode();
			if (!$langcode)
			{
				$langcode = 'en';
			}
			try
			{
				foreach (explode(",", $to) as $email)
				{
					$result = $mailManager->mail($module, $key, $email, $langcode, $params, null, $send = true);
					if (!$result)
					{
						\Drupal::logger('gigya_user_deletion')->error("Failed to send email to " . $email);
					}
				}
			}
			catch (\Exception $e)
			{
				\Drupal::logger('gigya_user_deletion')->error("Failed to send emails - " . $e->getMessage());
			}
		}

		/**
		 * Get S3 Region
		 *
		 * @return string | false
		 */
		public function getRegion() {
			//Get S3 connection details from DB
			$secretKey = "";
			$storageDetails = \Drupal::config('gigya.job')->get('gigya.storageDetails');
			$helper = new GigyaHelper();
			$bucketName = $storageDetails['bucketName'];
			$accessKey = $storageDetails['accessKey'];
			$secretKeyEnc = $storageDetails['secretKey'];
			//decrypt S3 secret
			if (!empty($secretKeyEnc))
			{
				$secretKey = $helper->decrypt($secretKeyEnc);
			}
			$s3Client = S3Client::factory(array(
											  'key' => $accessKey,
											  'secret' => $secretKey,
										  ));
			try
			{
				$response = $s3Client->GetBucketLocation(array('Bucket' => $bucketName,));
				return $response->get('Location');
			}
			catch (S3Exception $e)
			{
				\Drupal::logger('gigya_user_deletion')->error("Failed to get region from S3 server - " . $e->getMessage());
				return false;
			}
		}
	}