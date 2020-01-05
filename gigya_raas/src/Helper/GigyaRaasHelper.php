<?php

namespace Drupal\gigya_raas\Helper;

class GigyaRaasHelper {
	public static function getSessionConfig($type = 'regular') {
		if ($type == 'remember_me') {
			$session_type = \Drupal::config('gigya_raas.settings')->get('gigya_raas.remember_me_session_type');
			$session_time = \Drupal::config('gigya_raas.settings')->get('gigya_raas.remember_me_session_time');
		}
		else {
			$session_type = \Drupal::config('gigya_raas.settings')->get('gigya_raas.session_type');
			$session_time = \Drupal::config('gigya_raas.settings')->get('gigya_raas.session_time');
		}

		return [
			'type' => $session_type,
			'time' => $session_time,
		];
	}
}