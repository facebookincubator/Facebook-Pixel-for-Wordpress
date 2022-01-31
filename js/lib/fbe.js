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

var ajaxParam = function (params) {
  if (window.FORM_KEY) {
    params.form_key = window.FORM_KEY;
  }
  return params;
};

var FBEFlowContainer = React.createClass({

    getDefaultProps: function() {
        console.log("init props installed "+window.facebookBusinessExtensionConfig.installed);
        return {
            installed: window.facebookBusinessExtensionConfig.installed
        };
    },
    getInitialState: function() {
        console.log("change state");
        return {installed: this.props.installed};
    },

  bindMessageEvents: function bindMessageEvents() {
    var _this = this;
    if (FBUtils.isIE() && window.MessageChannel) {
      // do nothing, wait for our messaging utils to be ready
    } else {
      window.addEventListener('message', function (event) {
        var origin = event.origin || event.originalEvent.origin;
        if (FBUtils.urlFromSameDomain(origin, window.facebookBusinessExtensionConfig.popupOrigin)) {
          // Make ajax calls to store data from fblogin and fb installs
          _this.consoleLog("Message from fblogin ");
          _this.saveFBLoginData(event.data);
        }
      }, false);
    }
  },
  saveFBLoginData: function saveFBLoginData(data) {
    var _this = this;
    if (data) {
      var responseObj = JSON.parse(data);
      _this.consoleLog("Response from fb login -- " + data);
      var accessToken = responseObj.access_token;
      var success = responseObj.success;
      var pixelId = responseObj.pixel_id;

      if(success) {
        let action = responseObj.action;
        if(action != null && action === 'delete') {
          // Delete asset ids stored in db instance.
          _this.consoleLog("Successfully uninstalled FBE");
            _this.deleteFBAssets();
        }else if(action != null && action === 'create') {
          _this.saveSettings(pixelId, accessToken, window.facebookBusinessExtensionConfig.externalBusinessId);
          _this.setState({installed: 'true'});
        }
      }else {
        _this.consoleLog("No response received after setup");
      }
    }
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
          msg = "The Meta Pixel with ID: " + pixelId + " is now installed on your website.";
        } else {
          msg = "There was a problem saving the pixel. Please try again";
        }
        _this.consoleLog(msg);
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
        }else {
          msg = data.error_message;
        }
        _this.consoleLog(msg);
        _this.setState({installed: 'false'});
      },
      error: function() {
        console.error('There was a problem deleting the connection, Please try again.');
      }
    });
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
            '&channel=' + window.facebookBusinessExtensionConfig.channel;
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
