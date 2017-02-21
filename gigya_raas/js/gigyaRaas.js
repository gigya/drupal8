/**
 * @file
 * Handles AJAX login and register events.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  var initLoginUI = function () {
    if (typeof drupalSettings.gigya.loginUIParams !== 'undefined') {
      $.each(drupalSettings.gigya.loginUIParams, function (index, value) {
        value.context = {id: value.containerID};
        gigya.services.socialize.showLoginUI(value);
      });
    }
  };

  var onLoginHandler = function (res) {
    var data = {
      "uid": res.UID,
      "uid_sig": res.UIDSignature,
      "sig_timestamp": res.signatureTimestamp
    };

    var ajaxSettings = {
      url: '/gigya/raas-login',
      submit: data
    };
    var myAjaxObject = Drupal.ajax(ajaxSettings);
    myAjaxObject.execute();
  };


  var profileUpdated = function (data) {
    if (data.response.errorCode == 0) {
      var gigyaData = {
        UID: data.response.UID,
        UIDSignature: data.response.UIDSignature,
        signatureTimestamp: data.response.signatureTimestamp
      };
      var ajaxSettings = {
        url: '/gigya/raas-profile-update',
        submit: {gigyaData : gigyaData}
      };
      var myAjaxObject = Drupal.ajax(ajaxSettings);
      myAjaxObject.execute();
    }

  };

  var checkLogout = function () {

    var logoutCookie = gigya.utils.cookie.get('Drupal.visitor.gigya');
    if (logoutCookie == 'gigyaLogOut') {
      gigya.accounts.logout();
      gigya.utils.cookie.remove('Drupal.visitor.gigya');
    }
  };


  var logoutCallback = function () {
    document.location = drupalSettings.path.baseUrl + 'user/logout';
  };

  var initRaas = function () {
    if (drupalSettings.gigya.enableRaaS) {
      $('.gigya-raas-login').once('gigya-raas').click(function (e) {
        e.preventDefault();
        gigya.accounts.showScreenSet(drupalSettings.gigya.raas.login);
        drupalSettings.gigya.raas.linkId = $(this).attr('id');
      });
      $('.gigya-raas-reg').once('gigya-raas').click(function (e) {
        e.preventDefault();
        gigya.accounts.showScreenSet(drupalSettings.gigya.raas.register);
        drupalSettings.gigya.raas.linkId = $(this).attr('id');
      });
      $('.gigya-raas-prof, a[href="/user"]').once('gigya-raas').click(function (e) {
        e.preventDefault();
        drupalSettings.gigya.raas.profile.onAfterSubmit = profileUpdated;
        gigya.accounts.showScreenSet(drupalSettings.gigya.raas.profile);
      });
      var loginDiv = $('#gigya-raas-login-div');
      if (loginDiv.size() > 0 && (typeof drupalSettings.gigya.raas.login !== 'undefined')) {
        var id = loginDiv.eq(0).attr('id');
        drupalSettings.gigya.raas.login.containerID = id;
        drupalSettings.gigya.raas.linkId = id;
        gigya.accounts.showScreenSet(drupalSettings.gigya.raas.login);
      }
      var regDiv = $('#gigya-raas-register-div');
      if (regDiv.size() > 0 && (typeof drupalSettings.gigya.raas.register !== 'undefined')) {
        var id = regDiv.eq(0).attr('id');
        drupalSettings.gigya.raas.register.containerID = id;
        drupalSettings.gigya.raas.linkId = id;
        gigya.accounts.showScreenSet(drupalSettings.gigya.raas.register);
      }
      var profDiv = $('#gigya-raas-profile-div');
      if ((profDiv.size() > 0) && (typeof drupalSettings.gigya.raas.profile !== 'undefined')) {
        drupalSettings.gigya.raas.profile.containerID = profDiv.eq(0).attr('id');
        drupalSettings.gigya.raas.profile.onAfterSubmit = profileUpdated;
        gigya.accounts.showScreenSet(drupalSettings.gigya.raas.profile);
      }
    }
  };

  var init = function () {
    if (drupalSettings.gigya.enableRaaS) {
      gigyaHelper.addGigyaFunctionCall('accounts.addEventHandlers', {
        onLogin: onLoginHandler,
        onLogout: logoutCallback
      });
    }

    drupalSettings.gigya.isRaasInit = true;
  };


  //init();
  var gigyaCheckLoginStatus = function () {
    try {
      var getAccountInfoResponse = function (response) {
        if (!response) {
          return;
        }
        if (response.errorCode != 0) {
          if (drupalSettings.gigyaExtra.isLogin == true) {
            //Login in drupal but not in Gigya.
            if (!drupalSettings.gigyaExtra.bypassRaas == true) {
              //No bypass raas perm.
              //Log out the user.
              document.location = drupalSettings.path.baseUrl + 'user/logout';
            }
          }
        }
        else {
          if (drupalSettings.gigyaExtra.isLogin == false) {
            onLoginHandler(response);
          }
        }
      };
      gigya.accounts.getAccountInfo({callback: getAccountInfoResponse});
    } catch (e) {
    } finally {
    }


  };

  Drupal.behaviors.gigyaRaasInit = {
    attach: function (context, settings) {
      if (!('isRaasInit' in drupalSettings.gigya)) {
        window.onGigyaServiceReady = function (serviceName) {
          checkLogout();
          gigyaHelper.runGigyaCmsInit();
          gigyaCheckLoginStatus();
          initLoginUI();
          initRaas();
        };
        init();
      }
    }
  };

})(jQuery, Drupal, drupalSettings);
