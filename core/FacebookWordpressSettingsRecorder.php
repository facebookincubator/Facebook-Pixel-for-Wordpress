<?php
namespace FacebookPixelPlugin\Core;

class FacebookWordpressSettingsRecorder {

    public function init(){
        add_action('wp_ajax_save_fbe_settings', array($this, 'saveFbeSettings'));
        add_action('wp_ajax_delete_fbe_settings',
            array($this, 'deleteFbeSettings')
        );
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
        wp_send_json($res);
        return $res;
    }

    public function saveFbeSettings(){
        if (!current_user_can('administrator')) {
            $this->handleUnauthorizedRequest();
        }
        $pixel_id = $_POST['pixelId'];
        $access_token = $_POST['accessToken'];
        $external_business_id = $_POST['externalBusinessId'];
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

    public function deleteFbeSettings(){
        if (!current_user_can('administrator')) {
            $this->handleUnauthorizedRequest();
        }
        \delete_option( FacebookPluginConfig::SETTINGS_KEY );
        \delete_transient( FacebookPluginConfig::AAM_SETTINGS_KEY );
        return $this->handleSuccessRequest('Done');
    }
}
