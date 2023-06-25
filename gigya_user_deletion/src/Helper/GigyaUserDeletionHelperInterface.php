<?php
	/**
	 * Created by PhpStorm.
	 * User: Yan Nasonov
	 * Date: 23/11/2017
	 * Time: 11:50
	 *
	 * @file
	 * Contains \Drupal\gigya_user_deletion\Helper\GigyaUserDeletionHelperInterface.
	 */

	namespace Drupal\gigya_user_deletion\Helper;

	interface GigyaUserDeletionHelperInterface
	{
		public function getFileList();

		public function loadFileFromServer(string $fileName);

		public function getUsers($fileName);

		public function sendEmail($subject, $body, $to);

		public function getRegion();
	}
