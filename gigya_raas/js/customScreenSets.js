/**
 * @file
 * Handles custom screen sets.
 */

(function ($, Drupal, drupalSettings) {

  Drupal.behaviors.gigyaRaasCustomScreenSets = {
    attach: function (context, settings) {
      window.onGigyaServiceReady = function () {
        for (var id in drupalSettings.gigya.raas.customScreenSets) {
          var custom_screenset = drupalSettings.gigya.raas.customScreenSets[id];
          if (typeof custom_screenset.display_type !== 'undefined') {
            var screenset_params = {
              screenSet: custom_screenset.desktop_screenset,
              mobileScreenSet: custom_screenset.mobile_screenset
            };
            if (parseInt(custom_screenset.sync_data) === 1)
              screenset_params['onAfterSubmit'] = processFieldMapping;

            if (custom_screenset.display_type === 'popup') {
              $('#' + custom_screenset.link_id)
                .once('gigya-raas')
                .click(popupClickListener);
            }
            else if (custom_screenset.display_type === 'embed') {
              screenset_params['containerID'] = custom_screenset.container_id;
              gigya.accounts.showScreenSet(screenset_params);
            }
          }
        }
      };

      var popupClickListener = function (e) {
        var id = $(this).closest('.gigya-custom-screenset').attr('id');
        var custom_screenset = drupalSettings.gigya.raas.customScreenSets[id];
        var screenset_params = {
          screenSet: custom_screenset.desktop_screenset,
          mobileScreenSet: custom_screenset.mobile_screenset
        };
        e.preventDefault();
        gigya.accounts.showScreenSet(screenset_params);
        drupalSettings.gigya.raas.linkId = $(this).attr('id');
      };

      var processFieldMapping = function (data) {
        var gigyaData = {
          UID: data.response.UID,
          UIDSignature: data.response.UIDSignature,
          signatureTimestamp: data.response.signatureTimestamp
        };
        var ajaxSettings = {
          url: drupalSettings.path.baseUrl + 'gigya/raas-process-fieldmapping',
          submit: { gigyaData: gigyaData }
        };
        var myAjaxObject = Drupal.ajax(ajaxSettings);
        myAjaxObject.execute();
      };
    }
  };

})(jQuery, Drupal, drupalSettings);
