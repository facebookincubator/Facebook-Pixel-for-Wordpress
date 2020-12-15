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
            'type' => 'success',
            'msg' => $body,
        );
        echo json_encode($res);
    }

    private function handleUnauthorizedRequest(){
        $res = array(
            'type' => 'error',
            'msg' => 'Unauthorized user',
        );
        status_header(401);
        echo json_encode($res);
    }

    public function saveFbeSettings(){
        if (!is_admin()) {
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
            FacebookPluginConfig::USE_S2S_KEY => '1',
            FacebookPluginConfig::USE_PII_KEY => '1',
            FacebookPluginConfig::IS_FBE_INSTALLED_KEY => '1'
        );
        \update_option(
            FacebookPluginConfig::SETTINGS_KEY,
            $settings
        );
        $this->handleSuccessRequest($settings);
    }

    public function deleteFbeSettings(){
        if (!is_admin()) {
            $this->handleUnauthorizedRequest();
        }
        \delete_option( FacebookPluginConfig::SETTINGS_KEY );
        $this->handleSuccessRequest('Done');
    }
}
