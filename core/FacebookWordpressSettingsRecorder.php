<?php
namespace FacebookPixelPlugin\Core;

class FacebookWordpressSettingsRecorder {

    public function init(){
        add_action('wp_ajax_save_fbe_settings', array($this, 'saveFbeSettings'));
        add_action('wp_ajax_delete_fbe_settings',
            array($this, 'deleteFbeSettings')
        );
        add_action('wp_ajax_save_capi_integration_status',
            array($this, 'saveCapiIntegrationStatus'));
    }

    private function handleSuccessRequest($body){
        $res = array(
            'success' => true,
            'msg' => $body,
        );
        wp_send_json($res);
        return $res;
    }

    private function handleUnauthorizedRequest(){
        $res = array(
            'success' => false,
            'msg' => 'Unauthorized user',
        );
        wp_send_json($res, 403);
        return $res;
    }

    private function handleInvalidRequest(){
        $res = array(
            'success' => false,
            'msg' => 'Invalid values',
        );
        wp_send_json($res, 400);
        return $res;
    }

    public function saveFbeSettings(){
        if (!current_user_can('administrator')) {
            return $this->handleUnauthorizedRequest();
        }
        check_admin_referer(
            FacebookPluginConfig::SAVE_FBE_SETTINGS_ACTION_NAME
        );
        $pixel_id = sanitize_text_field($_POST['pixelId']);
        $access_token = sanitize_text_field($_POST['accessToken']);
        $external_business_id = sanitize_text_field(
            $_POST['externalBusinessId']
        );
        if(empty($pixel_id)
            || empty($access_token)
            || empty($external_business_id)){
            return $this->handleInvalidRequest();
        }
        $settings = array(
            FacebookPluginConfig::PIXEL_ID_KEY => $pixel_id,
            FacebookPluginConfig::ACCESS_TOKEN_KEY => $access_token,
            FacebookPluginConfig::EXTERNAL_BUSINESS_ID_KEY =>
                $external_business_id,
            FacebookPluginConfig::IS_FBE_INSTALLED_KEY => '1'
        );
        \update_option(
            FacebookPluginConfig::SETTINGS_KEY,
            $settings
        );
        return $this->handleSuccessRequest($settings);
    }

    public function saveCapiIntegrationStatus(){
        if (!current_user_can('administrator')) {
            return $this->handleUnauthorizedRequest();
        }
        check_admin_referer(
            FacebookPluginConfig::SAVE_CAPI_INTEGRATION_STATUS_ACTION_NAME
        );
        $val = sanitize_text_field($_POST['val']);

        if(!($val === '0' || $val === '1')){
            return $this->handleInvalidRequest();
        }

        \update_option(FacebookPluginConfig::CAPI_INTEGRATION_STATUS, $val);
        return $this->handleSuccessRequest($val);
    }

    public function deleteFbeSettings(){
        if (!current_user_can('administrator')) {
            return $this->handleUnauthorizedRequest();
        }
        check_admin_referer(
            FacebookPluginConfig::DELETE_FBE_SETTINGS_ACTION_NAME
        );
        \delete_option( FacebookPluginConfig::SETTINGS_KEY );
        \delete_transient( FacebookPluginConfig::AAM_SETTINGS_KEY );

        return $this->handleSuccessRequest('Done');
    }
}
