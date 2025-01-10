<?php
/**
 * Plugin Name: Hello World Plugin
 * Description: A plugin that prints "Hello World" using a shortcode and includes license key validation with plugin update .
 * Version: 1.0
 * Author: Abhishek verma
 */

defined('ABSPATH') || exit;

// =========================
// 1. Register Shortcode
// =========================
function hw_hello_world_shortcode() {
    return '<p>Hello World!</p>';
}
add_shortcode('hello_world', 'hw_hello_world_shortcode');

// =========================
// 2. License Key Admin Page
// =========================
function hw_add_license_menu() {
    add_menu_page(
        'Hello World License',
        'Hello World License',
        'manage_options',
        'hw-license',
        'hw_license_page'
    );
}
add_action('admin_menu', 'hw_add_license_menu');

function hw_license_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Handle form submission
    if (isset($_POST['hw_license_nonce']) && wp_verify_nonce($_POST['hw_license_nonce'], 'hw_license_update')) {
    update_option('hw_license_key', sanitize_text_field($_POST['hw_license_key']));
    echo '<div class="notice notice-success is-dismissible"><p>License key updated!</p></div>';
}


    $license_key = get_option('hw_license_key', '');
    ?>
    <div class="wrap">
        <h1>Hello World License</h1>
        <form method="POST">
            <label for="hw_license_key">Enter your license key:</label>
            <input type="text" name="hw_license_key" id="hw_license_key" value="<?php echo esc_attr($license_key); ?>" style="width: 300px;">
            <?php submit_button('Save License Key'); ?>
        </form>
    </div>
    <?php
}

// =========================
// 3. Plugin Update Checker
// =========================
function hw_check_for_updates() {
    $license_key = get_option('hw_license_key', '');
    if (empty($license_key)) {
        return; // Exit if no license key is provided
    }

    $current_version = '1.0';
    $update_url = 'http://localhost/product_review/wp-json/hello-world/v1/update-check'; // Replace with your server URL

    // Fetch update information
    $response = wp_remote_post($update_url, [
        'body' => [
            'license_key' => $license_key,
            'plugin_version' => $current_version,
        ],
    ]);

    if (is_wp_error($response)) {
        error_log('Update check failed: ' . $response->get_error_message());
        return;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!$data || !isset($data['new_version'])) {
        error_log('Invalid JSON or missing "new_version" property in update response.');
        return;
    }

    if (version_compare($current_version, $data['new_version'], '<')) {
        add_action('admin_notices', function () use ($data) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php printf(
                    'New version available: %s. <a href="%s">Update Now</a>',
                    esc_html($data['new_version']),
                    esc_url(admin_url('update.php?action=upgrade-plugin&plugin=hello-world-plugin'))
                ); ?></p>
            </div>
            <?php
        });
    }
}
add_action('admin_init', 'hw_check_for_updates');

// =========================
// 4. REST API Endpoint for Updates
// =========================
add_action('rest_api_init', function () {
    register_rest_route('hello-world/v1', '/update-check', [
        'methods'  => 'POST',
        'callback' => 'hw_get_update_details',
    ]);
});

function hw_get_update_details(WP_REST_Request $request) {
    $license_key = $request->get_param('license_key');
    $plugin_version = $request->get_param('plugin_version');

    // Validate license key (replace with your validation logic)
    if ($license_key !== 'your-valid-license-key') {
        return new WP_Error('invalid_license', 'Invalid license key', ['status' => 403]);
    }

    // Return update details
    return [
        'new_version'  => '1.1',
        'download_url' => 'http://localhost/product_review/wp-content/uploads/2024/12/hello-world-plugin-1.1.zip',
        'requires'     => '5.0',
        'tested'       => '6.3',
        'changelog'    => 'Bug fixes and improvements.',
    ];
}

// =========================
// 5. Automatic Plugin Updates with Auto-Update Filter
// =========================
add_filter('site_transient_update_plugins', 'hw_plugin_update_details');
function hw_plugin_update_details($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $license_key = get_option('hw_license_key', '');
    $current_version = '1.1'; // Current plugin version
    $update_url = 'http://localhost/product_review/wp-json/hello-world/v1/update-check'; // Replace with your server URL

    $response = wp_remote_post($update_url, [
        'body' => [
            'license_key' => $license_key,
            'plugin_version' => $current_version,
        ],
    ]);

    if (is_wp_error($response)) {
        return $transient;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if ($data && isset($data['new_version']) && version_compare($current_version, $data['new_version'], '<')) {
        $plugin_slug = plugin_basename(__FILE__); // Automatically fetch the correct plugin slug
        $transient->response[$plugin_slug] = (object) [
            'new_version' => $data['new_version'],
            'package'     => $data['download_url'],
            'slug'        => 'hello-world-plugin', // Plugin slug
            'plugin'      => $plugin_slug,
            'tested'      => $data['tested'],
            'requires'    => $data['requires'],
        ];
    }

    return $transient;
}

// Enable automatic updates for the plugin
add_filter('auto_update_plugin', 'hw_enable_auto_updates', 10, 2);
function hw_enable_auto_updates($update, $item) {
    // Enable auto-updates for your specific plugin
    if ($item->slug === 'hello-world-plugin') { // Replace with your plugin's slug
        return true;
    }
    return $update;
}


