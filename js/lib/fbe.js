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
          showDisconnectWarning: false,
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
   */

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
      headers: {
        'Authorization': 'Bearer ' + accessToken
      },
      data: ajaxParam({
        pixelId: '',
        businessId: '',
      }),
      success: function onSuccess(data) {
        if (data && data.success) {
          // Mark as installed so render() transitions to connected/loading state
          window.fbl4bConfig.installed = true;
          _this.setState({installed: 'true'});
          _processingOAuth = false;
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
            // Force re-render with meaningful state change
            _this.setState({fbl4bPixels: response.data.data});
          }
        } else {
          // Store empty array so renderConnectedState shows "no pixels" state
          window.fbl4bConfig.pendingPixels = [];
          _this.setState({fbl4bPixels: []});
        }
      },
      error: function(jqXHR, textStatus, errorThrown) {
        _this.consoleLog('FBL4B: Failed to fetch pixels: ' + errorThrown);
        // Store empty array so renderConnectedState shows "no pixels" state
        window.fbl4bConfig.pendingPixels = [];
        _this.setState({fbl4bPixels: []});
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
      type: 'post',
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

    // For FBL4B: If we have an access token but missing business_id or pixel_id,
    // fetch the missing data before rendering the iframe
    if (this.isFBL4BEnabled()) {
      this.initFBL4BData();

      // Show the cancel link after 5s delay — only if still in loading state
      setTimeout(function() {
        var cancelLink = document.getElementById('fbl4b-cancel-link');
        if (cancelLink) {
          cancelLink.style.display = 'block';
        }
      }, 5000);

      // Handle inline pixel selection dropdown changes
      jQuery(document).on('change', '#fbl4b-pixel-select-inline', function() {
        var btn = jQuery('#fbl4b-select-pixel-inline-btn');
        if (jQuery(this).val()) {
          btn.prop('disabled', false).removeClass('fbl4b-btn-disabled');
        } else {
          btn.prop('disabled', true).addClass('fbl4b-btn-disabled');
        }
      });
    }
  },

  /**
   * Initialize FBL4B data on page load.
   * Always validates the access token by calling /me API.
   * If token is revoked (user disconnected), clears all FBL4B data.
   */
  initFBL4BData: function initFBL4BData() {
    var _this = this;
    var config = window.fbl4bConfig;

    // Check if FBL4B is connected (installed flag comes from connectionType)
    if (!config.installed) {
      return;
    }

    // Always validate the access token by calling /me API
    _this.validateFBL4BConnection(function(isValid) {
      if (!isValid) {
        _this.consoleLog("FBL4B: Access token is invalid/revoked, clearing stored data...");
        _this.clearFBL4BData();
        return;
      }

      // If we already have a pixel selected, we're done - don't fetch anything
      if (config.pixelId) {
        return;
      }

      // If we have business_id but no pixel_id, fetch pixels
      // This only happens if user abandoned pixel selection
      if (config.businessId && !config.pixelId) {
        _this.fetchPixelsForBusiness(config.businessId);
        return;
      }

      // If we're missing business_id entirely, fetch it first
      if (!config.businessId) {
        _this.fetchPixelIdAndSave();
      }
    });
  },

  /**
   * Validate the FBL4B connection by calling /me API.
   * If the access token is revoked, the API will return an error.
   *
   * @param callback - Callback function that receives a boolean (valid/invalid)
   */
  validateFBL4BConnection: function validateFBL4BConnection(callback) {
    var _this = this;

    jQuery.ajax({
      type: 'post',
      url: window.fbl4bConfig.validateTokenRoute,
      timeout: 30000,
      success: function onSuccess(response) {
        if (response.success && response.data && response.data.valid) {

            // Check if businessId is missing from stored config
            var storedBusinessId = window.fbl4bConfig.businessId;
            if (!storedBusinessId || storedBusinessId === '') {
              window.fbl4bConfig.businessId = response.data.client_business_id;
              // Save businessId to backend (partial update), then continue validation
              _this.saveSettingsFBL4B(null, response.data.client_business_id);
            }

            callback(true);
          } else {
            callback(false);
          }
      },
      error: function(jqXHR, textStatus, errorThrown) {
        _this.consoleLog("FBL4B: Token validation failed - " + textStatus);
        _this.consoleLog('FBL4B: Token validation error: ' + errorThrown);
        callback(false);
      }
    });
  },

  /**
   * Save the businessId to backend and reload page.
   * Used when businessId was missing from stored config but token is valid.
   */
  saveBusinessIdAndReload: function saveBusinessIdAndReload(businessId) {
    var _this = this;
    var config = window.fbl4bConfig;

    jQuery.ajax({
      type: 'post',
      url: config.setSaveSettingsRoute,
      data: {
        pixelId: config.pixelId,
        businessId: businessId
      },
      success: function onSuccess(data, _textStatus, _jqXHR) {
        window.location.reload();
      },
      error: function() {
        // Continue anyway with updated local config
      }
    });
  },

  /**
   * Render the connected state UI (plugin-side, not iframe).
   * Shows Business ID, Pixel ID with Reconnect and Disconnect options.
   */
  renderConnectedState: function renderConnectedState(config, pixels) {
    var _this = this;
    var disconnectUrl = FBL4B_DISCONNECT_URL + '?business_id=' + (config.businessId || '');
    var hasPixel = config.pixelId && config.pixelId !== '';
    var needsPixelSelection = !hasPixel && pixels && pixels.length > 0;
    var noPixelsAvailable = !hasPixel && pixels && pixels.length === 0;

    // Hide the fbe-iframe and render connected state in a sibling container
    var iframeEl = document.getElementById('fbe-iframe');
    if (iframeEl) {
      iframeEl.style.display = 'none';
      // Hide the back button if it exists
      var backBtnEl = document.getElementById('fbl4b-back-btn');
      if (backBtnEl) { backBtnEl.style.display = 'none'; }
      // Hide the upgrade banner if it exists (server-rendered, stays after JS transition)
      var upgradeBanner = document.querySelector('.fbl4b-upgrade-notice');
      if (upgradeBanner) { upgradeBanner.style.display = 'none'; }
      // Create or reuse a sibling container for the connected state
      var connectedEl = document.getElementById('fbl4b-connected');
      if (!connectedEl) {
        connectedEl = document.createElement('div');
        connectedEl.id = 'fbl4b-connected';
        iframeEl.parentNode.insertBefore(connectedEl, iframeEl);
      }
      connectedEl.style.display = '';
      // Render connected state into the sibling container
      ReactDOM.render(this._buildConnectedState(config, pixels, hasPixel, needsPixelSelection, noPixelsAvailable, disconnectUrl), connectedEl);
      return React.createElement('div', {style: {display: 'none'}});
    }

    return this._buildConnectedState(config, pixels, hasPixel, needsPixelSelection, noPixelsAvailable, disconnectUrl);
  },

  _buildConnectedState: function _buildConnectedState(config, pixels, hasPixel, needsPixelSelection, noPixelsAvailable, disconnectUrl) {
    var _this = this;

    return React.createElement(
      'div',
      {className: 'fbl4b-connected-container'},
      // Header
      React.createElement(
        'div',
        {className: 'fbl4b-connected-header'},
        React.createElement(
          'div',
          {className: 'fbl4b-connected-header-left'},
          React.createElement('span', {
              className: 'fbl4b-connected-logo',
              dangerouslySetInnerHTML: {__html: '<svg width="24" height="24" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><mask id="meta-logo-mask" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="64" height="64"><rect width="64" height="64" fill="url(#meta-logo-pattern)"/></mask><g mask="url(#meta-logo-mask)"><rect width="64" height="64" fill="#0866FF"/></g><defs><pattern id="meta-logo-pattern" patternContentUnits="objectBoundingBox" width="1" height="1"><use xlink:href="#meta-logo-img" transform="scale(0.0078125)"/></pattern><image id="meta-logo-img" width="128" height="128" preserveAspectRatio="none" xlink:href="data:image/png;base64,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"/></defs></svg>'}
            }),
          React.createElement('span', {className: 'fbl4b-connected-logo-text'}, 'Meta'),
          React.createElement('span', {className: 'fbl4b-connected-title'}, 'Your Business is Connected to Meta'),
          React.createElement(
            'span',
            {className: hasPixel ? 'fbl4b-connected-badge' : 'fbl4b-connected-badge fbl4b-connected-badge-setup'},
            hasPixel ? '✓ Active' : '⚠ Setup Required'
          )
        )
      ),
      // Connection details
      React.createElement(
        'div',
        {className: 'fbl4b-connected-details'},
        // Business ID row
        React.createElement(
          'div',
          {className: 'fbl4b-connected-row fbl4b-connected-row-border'},
          React.createElement(
            'div',
            {className: 'fbl4b-connected-label'},
            React.createElement('span', null, 'Business ID')
          ),
          React.createElement('span', {className: 'fbl4b-connected-value'}, config.businessId)
        ),
        // Pixel row — shows ID when selected, or inline selection when pending
          React.createElement(
            'div',
            {className: needsPixelSelection ? 'fbl4b-connected-row fbl4b-pixel-row-selection' : 'fbl4b-connected-row'},
            React.createElement(
              'div',
              {className: 'fbl4b-connected-label'},
              React.createElement('span', null, 'Meta Pixel'),
              !hasPixel ? React.createElement(
                'span',
                {className: 'fbl4b-selection-badge'},
                '⚠ Selection required'
              ) : null
            ),
            hasPixel
              ? React.createElement('span', {className: 'fbl4b-connected-value'},
                  config.pixelName ? config.pixelName + ' (' + config.pixelId + ')' : config.pixelId)
              : needsPixelSelection
                ? React.createElement(
                    'div',
                    {className: 'fbl4b-pixel-inline-select'},
                    React.createElement(
                      'select',
                      {
                        id: 'fbl4b-pixel-select-inline',
                        className: 'fbl4b-pixel-select',
                        onChange: function(e) {
                          var btn = document.getElementById('fbl4b-select-pixel-inline-btn');
                          var selectEl = e.target;
                          if (e.target.value) {
                            if (btn) {
                              btn.disabled = false;
                              btn.className = 'fbl4b-btn-confirm';
                            }
                            selectEl.style.borderColor = '#d1d5db';
                            selectEl.style.color = '#1c2b33';
                          } else {
                            if (btn) {
                              btn.disabled = true;
                              btn.className = 'fbl4b-btn-confirm fbl4b-btn-disabled';
                            }
                            selectEl.style.borderColor = '#dc2626';
                            selectEl.style.color = '#dc2626';
                          }
                        }
                      },
                      React.createElement('option', {value: ''}, 'Select Pixel'),
                      pixels.map(function(pixel) {
                        return React.createElement('option', {key: pixel.id, value: pixel.id, 'data-name': pixel.name || ''}, pixel.name + ' (' + pixel.id + ')');
                      })
                    ),
                    React.createElement(
                      'button',
                      {
                        id: 'fbl4b-select-pixel-inline-btn',
                        className: 'fbl4b-btn-confirm fbl4b-btn-disabled',
                        onClick: function() {
                          var selectEl = document.getElementById('fbl4b-pixel-select-inline');
                          var selectedPixelId = selectEl ? selectEl.value : '';
                          if (selectedPixelId) {
                            var selectedOption = selectEl.options[selectEl.selectedIndex];
                            var selectedPixelName = selectedOption ? selectedOption.getAttribute('data-name') || '' : '';
                            _this.saveSettingsFBL4B(selectedPixelId, config.businessId, selectedPixelName);
                          }
                        }
                      },
                      'Confirm'
                    )
                  )
                : null
          ),
          // Helper text below pixel row
          needsPixelSelection ? React.createElement(
            'p',
            {className: 'fbl4b-pixel-helper-text'},
            'Select a pixel to use on this WordPress site to start tracking conversions.'
          ) : null,
          // No pixels alert
          noPixelsAvailable ? React.createElement(
            'div',
            {className: 'fbl4b-pixel-alert', style: {marginTop: '8px'}},
            React.createElement('p', {className: 'fbl4b-pixel-alert-text'},
              '⚠️ No Meta Pixels were found for your business. Please create a pixel in ',
              React.createElement('a', {href: 'https://business.facebook.com/events_manager', target: '_blank', className: 'fbl4b-link'}, 'Events Manager'),
              ', then click Refresh.'
            ),
            React.createElement(
              'button',
              {
                onClick: function() {
                  _this.fetchPixelsForBusiness(config.businessId);
                },
                className: 'fbl4b-btn-primary',
                style: {marginRight: '10px'}
              },
              'Refresh Pixels'
            )
          ) : null
        ),
      // Connection Settings accordion — collapses Reconnect & Disconnect
      React.createElement(
        'div',
        {className: 'fbl4b-connected-section-last fbl4b-connection-settings'},
        React.createElement(
          'div',
          {
            className: 'fbl4b-connection-settings-header',
            onClick: function() {
              _this.setState({showConnectionSettings: !_this.state.showConnectionSettings});
            }
          },
          React.createElement('span', {className: 'fbl4b-connection-settings-title'}, 'Connection Settings'),
          React.createElement('span', {className: 'fbl4b-connection-settings-arrow'}, _this.state.showConnectionSettings ? '▴' : '▾')
        ),
        _this.state.showConnectionSettings ? React.createElement(
          'div',
          {className: 'fbl4b-connection-settings-body'},
          // Reconnect — only when fully connected
          hasPixel ? React.createElement(
            'div',
            {className: 'fbl4b-connection-settings-item'},
            React.createElement('h4', {className: 'fbl4b-connected-section-title'}, 'Reconnect'),
            React.createElement('p', {className: 'fbl4b-connected-section-desc'},
              'Re-run the connection flow to update your linked assets.'
            ),
            !_this.state.showReconnectConfirm
              ? React.createElement(
                  'button',
                  {
                    onClick: function() {
                      _this.setState({showReconnectConfirm: true});
                    },
                    className: 'fbl4b-btn-primary'
                  },
                  'Reconnect'
                )
              : React.createElement(
                  'div',
                  {className: 'fbl4b-reconnect-confirm'},
                  React.createElement('p', {style: {margin: '0 0 12px 0', fontSize: '14px', color: '#1c2b33', lineHeight: '1.5'}},
                    'This will start a new authentication flow. Your current pixel configuration will be preserved until you complete the new connection.'
                  ),
                  React.createElement(
                    'div',
                    {style: {display: 'flex', gap: '8px'}},
                    React.createElement(
                      'button',
                      {
                        onClick: function() {
                          _this.setState({showReconnectConfirm: false, showReconnectIframe: true});
                        },
                        className: 'fbl4b-btn-primary'
                      },
                      'Continue'
                    ),
                    React.createElement(
                      'button',
                      {
                        onClick: function() {
                          _this.setState({showReconnectConfirm: false});
                        },
                        className: 'fbl4b-btn-secondary'
                      },
                      'Cancel'
                    )
                  )
                )
          ) : null,
          // Disconnect
          React.createElement(
            'div',
            {className: 'fbl4b-connection-settings-item'},
            React.createElement('h4', {className: 'fbl4b-connected-section-title'}, 'Disconnect'),
            React.createElement(
              'p',
              {className: 'fbl4b-connected-section-desc'},
              'This will remove the connection between this WordPress site and your Meta Business Portfolio. Your Pixel and other assets will remain accessible in ',
              React.createElement('a', {href: FBL4B_BUSINESS_MANAGER_URL + '?business_id=' + (config.businessId || ''), target: '_blank', className: 'fbl4b-link'}, 'Meta Business Manager'),
              '.'
            ),
            !_this.state.showDisconnectWarning
              ? React.createElement(
                  'button',
                  {
                    onClick: function() {
                      _this.setState({showDisconnectWarning: true});
                    },
                    className: 'fbl4b-btn-danger'
                  },
                  'Disconnect from Meta'
                )
              : React.createElement(
                  'div',
                  {className: 'fbl4b-reconnect-confirm'},
                  React.createElement('p', {className: 'fbl4b-disconnect-warning-title'}, '⚠️ To complete disconnection:'),
                  React.createElement(
                    'ol',
                    {className: 'fbl4b-disconnect-warning-steps'},
                    React.createElement('li', null, 'Click "Continue" below to open Meta Business Settings'),
                    React.createElement('li', null, 'Find and remove this WordPress integration'),
                    React.createElement('li', null, 'Return here and refresh this page')
                  ),
                  React.createElement(
                    'div',
                    {style: {display: 'flex', gap: '8px', marginTop: '12px'}},
                    React.createElement(
                      'button',
                      {
                        onClick: function() {
                          window.open(disconnectUrl, '_blank');
                        },
                        className: 'fbl4b-btn-danger'
                      },
                      'Continue to Meta Business Settings'
                    ),
                    React.createElement(
                      'button',
                      {
                        onClick: function() {
                          _this.setState({showDisconnectWarning: false});
                        },
                        className: 'fbl4b-btn-secondary'
                      },
                      'Cancel'
                    )
                  )
                )
          )
        ) : null
      )
    );
  },

  /**
   * Show a WordPress-style admin notice for FBL4B events.
   * @param {string} message - The message to display
   * @param {string} type - 'error', 'success', or 'warning'
   */
  showFBL4BNotice: function showFBL4BNotice(message, type) {
    // Remove any existing FBL4B notices
    var existing = document.querySelectorAll('.fbl4b-notice');
    existing.forEach(function(el) { el.remove(); });

    var notice = document.createElement('div');
    notice.className = 'fbl4b-notice fbl4b-notice-' + type;

    var messageEl = document.createElement('p');
    messageEl.textContent = message;
    notice.appendChild(messageEl);

    var dismissBtn = document.createElement('button');
    dismissBtn.className = 'fbl4b-notice-dismiss';
    dismissBtn.textContent = '×';
    dismissBtn.onclick = function() { notice.remove(); };
    notice.appendChild(dismissBtn);

    // Insert before the iframe container
    var iframeContainer = document.getElementById('fbe-iframe');
    if (iframeContainer && iframeContainer.parentNode) {
      iframeContainer.parentNode.insertBefore(notice, iframeContainer);
    }

    // Auto-dismiss after 10 seconds
    setTimeout(function() {
      if (notice.parentNode) { notice.remove(); }
    }, 10000);
  },

  consoleLog: function consoleLog(message) {
    var debug = this.isFBL4BEnabled()
      ? window.fbl4bConfig.debug
      : window.facebookBusinessExtensionConfig.debug;
    if(debug) {
      console.log(message);
    }
  },

  /**
   * Build query params for FBL4B iframe.
   * FBL4B uses a simplified set of params compared to MBE.
   * When we have pixelId, include business_id and pixel_id.
   */
  queryParamsFBL4B: function queryParamsFBL4B() {
    var config = window.fbl4bConfig;
    // app_id and config_id are already in the iframe URL from PHP
    var params = '&version=' + config.version;

    if (config.pixelId) {
      params += '&installed=true';
      if (config.businessId) {
        params += '&business_id=' + config.businessId;
      }
      params += '&pixel_id=' + config.pixelId;
    } else {
      params += '&installed=false';
    }

    return params;
  },

  /**
   * Build query params for legacy MBE iframe.
   */
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
      // For FBL4B: Check if we need to show pixel selection instead of iframe
      if (_this.isFBL4BEnabled()) {
        var config = window.fbl4bConfig;

        // Debug: Log all config values

        // If we have access token but missing pixel_id, check if we have
        // pending pixels to show for selection. If pendingPixels is set,
        // fall through to the connected state which renders the pixel selection UI.
        // If still fetching (pendingPixels not set), show loading placeholder.
        if (config.installed && !config.pixelId) {

          // If pendingPixels is set (including empty array), the pixel fetch
          // is complete — fall through to renderConnectedState which handles
          // pixel selection, no-pixels, and connected states.
          if (config.pendingPixels !== undefined && config.pendingPixels !== null) {
            return _this.renderConnectedState(config, config.pendingPixels);
          }

          // Still fetching pixels — show loading placeholder
          return React.createElement(
            'div',
            {
              id: 'fbl4b-loading',
              className: 'fbl4b-loading-container'
            },
            React.createElement('p', {className: 'fbl4b-loading-text'},
              'Configuring your Meta Business connection...'
            ),
            React.createElement(
              'div',
              {
                id: 'fbl4b-cancel-link',
                style: {display: 'none'}
              },
              React.createElement(
                'button',
                {
                  onClick: function() {
                    if (confirm('This will disconnect your Meta Business connection. Continue?')) {
                      _this.clearFBL4BData();
                    }
                  },
                  className: 'fbl4b-link',
                  style: {background: 'none', border: 'none', cursor: 'pointer', marginTop: '12px', fontSize: '13px'}
                },
                'Cancel and start over'
              )
            )
          );
        }

        // If user clicked Reconnect, show the iframe for re-authentication
        if (_this.state.showReconnectIframe) {
          // Show the main container and hide the connected state
          var iframeEl = document.getElementById('fbe-iframe');
          var connectedEl = document.getElementById('fbl4b-connected');
          if (iframeEl) { iframeEl.style.display = ''; }
          if (connectedEl) { connectedEl.style.display = 'none'; }

          var iframeUrl = config.iframeUrl;
          // iframeUrl already includes app_id and config_id from PHP
          var reconnectParams = '&version=' + config.version + '&installed=false';

          // Render back button outside fbe-iframe
          var backBtnEl = document.getElementById('fbl4b-back-btn');
          if (!backBtnEl) {
            backBtnEl = document.createElement('div');
            backBtnEl.id = 'fbl4b-back-btn';
            backBtnEl.style.margin = '20px 20px 12px 0';
            iframeEl.parentNode.insertBefore(backBtnEl, iframeEl);
          }
          backBtnEl.style.display = '';
          ReactDOM.render(
            React.createElement(
              'button',
              {
                onClick: function() {
                  _this.setState({showReconnectIframe: false});
                },
                className: 'fbl4b-btn-secondary'
              },
              '← Back to Connection Status'
            ),
            backBtnEl
          );

          // Render just the iframe inside fbe-iframe
          return React.createElement(
            'iframe',
            {
              src: iframeUrl + reconnectParams,
              className: 'fbl4b-iframe-full'
            }
          );
        }

        // Connected state — show when we have businessId (with or without pixel)
        if (config.businessId) {
          var pendingPixels = config.pendingPixels || null;
          return _this.renderConnectedState(config, pendingPixels);
        }

        // Onboarding state - show iframe for initial setup
        var iframeUrl = config.iframeUrl;
        var queryParams = _this.queryParamsFBL4B();

        // If upgrading from MBE, show a back button to return to MBE connected state
        if (config.upgradeFromMBE) {
          return React.createElement(
            'div',
            null,
            React.createElement(
              'button',
              {
                onClick: function() {
                  // Clear upgrade flag and redirect back without the param
                  var url = new URL(window.location.href);
                  url.searchParams.delete('upgrade_to_fbl4b');
                  window.location.href = url.toString();
                },
                style: { marginBottom: '16px' },
                className: 'fbl4b-btn-secondary'
              },
              '← Back to Current Connection'
            ),
            React.createElement(
              'iframe',
              {
                src: iframeUrl + queryParams,
                className: 'fbl4b-iframe-full'
              }
            )
          );
        }

        return React.createElement(
          'iframe',
          {
            src: iframeUrl + queryParams,
            className: 'fbl4b-iframe-full'
          }
        );
      }

      // Legacy MBE flow
      var iframeUrl = window.facebookBusinessExtensionConfig.fbeLoginUrl;
      var queryParams = _this.queryParams();

      return React.createElement(
        'iframe',
        {
          src: iframeUrl + queryParams,
          className: 'fbl4b-iframe-full'
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
    // Update Events Manager link with the correct pixel ID
    var sanitizedPixelId = String(pixelId).replace(/[^0-9]/g, '');
    jQuery(".meta-event-manager a").attr("href",
      "https://business.facebook.com/events_manager2/list/pixel/" + sanitizedPixelId
    );
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
