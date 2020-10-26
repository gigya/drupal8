<?php

namespace Drupal\gigya_raas\EventSubscriber;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Database\Database;
use Drupal\Core\TempStore\TempStoreException;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\gigya_raas\Helper\GigyaRaasHelper;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GigyaRaasEventSubscriber implements EventSubscriberInterface {

	/**
	 * Session management logic on each page load or action
	 *
	 * @param $event
	 */
	public function onLoad($event) {
		/* Get necessary parameters from outside the class */
		/** @var PrivateTempStore $gigya_raas_session */
		$gigya_raas_session = \Drupal::service('tempstore.private')->get('gigya_raas');
		$drupal_session = \Drupal::service('session');
		$current_user = \Drupal::currentUser();
		$uid = $current_user->id();

		/* Update DB with session expiration if this hasn't been done before ('session_registered' flag) */
		$is_remember_me = intval($gigya_raas_session->get('session_is_remember_me'));
		$cached_session_expiration = $gigya_raas_session->get('session_expiration');
		if (($gigya_raas_session->get('session_registered') === FALSE) and $cached_session_expiration) {
			Database::getConnection()
				->query('UPDATE {sessions} SET expiration = :expiration, is_remember_me = :is_remember_me WHERE uid = :uid AND sid = :sid',
					[':expiration' => $cached_session_expiration, ':is_remember_me' => $is_remember_me, ':uid' => $uid, ':sid' => Crypt::hashBase64($drupal_session->getId())]);

			try {
				$gigya_raas_session->set('session_registered', TRUE);
			} catch (TempStoreException $e) {
				/* This could lead to some issues, but it should be updated on the next request (probably same-page), therefore no error is necessary */
			}
		}

		/* User is logged in through Gigya */
		if ($uid and !$current_user->hasPermission('bypass gigya raas')) {
			$session_type = ($is_remember_me) ? 'remember_me' : 'regular';
			$session_params = GigyaRaasHelper::getSessionConfig($session_type);

			if ($session_params['type'] === 'dynamic') {
				$this->handleDynamicSession($session_params, $gigya_raas_session, $uid);
			}
			elseif ($session_params['type'] === 'fixed') {
				$this->handleFixedSession($gigya_raas_session, $drupal_session, $uid);
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getSubscribedEvents() {
		$events[KernelEvents::REQUEST][] = ['onLoad', 28]; /* Priority of dynamic cache is 25 or 27, so the priority here needs to be above 27 */

		return $events;
	}

	/**
	 * @param array $session_params
	 * @param PrivateTempStore $gigya_raas_session
	 * @param int $uid
	 */
	private function handleDynamicSession($session_params, $gigya_raas_session, $uid) {
		$cached_session_expiration = $gigya_raas_session->get('session_expiration');
		$new_session_expiration = time() + $session_params['time'];
		$prev_session_expiration = (empty($cached_session_expiration) or $cached_session_expiration == -1) ? $new_session_expiration : $cached_session_expiration;

		if ($prev_session_expiration < time()) {
			user_logout();
		}

		try {
			$gigya_raas_session->set('session_expiration', $new_session_expiration);
		} catch (TempStoreException $e) {
			/* This could lead to some issues, but it should be updated on the next request (probably same-page), therefore no error is necessary */
		}
	}

	/**
	 * @param PrivateTempStore $gigya_raas_session
	 * @param SessionInterface $drupal_session
	 * @param int $uid
	 */
	public function handleFixedSession($gigya_raas_session, $drupal_session, $uid) {
		$cached_session_expiration = $gigya_raas_session->get('session_expiration');
		$session_expiration = NULL;

		/* Session expiration is "cached" to reduce DB requests. It can only be empty under "fixed session" */
		if (empty($cached_session_expiration)) {
			$error_message = 'Gigya session information could not be retrieved from the database. It is likely that the Gigya RaaS module has not been installed correctly. Please attempt to reinstall it. Attempted to retrieve details for user ID: ' . $uid;

			try {
				$session_expiration_row = Database::getConnection()->query('SELECT expiration, is_remember_me FROM {sessions} s WHERE s.uid = :uid AND s.sid = :sid', [
					':uid' => $uid,
					':sid' => Crypt::hashBase64($drupal_session->getId()),
				])->fetchAssoc();
				if (!isset($session_expiration_row['expiration'])) { /* Query succeeded but didn't return any expiration column (should never happen!) */
					\Drupal::logger('gigya_raas')->error($error_message);
					user_logout();
				}
				else {
					if ($session_expiration_row['expiration'] < time()) {
						user_logout();
					} else {
						$gigya_raas_session->set('session_expiration', $session_expiration_row['expiration']);
						$gigya_raas_session->set('session_is_remember_me', $session_expiration_row['is_remember_me']);
					}
				}
			} catch (\Exception $e) {
				\Drupal::logger('gigya_raas')->error($error_message . PHP_EOL . 'Exception of type: ' . get_class($e) . ', exception error message: ' . $e->getMessage());
				user_logout();
			}
		}
		/* Right after logging in, the session expiration exists, but isn't yet written to the DB--but by the time this request is executed, it is already written, so it's possible to update the DB. */
		else {
			if ($cached_session_expiration < time()) {
				user_logout();
			}
		}
	}
}
