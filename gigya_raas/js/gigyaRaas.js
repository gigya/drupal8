/**
 * @file
 * Handles AJAX login and register events.
 */

/**
 * For determining the full path to Drupal, useful for when Drupal is installed in a subdirectory of the main website.
 *
 * @property drupalSettings.path.pathPrefix
 * @property drupalSettings.path.baseUrl
 */
var getUrlPrefix = function () {
  return drupalSettings.path.pathPrefix ? (drupalSettings.path.baseUrl + drupalSettings.path.pathPrefix) : drupalSettings.path.baseUrl;
};

(function ($, Drupal, drupalSettings) {

  'use strict';

  /* For Internet Explorer */
  if (!String.prototype.startsWith) {
    String.prototype.startsWith = function (searchString, position) {
      position = position || 0;
      return this.indexOf(searchString, position) === position;
    };
  }

  /**
   * Invoked using InvokeCommand by Drupal's controller
   *
   * @param redirectTarget
   *
   * @property gigya.setSSOToken
   */
  jQuery.fn.loginRedirect = function (redirectTarget) {
    /**
     * @var bool sendSetSSOToken  Should be set in Gigya's global configuration.
     * Set this to True if you would like setSSOToken to be called
     * (a redirect to Gigya to set the site's cookies, for
     *   browsers that do not support 3rd party cookies)
     */

    if (!redirectTarget.startsWith('http')) {

      if (redirectTarget.startsWith(drupalSettings.path.baseUrl)) {
        redirectTarget = redirectTarget.substring(drupalSettings.path.baseUrl.length);
      }

      redirectTarget = drupalSettings.gigya.raas.origin + getUrlPrefix() + redirectTarget;
    }
    if (typeof sendSetSSOToken === 'undefined' || sendSetSSOToken === false) {
      location.replace(redirectTarget);
    } else if (sendSetSSOToken === true) {
      gigya.setSSOToken({redirectURL: redirectTarget});
    }
  };

  jQuery.fn.logoutRedirect = function (redirectTarget) {

    if (redirectTarget.startsWith(drupalSettings.path.baseUrl)) {
      redirectTarget = redirectTarget.substring(drupalSettings.path.baseUrl.length);
    }

    redirectTarget = drupalSettings.gigya.raas.origin + getUrlPrefix() + redirectTarget;
    document.location = redirectTarget;
  };

  /**
   * @property drupalSettings.gigya.loginUIParams
   * @property gigya.services.socialize.showLoginUI
   */
  var initLoginUI = function () {
    if (typeof drupalSettings.gigya.loginUIParams !== 'undefined') {
      $.each(drupalSettings.gigya.loginUIParams, function (index, value) {
        value.context = {id: value.containerID};
        gigya.services.socialize.showLoginUI(value);
      });
    }
  };

  var getRememberMeStatus = function (res) {
    var remember = false;
    /* Pull remember me status from the group context */
    if (typeof res.groupContext !== 'undefined') {
      var groupRemember = JSON.parse(res.groupContext).remember;
      if (typeof groupRemember !== 'undefined') {
        remember = groupRemember;
      }
    }
    /* "Remember Me" clicked on the current site always overrides the group context remember */
    if (typeof res.remember !== 'undefined') {
      remember = res.remember;
    }

    return remember;
  };

  /**
   * @param res
   *
   * @property gigya.setGroupContext
   * @property res.groupContext
   */
  var onLoginHandler = function (res) {

    var remember = getRememberMeStatus(res);
    /* Propagate Remember Me status to the SSO group */
    gigya.setGroupContext({
      "remember": remember
    });

    var url = drupalSettings.path.baseUrl + 'gigya/raas-login';
    if (typeof drupalSettings.gigyaExtra != 'undefined' && typeof drupalSettings.gigyaExtra.loginRedirectMode != 'undefined' && drupalSettings.gigyaExtra.loginRedirectMode === 'current') {
      url += '?destination=/' + drupalSettings.path.currentPath;
      if (typeof drupalSettings.path.currentQuery == 'object') {
        url += encodeURIComponent('?' + $.param(drupalSettings.path.currentQuery));
      }
    }
    var ajaxSettings = {
      url: url,
      submit: data
    };

    var data = {
      "uid": res.UID,
      "uid_sig": res.UIDSignature,
      "sig_timestamp": res.signatureTimestamp,
      "id_token": res.id_token,
      "remember": remember
    };

    var ajaxSettings = {
      url: getUrlPrefix() + 'gigya/raas-login',
      submit: data
    };

    var myAjaxObject = Drupal.ajax(ajaxSettings);
    myAjaxObject.execute();
  };

  var profileUpdated = function (data) {
    if (data.response.errorCode === 0) {
      var gigyaData = {
        UID: data.response.UID,
        UIDSignature: data.response.UIDSignature,
        signatureTimestamp: data.response.signatureTimestamp,
        id_token: data.response.id_token
      };

      var ajaxSettings = {
        url: getUrlPrefix() + 'gigya/raas-profile-update',
        submit: {gigyaData: gigyaData}
      };

      var myAjaxObject = Drupal.ajax(ajaxSettings);
      myAjaxObject.execute();
    }
  };

  var checkLogout = function () {
    var logoutCookie = gigya.utils.cookie.get('Drupal.visitor.gigya');
    if (logoutCookie === 'gigyaLogOut') {
      gigya.accounts.logout();
      gigya.utils.cookie.remove('Drupal.visitor.gigya');
    }
  };

  /**
   * @property drupalSettings.path.baseUrl
   * @property myAjaxObject.execute()
   */
  var onLogoutHandler = function () {
    var data = {};

    var ajaxSettings = {
      url: getUrlPrefix() + 'gigya/raas-logout',
      submit: data
    };
    var myAjaxObject = Drupal.ajax(ajaxSettings);
    myAjaxObject.execute();
  };

  var validateSessionAndCreateUbcCookie = function (response) {
    if (response['errorCode'] === 0) {
      var data = {
        "uid": response.UID,
        "uid_sig": response.UIDSignature,
        "sig_timestamp": response.signatureTimestamp,
        "id_token": response.id_token,
        "is_session_validation_process": true
      };

      var ajaxSettings = {
        url: getUrlPrefix() + 'gigya/raas-login',
        submit: data
      };

      var myAjaxObject = Drupal.ajax(ajaxSettings);
      myAjaxObject.execute();

    } else {
      gigya.accounts.logout();
    }
  }
  /**
   * @property gigya.accounts.showScreenSet
   * @property drupalSettings.gigya.enableRaaS
   * @property drupalSettings.gigya.raas
   * @property drupalSettings.gigya.raas.login
   * @property drupalSettings.gigya.shouldValidateSession
   */
  var initRaaS = function () {
    if (drupalSettings.gigya.enableRaaS) {
      var id;
      if (drupalSettings.gigya.shouldValidateSession) {
        gigya.accounts.getAccountInfo({include: 'id_token'}, {callback: validateSessionAndCreateUbcCookie});
      }

      $(once('gigya-raas', '.gigya-raas-login')).each(function () {
        $(this).on('click', function (e) {
          e.preventDefault();
          gigya.accounts.showScreenSet(drupalSettings.gigya.raas.login);
          drupalSettings.gigya.raas.linkId = $(this).attr('id');
        });
      });

      $(once('gigya-raas', '.gigya-raas-reg')).each(function () {
        $(this).on('click', function (e) {
          e.preventDefault();
          gigya.accounts.showScreenSet(drupalSettings.gigya.raas.register);
          drupalSettings.gigya.raas.linkId = $(this).attr('id');
        });
      });
      $(once('gigya-raas', '.gigya-raas-prof, a[href="/user"]')).each(function () {
        $(this).on('click', function (e) {
          e.preventDefault();
          drupalSettings.gigya.raas.profile.onAfterSubmit = profileUpdated;
          gigya.accounts.showScreenSet(drupalSettings.gigya.raas.profile);
        });
      });

      var loginDiv = $('#gigya-raas-login-div');
      if (loginDiv.length > 0 && (typeof drupalSettings.gigya.raas.login !== 'undefined')) {
        id = loginDiv.eq(0).attr('id');
        drupalSettings.gigya.raas.login.containerID = id;
        drupalSettings.gigya.raas.linkId = id;
        gigya.accounts.showScreenSet(drupalSettings.gigya.raas.login);
      }
      var regDiv = $('#gigya-raas-register-div');
      if (regDiv.length > 0 && (typeof drupalSettings.gigya.raas.register !== 'undefined')) {
        id = regDiv.eq(0).attr('id');
        drupalSettings.gigya.raas.register.containerID = id;
        drupalSettings.gigya.raas.linkId = id;
        gigya.accounts.showScreenSet(drupalSettings.gigya.raas.register);
      }
      var profDiv = $('#gigya-raas-profile-div');
      if ((profDiv.length > 0) && (typeof drupalSettings.gigya.raas.profile !== 'undefined')) {
        drupalSettings.gigya.raas.profile.containerID = profDiv.eq(0).attr('id');
        drupalSettings.gigya.raas.profile.onAfterSubmit = profileUpdated;
        gigya.accounts.showScreenSet(drupalSettings.gigya.raas.profile);
      }
    }
  };
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
          if (parseInt(custom_screenset.sync_data) === 1) {
            screenset_params['onAfterSubmit'] = processFieldMapping;
          }

          if (custom_screenset.display_type === 'popup') {
            $('#' + custom_screenset.link_id).click(function (e) {
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


  const linkDataForMenuLinkScreen = [
    {hashLink: "#gigya-login", screen: drupalSettings.gigya.raas.login},
    {hashLink: "#gigya-register", screen: drupalSettings.gigya.raas.register},
    {hashLink: "#gigya-editProfile", screen: drupalSettings.gigya.raas.profile},]


  var findTheRightScreenAndShowIt = function () {
    linkDataForMenuLinkScreen.forEach(showMenuLinkScreen);
  };

  var showMenuLinkScreen = function (linkData) {
    if ($(location).attr('hash') === linkData.hashLink) {
      if (typeof gigya !== 'undefined') {
        if (linkData.hashLink === '#gigya-editProfile') {
          drupalSettings.gigya.raas.profile.onAfterSubmit = profileUpdated;
        }
        gigya.accounts.showScreenSet(linkData.screen);
        drupalSettings.gigya.raas.linkId = $(this).attr('id');


      } else {
        setTimeout(showMenuLinkScreen, 200);
      }
    }
  }

  /**
   * This is run when the link is change.
   **/
  addEventListener('hashChange', findTheRightScreenAndShowIt);
  $('.menu a').click(function () {
    findTheRightScreenAndShowIt();
  })


  var processFieldMapping = function (data) {
    var gigyaData = {
      UID: data.response.UID,
      UIDSignature: data.response.UIDSignature,
      signatureTimestamp: data.response.signatureTimestamp
    };
    var ajaxSettings = {
      url: getUrlPrefix() + 'gigya/raas-process-fieldmapping',
      submit: {gigyaData: gigyaData}
    };
    var myAjaxObject = Drupal.ajax(ajaxSettings);
    myAjaxObject.execute();
  };

  var init = function () {
    if (drupalSettings.gigya.enableRaaS) {
      gigyaHelper.addGigyaFunctionCall('accounts.addEventHandlers', {
        onLogin: onLoginHandler,
        onLogout: onLogoutHandler
      });
    }

    drupalSettings.gigya.isRaasInit = true;
  };

  /**
   * @type {{attach: Drupal.behaviors.gigyaRaasInit.attach}}
   *
   * @param context
   * @param settings
   *
   * @property Drupal.behaviors
   */
  Drupal.behaviors.gigyaRaasInit = {
    attach: function (context, settings) {
      if (!('isRaasInit' in drupalSettings.gigya)) {
        /**
         * @param serviceName
         */
        window.onGigyaServiceReady = function (serviceName) {
          checkLogout();
          gigyaHelper.runGigyaCmsInit();
          initLoginUI();
          initRaaS();
          initCustomScreenSet();
        };
        init();
      }
    }
  };

})(jQuery, Drupal, drupalSettings);
