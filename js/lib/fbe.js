/**
 * Copyright (c) 2016-present, Meta, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the root directory of this source tree. An additional grant
 * of patent rights can be found in the PATENTS file in the code directory.
 */
'use strict';

var React = require('react');
var ReactDOM = require('react-dom');
var IEOverlay = require('./IEOverlay');
var FBModal = require('./Modal');
var FBUtils = require('./utils');

var jQuery = (function (jQuery) {
  if (jQuery && typeof jQuery === 'function') {
    return jQuery;
  } else {
    console.error('window.jQuery is not valid or loaded, please check your magento 2 installation!');
    // if jQuery is not there, we return a dummy jQuery obejct with ajax,
    // so it will not break our following code
    return {
      ajax: function () {
      }
    };
  }
})(window.jQuery);

// FBL4B Constants
var FBL4B_DISCONNECT_URL = 'https://business.facebook.com/latest/settings/connected_apps';
var FBL4B_BUSINESS_MANAGER_URL = 'https://business.facebook.com/latest/settings/events_dataset_and_pixel';

// Dedup guard for OAuth postMessage
var _processingOAuth = false;

var ajaxParam = function (params) {
  if (window.FORM_KEY) {
    params.form_key = window.FORM_KEY;
  }
  return params;
};

var FBEFlowContainer = React.createClass({

    getDefaultProps: function() {
        // For FBL4B, use FBL4B-specific installed state
        var isFBL4B = window.fbl4bConfig && window.fbl4bConfig.enabled === true;
        var installedState = isFBL4B
          ? window.fbl4bConfig.installed
          : window.facebookBusinessExtensionConfig.installed;
        return {
            installed: installedState
        };
    },
    getInitialState: function() {
        var config = window.fbl4bConfig;
        return {
          installed: this.props.installed,
          // FBL4B states
          fbl4bLoading: false,
          fbl4bNeedsPixelSelection: false,
          fbl4bPixels: [],
          fbl4bBusinessId: config ? config.businessId : null,
          fbl4bPixelId: config ? config.pixelId : null,
          // Reconnect flow state
          showReconnectIframe: false,
          showReconnectConfirm: false,
          showConnectionSettings: false,
        };
    },

    /**
     * Check if FBL4B (Facebook Login for Business) is enabled.
     * FBL4B is a simplified OAuth flow that replaces the traditional MBE flow.
     */
    isFBL4BEnabled: function() {
      var config = window.fbl4bConfig;
      if (!config) return false;
      if (config.upgradeFromMBE) return true;
      return config.enabled && config.appId !== '';
    },

  bindMessageEvents: function bindMessageEvents() {
    var _this = this;
    if (FBUtils.isIE() && window.MessageChannel) {
      // do nothing, wait for our messaging utils to be ready
    } else {
      window.addEventListener('message', function (event) {
        var origin = event.origin || event.originalEvent.origin;

        // FBL4B origin validation — strict equality only
        if (_this.isFBL4BEnabled()) {
          var config = window.fbl4bConfig;
          var isValid = (origin === config.popupOrigin)
            || origin === 'https://www.facebook.com'
            || origin === 'https://business.facebook.com'
            || origin === 'https://m.facebook.com';
          if (isValid) {
            _this.saveFBLoginData(event.data);
            return;
          }
        }

        // MBE origin validation
        if (FBUtils.urlFromSameDomain(origin, window.facebookBusinessExtensionConfig.popupOrigin)) {
          _this.saveFBLoginData(event.data);
        }
      }, false);
    }
  },

  saveFBLoginData: function saveFBLoginData(data) {
    var _this = this;
    if (data) {
      var responseObj;
      try {
        responseObj = JSON.parse(data);
      } catch (e) {
        return;
      }

      // Handle FBL4B messages — single type, three outcomes
      if (_this.isFBL4BEnabled() && responseObj.type === 'FBL4B_ONBOARDING_COMPLETE') {
        // Dedup guard — don't process the same message twice
        if (_processingOAuth) {
          return;
        }

        if (responseObj.success && responseObj.accessToken) {
          _processingOAuth = true;
          _this.consoleLog("FBL4B: Token received: ****" + responseObj.accessToken.slice(-4));
          _this.setState({showReconnectIframe: false});
          window.fbl4bConfig.pixelId = '';
          // Hide back button once token is received
          var backBtn = document.getElementById('fbl4b-back-btn');
          if (backBtn) { backBtn.style.display = 'none'; }
          // Save token first, then fetch business ID in the callback
          _this.saveAccessTokenToServer(responseObj.accessToken);
        } else if (responseObj.error && responseObj.error.code === 'CANCELLED') {
          _processingOAuth = false;
          if (_this.state.showReconnectIframe) {
            // Return to connected state
            _this.setState({showReconnectIframe: false});
          } else {
            // Initial auth cancelled — reload page to reset iframe to GET_STARTED state
            window.location.reload();
          }
        } else {
          _this.consoleLog('[FBL4B] Connection failed');
          _processingOAuth = false;
          if (_this.state.showReconnectIframe) {
            _this.setState({showReconnectIframe: false});
          } else {
            _this.showFBL4BNotice('Connection failed. Please try again.', 'error');
          }
        }
        return;
      }

      // Handle legacy MBE message format
      var accessToken = responseObj.access_token;
      var success = responseObj.success;
      var pixelId = responseObj.pixel_id;

      if(success) {
        let action = responseObj.action;
        if(action != null && action === 'delete') {
          // Delete asset ids stored in db instance.
          _this.deleteFBAssets();
          _this.hideAdsPlugin();
        }else if(action != null && action === 'create') {
          _this.saveSettings(pixelId, accessToken, window.facebookBusinessExtensionConfig.externalBusinessId);
          _this.setState({installed: 'true'});
          _this.showAdsPlugin();
        }
      }else {
      }
    }
  },

  /**
   * Fetch pixel ID from Graph API using the access token.
   * FBL4B doesn't return pixel_id directly, so we need to fetch it.
   *
   * Flow:
   * 1. Fetch /me?fields=client_business_id to get the business ID
   * 2. Fetch pixels via /{client_business_id}/owned_pixels
   * 3. If multiple pixels, show selection UI
   * 4. Save selected pixel and reload iframe
   * Fetches the client_business_id and pixels via server-side proxy.
   * The access token is already saved to the server by saveAccessTokenToServer()
   * before this function is called.
   */
  fetchPixelIdAndSave: function fetchPixelIdAndSave() {
    var _this = this;

    jQuery.ajax({
      type: 'post',
      url: window.fbl4bConfig.fetchBusinessIdRoute,
      timeout: 30000,
      success: function onSuccess(response) {
        if (!response.success || !response.data) {
          _this.consoleLog('FBL4B: Failed to fetch business ID from proxy');
          return;
        }

        var businessId = response.data.client_business_id;

        if (!businessId) {
          return;
        }

        // Save businessId (token already saved above)
        _this.saveSettingsFBL4B(null, businessId);

        window.fbl4bConfig.businessId = businessId;

        // Now fetch pixels
        _this.fetchPixelsForBusiness(businessId);
      },
      error: function(jqXHR, textStatus, errorThrown) {
        _this.consoleLog('FBL4B: Failed to fetch business ID: ' + errorThrown);
      }
    });
  },

  /**
   * Fetch pixels for a business using client_business_id.
   * If multiple pixels found, show selection UI.
  /**
   * Save the FBL4B access token to the server.
   * This is called ONCE during initial onboarding when the token
   * arrives from the iframe postMessage. After this save, the token
   * is only on the server — never on the window object.
   */
  saveAccessTokenToServer: function saveAccessTokenToServer(accessToken) {
    var _this = this;

    if (!accessToken) {
      return;
    }

    jQuery.ajax({
      type: 'post',
      url: window.fbl4bConfig.setSaveSettingsRoute,
      data: ajaxParam({
        accessToken: accessToken,
        pixelId: '',
        businessId: '',
      }),
      success: function onSuccess(data) {
        if (data && data.success) {
          // Mark as installed so render() transitions to connected/loading state
          window.fbl4bConfig.installed = true;
          _this.setState({installed: 'true'});
          _this.fetchPixelIdAndSave();
        } else {
          _this.consoleLog("FBL4B: Failed to save access token");
          _this.showFBL4BNotice('Failed to save connection. Please try again.', 'error');
          _processingOAuth = false;
        }
      },
      error: function() {
        _this.consoleLog('FBL4B: Error saving access token to server');
        _this.showFBL4BNotice('Failed to save connection. Please try again.', 'error');
        _processingOAuth = false;
      }
    });
  },

  /**
   * Save FBL4B settings (pixelId and/or businessId) to the server.
   * Does NOT handle the access token — use saveAccessTokenToServer() for that.
   */
  fetchPixelsForBusiness: function fetchPixelsForBusiness(businessId) {
    var _this = this;

    jQuery.ajax({
      type: 'post',
      url: window.fbl4bConfig.fetchPixelsRoute,
      data: { businessId: businessId },
      timeout: 30000,
      success: function onSuccess(response) {
        if (response.success && response.data && response.data.data && response.data.data.length > 0) {

          if (response.data.data.length === 1) {
            // Auto-select single pixel
            var pixelId = response.data.data[0].id;
            var pixelName = response.data.data[0].name || '';
            _this.saveSettingsFBL4B(pixelId, businessId, pixelName);
          } else {
            // Multiple pixels — store on config for renderConnectedState to use
            window.fbl4bConfig.pendingPixels = response.data.data;
            // Force re-render by updating state
            _this.setState({});
          }
        } else {
          // Store empty array so renderConnectedState shows "no pixels" state
          window.fbl4bConfig.pendingPixels = [];
          _this.setState({});
        }
      },
      error: function(jqXHR, textStatus, errorThrown) {
        _this.consoleLog('FBL4B: Failed to fetch pixels: ' + errorThrown);
        // Store empty array so renderConnectedState shows "no pixels" state
        window.fbl4bConfig.pendingPixels = [];
        _this.setState({});
      }
    });
  },

  /**
   * Clear all FBL4B stored data when user disconnects.
   * Calls the delete endpoint and reloads the page.
   */
  clearFBL4BData: function clearFBL4BData() {
    var _this = this;

    jQuery.ajax({
      type: 'delete',
      url: window.fbl4bConfig.deleteConfigKeys,
      success: function onSuccess(data, _textStatus, _jqXHR) {
        // Clear local config
        window.fbl4bConfig.businessId = null;
        window.fbl4bConfig.pixelId = null;
        window.fbl4bConfig.installed = false;
        // Reload page to show fresh onboarding state
        window.location.reload();
      },
      error: function() {
        _this.consoleLog('FBL4B: Failed to clear stored data');
        // Still reload to show onboarding
        window.location.reload();
      }
    });
  },

  /**
   * Save settings for FBL4B flow.
   *
   * @param pixelId - The pixel ID to save
   * @param businessId - The client_business_id to save
   */
  saveSettingsFBL4B: function saveSettingsFBL4B(pixelId, businessId, pixelName) {
    var _this = this;

    jQuery.ajax({
      type: 'post',
      url: window.fbl4bConfig.setSaveSettingsRoute,
      data: ajaxParam({
        pixelId: pixelId || '',
        businessId: businessId || '',
        pixelName: pixelName || '',
      }),
      success: function onSuccess(data, _textStatus, _jqXHR) {
        var response = data;
        if (response.success) {
          window.fbl4bConfig.businessId = businessId;
          window.fbl4bConfig.pixelId = pixelId;
          window.fbl4bConfig.pixelName = pixelName || '';

          if (pixelId) {
            // Pixel selected — update config and re-render inline
            window.fbl4bConfig.installed = true;
            window.fbl4bConfig.pendingPixels = null;
            _this.setState({pixelId: pixelId, installed: 'true', businessId: businessId});
            _this.showEventsManagerSection(pixelId);
            // Hide Ads sections for FBL4B (they require MBE scopes)
            _this.hideAdsPlugin();
          } else {
          }
        } else {
        }
      },
      error: function () {
        _this.consoleLog('FBL4B: There was a problem saving the settings');
        _this.showFBL4BNotice('Failed to save connection. Please try again.', 'error');
      }
    });
  },

  /**
   * Reload the FBL4B iframe with updated parameters.
   * Called after settings are saved to show the "connected" state.
   * Uses displayBusinessId (the id field) which is what the iframe expects.
   */
  reloadFBL4BIframe: function reloadFBL4BIframe(pixelId, displayBusinessId) {
    var _this = this;

    var baseUrl = window.fbl4bConfig.iframeUrl;
    var params = 'app_id=' + window.fbl4bConfig.appId +
                 '&config_id=' + window.fbl4bConfig.configId +
                 '&installed=true' +
                 '&version=' + window.fbl4bConfig.version;

    if (displayBusinessId) {
      params += '&business_id=' + displayBusinessId;
    }
    if (pixelId) {
      params += '&pixel_id=' + pixelId;
    }

    var newIframeUrl = baseUrl + params;

    // Update the iframe src
    jQuery('#fbe-iframe iframe').attr('src', newIframeUrl);
  },

  saveSettings: function saveSettings( pixelId, accessToken, externalBusinessId ){
    var _this = this;
    if(!pixelId){
      console.error('Meta Business Extension Error: got no pixel_id');
      return;
    }
    if(!accessToken){
      console.error('Meta Business Extension Error: got no access token');
      return;
    }
    if(!externalBusinessId){
      console.error('Meta Business Extension Error: got no external business id');
      return;
    }
    jQuery.ajax({
      type: 'post',
      url: window.facebookBusinessExtensionConfig.setSaveSettingsRoute,
      async : false,
      data: ajaxParam({
        pixelId: pixelId,
        accessToken: accessToken,
        externalBusinessId: externalBusinessId,
      }),
      success: function onSuccess(data, _textStatus, _jqXHR) {
        var response = data;
        let msg = '';
        if (response.success) {
          _this.setState({pixelId: pixelId});
          _this.showEventsManagerSection(pixelId);
          msg = "The Meta Pixel with ID: " + pixelId + " is now installed on your website.";
        } else {
          msg = "There was a problem saving the pixel. Please try again";
        }
      },
      error: function () {
        console.error('There was a problem saving the pixel with id', pixelId);
      }
    });
  },
  deleteFBAssets: function deleteFBAssets() {
    var _this = this;
    jQuery.ajax({
      type: 'delete',
      url: window.facebookBusinessExtensionConfig.deleteConfigKeys,
      success: function onSuccess(data, _textStatus, _jqXHR) {
        let msg = '';
        if(data.success) {
          msg = data.message;
          _this.hideEventsManagerSection();
        }else {
          msg = data.error_message;
        }
        _this.setState({installed: 'false'});
      },
      error: function() {
        console.error('There was a problem deleting the connection, Please try again.');
      }
    });
  },
  showAdsPlugin: function showAdsPlugin() {
    jQuery("#meta-ads-plugin").show();
  },
  hideAdsPlugin: function hideAdsPlugin() {
    jQuery("#meta-ads-plugin").hide();
  },
  componentDidMount: function componentDidMount() {
    this.bindMessageEvents();
  },
  consoleLog: function consoleLog(message) {
    if(window.facebookBusinessExtensionConfig.debug) {
      console.log(message);
    }
  },
  queryParams: function queryParams() {
    return 'app_id='+window.facebookBusinessExtensionConfig.appId +
            '&timezone='+window.facebookBusinessExtensionConfig.timeZone+
            '&external_business_id='+window.facebookBusinessExtensionConfig.externalBusinessId+
            '&installed='+this.state.installed+
            '&system_user_name='+window.facebookBusinessExtensionConfig.systemUserName+
            '&business_vertical='+window.facebookBusinessExtensionConfig.businessVertical+
            '&version='+window.facebookBusinessExtensionConfig.version+
            '&currency='+ window.facebookBusinessExtensionConfig.currency +
            '&business_name='+ window.facebookBusinessExtensionConfig.businessName +
            '&channel=' + window.facebookBusinessExtensionConfig.channel +
            '&hide_create_ad_button=' + true;
  },
  render: function render() {
    var _this = this;
    try {
      _this.consoleLog("query params --"+_this.queryParams());
      return React.createElement(
        'iframe',
        {
          src:window.facebookBusinessExtensionConfig.fbeLoginUrl + _this.queryParams(),
          style: {border:'none',width:'1100px',height:'700px'}
        }
      );
    } catch (err) {
      console.error(err);
    }
  },
  hideEventsManagerSection: function hideEventsManagerSection() {
    jQuery(".events-manager-wrapper").hide();
    jQuery('#ad-creation-plugin').hide();
    jQuery('#ad-insights-plugin').hide();
    jQuery("#fb-adv-conf").hide();
    jQuery(".events-manager-wrapper input#pixel-id").val('');
  },
  showEventsManagerSection: function showEventsManagerSection(pixelId) {
    jQuery(".events-manager-wrapper").show();
    jQuery('#ad-creation-plugin').show();
    jQuery('#ad-insights-plugin').show();
    jQuery("#fb-adv-conf").show();
    jQuery(".events-manager-wrapper input#pixel-id").val(pixelId);
  }
});

// Render
ReactDOM.render(
  React.createElement(FBEFlowContainer, null),
  document.getElementById('fbe-iframe')
);

// Code to display the above container.
var displayFBModal = function displayFBModal() {
  if (FBUtils.isIE()) {
    IEOverlay().render();
  }
};

(function main() {
  // Logic for when to display the container.
  if (document.readyState === 'interactive') {
    // in case the document is already rendered
    displayFBModal();
  } else if (document.addEventListener) {
    // modern browsers
    document.addEventListener('DOMContentLoaded', displayFBModal);
  } else {
    document.attachEvent('onreadystatechange', function () {
      // IE <= 8
      if (document.readyState === 'complete') {
        displayFBModal();
      }
    });
  }
})();
