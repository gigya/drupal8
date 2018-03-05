<?php
/**
 * @file
 * Documentation for Gigya module APIs.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Modify the data of the raas mapping.
 *
 * @param $gigya_data
 *   An array of all the gigya data.
 *
 * @param $drupal_user
 *   An drupal user object.
 *
 * @param $field_map
 *   An field map arrays.
 */
function hook_gigya_raas_map_data_alter(array &$gigya_data, User &$drupal_user, array &$field_map) {
  $field_map['name2'] = 'profile.name2';
}

/**
 * Modify the data gigya_global_parameters before it is added to the js.
 *
 * @param $gigya_global_parameters
 *   An field map arrays.
 */
function hook_gigya_global_parameters_alter(array &$gigya_global_parameters) {
  $gigya_global_parameters['parm'] = 'val';
}

/**
 * Modify the data gigya_lang before it is added to the js.
 *
 * @param $lang
 *   An field map arrays.
 *
 * @see CKEditorPluginManager
 */
function hook_gigya_lang_alter(&$lang) {
  if ($lang == "en") {
    $lang = "en2";
  }
}



/**
 * @} End of "addtogroup hooks".
 */
