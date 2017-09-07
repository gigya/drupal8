/**
 * @file
 * Handles AJAX submission and response in Views UI.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  var init = function () {
    //if value set in gigya raas module update the value of the sessionExpiration
    if (drupalSettings.gigyaExtra) {
        drupalSettings.gigya.globalParameters.sessionExpiration = (drupalSettings.gigyaExtra.session_type == "dynamic" ? -1 : drupalSettings.gigyaExtra.session_time);
    }
    window.__gigyaConf = drupalSettings.gigya.globalParameters;

    gigyaHelper.addGigyaScript(drupalSettings.gigya.apiKey, drupalSettings.gigya.lang, drupalSettings.gigya.dataCenter);
    drupalSettings.gigya.isInit = true;
  };

  Drupal.behaviors.gigyaInit = {
    attach: function (context, settings) {
      if (!('isInit' in drupalSettings.gigya)) {
        init();
      }
    }
  };


})(jQuery, Drupal, drupalSettings);
