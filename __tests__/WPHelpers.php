<?php

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($string) {
        return stripslashes($string);
    }
}

if (!function_exists('wp_register_script')) {
    function wp_register_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false) {
        return true;
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('plugins_url')) {
    function plugins_url($path = '', $plugin = '') {
        $base_url = 'http://' . $_SERVER['HTTP_HOST'] . '/wp-content/plugins/';

        if (!empty($plugin)) {
            $plugin_dir = dirname(plugin_basename($plugin));
            if ('.' !== $plugin_dir) {
                $base_url .= trailingslashit($plugin_dir);
            }
        }

        return $base_url . ltrim($path, '/');
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($string) {
        return rtrim($string, '/') . '/';
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return str_replace(WP_PLUGIN_DIR . '/', '', $file);
    }
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', $_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins');
}


if (!function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $object_name, $l10n) {
        if (!is_string($object_name) || empty($object_name)) {
            return false;
        }

        $data = [];
        foreach ($l10n as $key => $value) {
            $data[] = sprintf('%s: %s', json_encode($key), json_encode($value));
        }

        $script = sprintf(
            '<script type="text/javascript">var %s = {%s};</script>',
            $object_name,
            implode(',', $data)
        );

        echo $script;
        return true;
    }
}


if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false) {
        static $enqueued_scripts = [];

        // Avoid duplicate enqueuing
        if (isset($enqueued_scripts[$handle])) {
            return;
        }

        $version = $ver ? '?ver=' . $ver : '';
        $script_url = $src . $version;

        if ($in_footer) {
            add_to_footer_queue("<script src=\"$script_url\" type=\"text/javascript\"></script>");
        } else {
            echo "<script src=\"$script_url\" type=\"text/javascript\"></script>\n";
        }

        $enqueued_scripts[$handle] = $script_url;
    }

    function add_to_footer_queue($script_tag) {
        static $footer_scripts = [];
        $footer_scripts[] = $script_tag;

        register_shutdown_function(function() use (&$footer_scripts) {
            foreach ($footer_scripts as $script) {
                echo $script . "\n";
            }
        });
    }
}
