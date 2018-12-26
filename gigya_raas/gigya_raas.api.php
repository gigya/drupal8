<?php

/**
 * @file
 * Documentation for Gigya raas module APIs.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Modify the raas settings before it is added to the js.
 *
 * @param $raas_login
 *   array with raas login params
 * @param $raas_register
 *   array with raas register params
 */
function hook_gigya_raas_settings_alter(array &$raas_login, array &$raas_register) {
  $raas_login['screenSet'] = 'RegistrationLogin-A';
}

/**
 * Modify the data gigya_global_parameters before it is added to the js.
 *
 * @param $raas_profile
 *   An field map arrays.
 */
function hook_gigya_raas_profile_settings_alter(array &$raas_profile) {
  $raas_profile['screenSet'] = 'Default-ProfileUpdate';
}

/**
 * @} End of "addtogroup hooks".
 */