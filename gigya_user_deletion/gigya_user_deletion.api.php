<?php

/**
 * Modify the data gigya_delete_user before it is added to the js.
 *
 * @param $user (CMS)
 *
 * @see CKEditorPluginManager
 */
function hook_gigya_delete_user_alter(&$user, &$res) {
  if ($user->get('uid')->value === "14") {
    $res = FALSE;
    return;
  }
  $res = TRUE;
}
