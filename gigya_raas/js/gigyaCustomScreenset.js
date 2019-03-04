/**
 * @file
 * Handles the custom screen-set block
 */

(function ($, Drupal, drupalSettings) {

	'use strict';

	/**
	 * @property gigya.accounts.showScreenSet
	 * @property drupalSettings.gigya.raas.customScreenSets
	 */
	var initCustomScreenSet = function () {
		if (drupalSettings.gigya.enableRaaS) {
			var customScreenSets = drupalSettings.gigya.raas.customScreenSets;

			/**
			 * @property custom_screenset.display_type
			 * @property custom_screenset.link_id
			 * @property custom_screenset.container_id
			 * @property custom_screenset.desktop_screenset
			 * @property custom_screenset.mobile_screenset
			 * @property custom_screenset.sync_data
			 */
			customScreenSets.forEach(function (custom_screenset) {
				if (typeof custom_screenset.display_type !== 'undefined') {
					var screenset_params = {
						screenSet: custom_screenset.desktop_screenset,
						mobileScreenSet: custom_screenset.mobile_screenset
					};
					if (parseInt(custom_screenset.sync_data) === 1)
						screenset_params['onAfterSubmit'] = processFieldMapping;

					if (custom_screenset.display_type === 'popup') {
						$('#' + custom_screenset.link_id).once('gigya-raas').click(function (e) {
							e.preventDefault();
							gigya.accounts.showScreenSet(screenset_params);
							drupalSettings.gigya.raas.linkId = $(this).attr('id');
						});
					} else if (custom_screenset.display_type === 'embed') {
						screenset_params['containerID'] = custom_screenset.container_id;
						gigya.accounts.showScreenSet(screenset_params);
					}
				}
			});
		}
	};

	var processFieldMapping = function (data) {
		var gigyaData = {
			UID: data.response.UID,
			UIDSignature: data.response.UIDSignature,
			signatureTimestamp: data.response.signatureTimestamp
		};
		var ajaxSettings = {
			url: drupalSettings.path.baseUrl + 'gigya/raas-process-fieldmapping',
			submit: {gigyaData: gigyaData}
		};
		var myAjaxObject = Drupal.ajax(ajaxSettings);
		myAjaxObject.execute();
	};

	/**
	 * @type {{attach: Drupal.behaviors.gigyaCustomScreenSetInit.attach}}
	 *
	 * @param context
	 * @param settings
	 *
	 * @property Drupal.behaviors
	 */
	Drupal.behaviors.gigyaCustomScreenSetInit = {
		attach: function (context, settings) {
			/**
			 * @param serviceName
			 */
			window.onGigyaServiceReady = function (serviceName) {
				initCustomScreenSet();
			};
		}
	};

})(jQuery, Drupal, drupalSettings);