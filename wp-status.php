<?php
/*
 * Plugin Name: WP Status
 * Description: JSON Endpoint to display site status for use with monitoring tools such as UptimeRobot, Uptime Kuma, Nagios or Zabbix.
 * Version: 1.0
 * Author: Jonny McCullagh
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

add_action('plugins_loaded', 'wp_status_load_textdomain');

add_action('admin_menu', function () {
    add_options_page(
        'WP Status Settings',
        'WP Status',
        'manage_options',
        'wp-status',
        'wp_status_settings_page'
    );
});


add_action('rest_api_init', function () {
    register_rest_route(
        'wp-status/v1',
        '/wp-status',
        array(
            'methods' => 'GET',
            'callback' => 'wp_status_get_status',
            'permission_callback' => 'wp_status_permission_check'
        )
    );
});

add_filter('admin_footer_text', function () {
    return 'Thank you for using WP Status. Check out the source code on <a href="https://github.com/jonnymccullagh/wp-status" target="_blank" rel="noopener noreferrer">GitHub</a>.';
});

// Set a password on plugin activation for security
register_activation_hook(__FILE__, function () {
    $password = get_option('wp_status_password');
    if (empty($password)) {
        $password = wp_generate_password(16, false);
        update_option('wp_status_password', $password);
    }
});


function wp_status_load_textdomain() {
    load_plugin_textdomain('wp-status', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}


function wp_status_settings_page() {
    if (isset($_POST['wp_status_password'])) {
        check_admin_referer('wp_status_save_settings');
        update_option('wp_status_password', sanitize_text_field($_POST['wp_status_password']));
        echo '<div class="updated"><p>';
        _e('WP Status authorization password saved successfully.', 'wp-status');
        echo '</p></div>';
    }
    $password = get_option('wp_status_password');
    ?>
    <div class="wrap">
        <h1>WP Status Settings</h1>
        <form method="post">
            <?php wp_nonce_field('wp_status_save_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="wp_status_password"><?php _e('API Password', 'wp-status'); ?></label></th>
                    <td><input type="text" name="wp_status_password" id="wp_status_password" value="<?php echo esc_attr($password); ?>" class="regular-text"></td>
                </tr>
            </table>
            <?php submit_button(__('Save Password', 'wp-status')); ?>
        </form>

        <h2><?php _e('Usage Example', 'wp-status'); ?></h2>
        <p><?php _e('Use the following curl command to access the status endpoint', 'wp-status'); ?>:</p>
        <code>
            curl -H "Authorization: <?php echo esc_attr($password); ?>" <?php echo site_url('/wp-json/wp-status/v1/wp-status'); ?>
        </code>
    </div>
    <?php
}


// The callback to provide status data.
function wp_status_get_status() {
    $start_time = microtime(true);
    global $wpdb;

    // Cache control headers to prevent caching at intermediary layers.
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");

    // Fetch update and comment data.
    $core_updates = get_site_transient('update_core');
    $plugin_updates = get_site_transient('update_plugins');
    $theme_updates = get_site_transient('update_themes');
    $unapproved_comments = get_comments(array(
        'status' => 'hold',
        'count'  => true,
    ));
    $db_status = is_wp_error($wpdb) ? 2 : 0;
    $plugin_update_count = isset($plugin_updates->response) ? count((array)$plugin_updates->response) : 0;
    $theme_update_count = isset($theme_updates->response) ? count((array)$theme_updates->response) : 0;

    // Is a core update required
    $core_update_available = false;
    $wp_version = "None";
    if (!empty($core_updates->updates)) {
        foreach ($core_updates->updates as $core_update) {
            if (isset($core_update->current, $core_update->version)) {
                $wp_version = $core_update->current;
                // Compare the installed version with the available version.
                if ($core_update->current !== $core_update->version) {
                    $core_update_available = true;
                    break;
                }
            }
        }
    }

    // Determine the WP status and HTTP status code.
    if ($db_status == 2) {
        $wp_status = "critical";
        $wp_status_code = 2;
        $http_status = 500;
    } elseif ($plugin_update_count > 0 || $theme_update_count > 0 || $core_update_available) {
        $wp_status = "warning";
        $wp_status_code = 1;
        $http_status = 200;
    } else {
        $wp_status = "ok";
        $wp_status_code = 0;
        $http_status = 200;
    }
    // Prepare the response time metric
    $end_time = microtime(true);
    $execution_time_ms = round($end_time - $start_time, 5);
     // Prepare the memory metrics
    $current_script_memory = function_exists('memory_get_usage') ? memory_get_usage() : false;
    $peak_script_memory = function_exists('memory_get_peak_usage') ? memory_get_peak_usage() : false;
    $current_script_memory_mb = round($current_script_memory / 1024 / 1024, 2);
    $peak_script_memory_mb = round($peak_script_memory / 1024 / 1024, 2);
    // Other metrics
    $db_query_count = $wpdb->num_queries;
    $php_version = phpversion();

    // Prepare the JSON response.
    $response = array(
        'status'                   => $wp_status,
        'wp_status_code'           => $wp_status_code,
        'database_access'          => $db_status,
        'plugin_update_count'      => $plugin_update_count,
        'theme_update_count'       => $theme_update_count,
        'core_update_available'    => $core_update_available,
        'unapproved_comments'      => $unapproved_comments,
        'execution_time_ms'        => $execution_time_ms,
        'current_script_memory_mb' => $current_script_memory_mb,
        'peak_script_memory_mb'    => $peak_script_memory_mb,
        'wp_version'               => $wp_version,
        'php_version'              => $php_version,
        'db_query_count'           => $db_query_count,
    );

    wp_send_json($response, $http_status);
}


// Permission check to protect the endpoint.
function wp_status_permission_check($request) {
    $password = get_option('wp_status_password');

    // Handle missing password.
    if (!$password) {
        error_log("WP Status: Authorization failed. No password is set in plugin settings.");
        wp_send_json(
            array("error" => __("Authorization is required to access this endpoint.", 'wp-status')),
            503
        );
        return false;
    }

    // Check password is correct
    $provided_password = $request->get_header('Authorization');
    if ($password !== $provided_password) {
        error_log("WP Status: Authorization failed. Incorrect password provided.");
        wp_send_json(
            array("error" => __("Invalid API password.", 'wp-status')),
            503
        );
        return false;
    }

    return true; // Allow access
}