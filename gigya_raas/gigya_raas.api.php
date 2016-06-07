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
 * Modify the data gigya_global_parameters before it is added to the js.
 *
 * @param $gigya_global_parameters
 *   An field map arrays.
 */
function hook_gigya_raas_settings_alter(array &$raas_login, array &$raas_register) {
  $raas_login['screenSet'] = 'RegistrationLogin-A';
}


/**
 * @} End of "addtogroup hooks".
 */
