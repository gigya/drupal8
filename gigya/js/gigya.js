/**
 * @file
 * Handles AJAX submission and response in Views UI.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

	/**
     * @property drupalSettings.gigya.globalParameters
     * @property drupalSettings.gigya.apiKey
     * @property drupalSettings.gigya.dataCenter
	 */
	var init = function () {
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
