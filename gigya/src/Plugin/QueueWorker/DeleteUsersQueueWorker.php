<?php
/**
 * @file
 * Contains \Drupal\gigya\Plugin\QueueWorker\DeleteUsersQueueWorker.
 */

namespace Drupal\gigya\Plugin\QueueWorker;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity;
use Drupal\Core\Database;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\gigya\Helper\GigyaHelper;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Drupal\Component\Utility;

/**
 * Processes tasks for example module.
 *
 * @QueueWorker(
 *   id = "gigya",
 *   title = @Translation("Example: Queue worker"),
 *   cron = {"time" = 90}
 * )
 */
class DeleteUsersQueueWorker extends QueueWorkerBase
{
	public function processItem($item) {
		$account = $item->gigya_uid;
        \Drupal::logger('gigya')->info("processed item");
        $helper = new GigyaHelper();
        $messageSucceed = "User successfully deleted from CMS - UID: ";
        $user = $helper->getUidByUUID($account);
        //If UID found
        if ($user) {
            try {
                //get CMS's uid by Gigya uid
                $CMSuid = $user->get('uid')->value;
                //If it's admin user (user=1) skip
                if ($CMSuid == 1) {
                    \Drupal::logger('gigya')->notice("Skipped deleting admin user");
                } else {
                    //Parameter for hook user_delete - if return false skip delete user
                    $should_del = true;
                    \Drupal::moduleHandler()->alter('delete_user', $user, $should_del);
                    if ($should_del === TRUE) {
                        //Delete user
                        user_delete($CMSuid);
                      //  $counter_succeed++;
                    } else {
                        \Drupal::logger('gigya')->notice("Delete user hook - Skipped deleting user. CMS ID = " . $CMSuid);
                    }
                  //  \Drupal::logger('gigya')->notice($messageSucceed . $gigyaUID);
                }
            }
            catch (Exception $e) {
                //add to logs
                \Drupal::logger('gigya')->error("Failed to delete UID " . $account . " from CMS with error - " . $e->getMessage());
                //increase failed counter to send in email
                //$counter_failed++;
            }
        }
        else {
            //add to logs
            \Drupal::logger('gigya')->error("Failed to delete UID: " . $account . ", UID wasn't found in CMS");
        }
    }
}
