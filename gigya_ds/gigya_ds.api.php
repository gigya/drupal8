<?php
/**
 *
 * This is an example of how to fetch ds data only for the current user uid.
 *
 */
function fetch_ds_data_only() {
  global $user;
  $gigya_uid = \Drupal::service('entity.repository')
    ->loadEntityByUuid('user', $user->id());
  if (!empty($gigya_uid)) {
    $ds_data = gigya_ds_get_data($gigya_uid);
  }

}

/**
 * The following example add a value to the ds data array.
 *
 * @param $ds_data
 */
function hook_gigya_ds_data_alter(&$ds_data) {
  //  $ds_data['type']['field'] = "d";
}
