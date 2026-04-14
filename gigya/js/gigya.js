/**
 * @file
 * Initialises the Gigya script.
 */

(function (drupalSettings) {

  'use strict';

  /**
   * @property drupalSettings.gigya.globalParameters
   * @property drupalSettings.gigya.apiKey
   * @property drupalSettings.gigya.dataCenter
   */
  window.__gigyaConf = drupalSettings.gigya.globalParameters;
  gigyaHelper.addGigyaScript(drupalSettings.gigya.apiKey, drupalSettings.gigya.lang, drupalSettings.gigya.dataCenter);

})(drupalSettings);
