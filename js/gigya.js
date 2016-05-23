/**
 * @file
 * Handles AJAX submission and response in Views UI.
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
  }

  var onLoginHandler1  = function() {
    debugger;
  }
  var onLoginHandler = function (res) {
    debugger;
    console.log('raasLogin');

    var data = {
      "uid": res.UID,
      "uid_sig": res.UIDSignature,
      "sig_timestamp": res.signatureTimestamp
    };

    var ajaxSettings = {
      url: '/gigya/raas-login',
      submit: data,
    };
    debugger;
    var myAjaxObject = Drupal.ajax(ajaxSettings);
    var ajaxres = myAjaxObject.execute();
    console.log(ajaxres);


  }


  var profileUpdated = function (data) {
    console.log(data);
    var ajaxSettings = {
      url: '/gigya/raas-profile-update',
      submit: {gigyaProfile : data.profile},
    };
    var myAjaxObject = Drupal.ajax(ajaxSettings);
    var ajaxres = myAjaxObject.execute();
    console.log(ajaxres);
  }


  var logoutCallback = function () {
    console.log('logoutCallback');
  }

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
  }

  var init = function () {
    window.__gigyaConf = drupalSettings.gigya.globalParameters;
    //@TODO: replace after debug.
    window.__gigyaConf.enabledProviders = "*";
    if (drupalSettings.gigya.enableRaaS) {
      gigyaHelper.addGigyaFunctionCall('accounts.addEventHandlers', {
        onLogin: onLoginHandler,
        onLogout: logoutCallback
      });
    }

    gigyaHelper.addGigyaScript(drupalSettings.gigya.apiKey, drupalSettings.gigya.lang)
    drupalSettings.gigya.isInit = true;
  }


  //init();

  Drupal.behaviors.gigyaInit = {
    attach: function (context, settings) {
      if (!('isInit' in drupalSettings.gigya)) {
        window.onGigyaServiceReady = function (serviceName) {
          gigyaHelper.checkLogout();
          gigyaHelper.runGigyaCmsInit();
          initLoginUI();
          initRaas();
        }
        init();
      }
    }
  };


})(jQuery, Drupal, drupalSettings);
