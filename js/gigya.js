/**
 * @file
 * Handles AJAX submission and response in Views UI.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  window.__gigyaConf = drupalSettings.gigya.globalParameters;
  gigyaHelper.addGigyaScript(drupalSettings.gigya.apiKey, drupalSettings.gigya.lang)

})(jQuery, Drupal, drupalSettings);
