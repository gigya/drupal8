/**
 * @file
 * Handles AJAX submission and response in Views UI.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  var initLoginUI = function() {
    if (typeof drupalSettings.gigya.loginUIParams !== 'undefined') {
      $.each(drupalSettings.gigya.loginUIParams, function (index, value) {
        value.context = {id: value.containerID};
        gigya.services.socialize.showLoginUI(value);
      });
    }
  }


  var init = function() {
    window.__gigyaConf = drupalSettings.gigya.globalParameters;
    window.__gigyaConf.enabledProviders = "";
    gigyaHelper.addGigyaScript(drupalSettings.gigya.apiKey, drupalSettings.gigya.lang)
  }


  init();

  Drupal.behaviors.gigyaInit = {
    attach: function (context, settings) {
      function onGigyaServiceReady(serviceName) {
        if (typeof gigya !== 'undefined') {
          // Display LoginUI if necessary.

        }
      }
    }
  };

  //// Display LoginUI if necessary.
  //if (typeof Drupal.settings.gigya.loginUIParams !== 'undefined') {
  //  $.each(Drupal.settings.gigya.loginUIParams, function (index, value) {
  //    value.context = {id: value.containerID};
  //    gigya.services.socialize.showLoginUI(value);
  //  });
  //}

  window.onGigyaServiceReady = function(serviceName) {
    gigyaHelper.checkLogout();
    gigyaHelper.runGigyaCmsInit();
    gigya.accounts.addEventHandlers(
      {onLogin: gigyaHelper.onLoginHandler}
    );
    initLoginUI();
  }

})(jQuery, Drupal, drupalSettings);
