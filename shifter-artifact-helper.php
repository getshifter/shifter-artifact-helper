<?php
/*
Plugin Name: Shifter – Artifact Helper
Plugin URI: https://github.com/getshifter/shifter-artifact-helper
Description: Helper tool for building Shifter Artifacts
Version: 1.0.5
Author: Shifter Team
Author URI: https://getshifter.io
License: GPLv2 or later
*/

// Shifter URLs
require_once __DIR__.'/include/class-shifter-urls.php';
require_once __DIR__.'/include/class-shifter-onelogin.php';

/**
 * Shifter URLs v1
 */
add_action(
    'template_redirect',
    function () {
        if (!isset($_GET['urls'])) {
            return;
        }

        $json_data = shifter_get_urls();

        if ($json_data['count'] <= 0) {
            header("HTTP/1.1 404 Not Found");
        }
        header('Content-Type: application/json');
        echo json_encode($json_data);
        die();
    }
);

/**
 * Shifter URLs v2 (WP JSON REST API)
 */
add_action(
    'rest_api_init',
    function () {
        register_rest_route(
            ShifterUrls::REST_ENDPOINT,
            ShifterUrls::REST_PATH,
            [
                'methods'  => WP_REST_Server::READABLE,
                'callback' => 'shifter_urls_for_rest_api'
            ]
        );
        register_rest_route(
            ShifterUrls::REST_ENDPOINT,
            ShifterUrls::REST_PATH.'/(?P<path>.+)',
            [
                'methods'  => WP_REST_Server::READABLE,
                'callback' => 'shifter_urls_for_rest_api'
            ]
        );
        register_rest_route(
            ShifterOneLogin::REST_ENDPOINT,
            ShifterOneLogin::REST_PATH,
            [
                'methods'  => WP_REST_Server::READABLE,
                'callback' => 'shifter_one_login'
            ]
        );
        register_rest_route(
            ShifterOneLogin::REST_ENDPOINT,
            ShifterOneLogin::REST_PATH.'/(?P<username>.+)',
            [
                'methods'  => WP_REST_Server::READABLE,
                'callback' => 'shifter_one_login'
            ]
        );
        register_rest_route(
            ShifterOneLogin::REST_ENDPOINT,
            ShifterOneLogin::REST_PATH.'/(?P<action>.+)/(?P<username>.+)',
            [
                'methods'  => WP_REST_Server::CREATABLE,
                'callback' => 'shifter_one_login'
            ]
        );
    }
);

/**
 * Callback function for WP JSON REST API
 *
 * @param array $data
 *
 * @return object
 */
function shifter_urls_for_rest_api($data=[])
{
    if (!defined('SHIFTER_REST_REQUEST')) {
        define('SHIFTER_REST_REQUEST', true);
    }

    $request_path = trailingslashit('/' . (isset($data['path']) && !empty($data['path']) ? $data['path'] : ''));
    $json_data = shifter_get_urls($request_path, true);
    $json_data['page']++;

    $response = new WP_REST_Response($json_data);
    $response->set_status(200);

    return $response;
}

/**
 * Callback function for WP JSON REST API
 *
 * @param array $data
 *
 * @return object
 */
function shifter_one_login($data=[])
{
    if (!defined('SHIFTER_REST_REQUEST')) {
        define('SHIFTER_REST_REQUEST', true);
    }
    if ( ! isset($_SERVER['SHIFTER_LOGIN_TOKEN'])) {
        $_SERVER['SHIFTER_LOGIN_TOKEN'] = 'NONE';
    }
    $json_data = [];
    $json_data['code'] = 'not_allowed';
    $json_data['message'] = "Sorry, you are not allowed to do that.";
    $status = 401;

    $request_method = strtoupper(sanitize_key(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET'));
    $token = sanitize_key(isset($_REQUEST['token']) ? $_REQUEST['token'] : '');
    $username = isset($data['username']) && !empty($data['username']) ? $data['username'] : '';
    $action = sanitize_key(isset($data['action']) && !empty($data['action']) ? $data['action'] : 'get');
    $json_data['user'] = [ 'user_login' => $username ];

    if (ShifterOneLogin::chk_token($token)) {
        $user = ShifterOneLogin::get_user_by_loginname($username);
        $user_info = false;
        $json_data['action'] = $action;

        switch ($action) {
        case 'get':
            if ($user) {
                $json_data['loginUrl'] = ShifterOneLogin::magic_link($username);
                $user_info = array(
                    'ID' => $user->ID,
                    'user_login' => $username,
                    'user_email' => $user->user_email,
                    'role' => $user->roles,
                );
                $status = 200;
            } else {
                $status = 404;
            }
            break;
        case 'create':
        case 'update':
            if ('POST' === $request_method) {
                $email = sanitize_email(isset($_POST['email']) ? $_POST['email'] : '');
                $role = sanitize_key(isset($_POST['role']) ? $_POST['role'] : 'administrator');
                if ($user) {
                    $user_info = ShifterOneLogin::update_user($username, $email, $role);
                } else if ('create' === $action) {
                    $user_info = ShifterOneLogin::create_user($username, $email, $role);
                } else {
                    $status = 404;
                }
            }
            if ($user_info) {
                $status = 200;
                $json_data['loginUrl'] = ShifterOneLogin::magic_link($username);
            }
            break;
        default:
            break;
        }
    }

    if (404 === $status && false === $user_info) {
        $json_data['code'] = 'not_found';
        $json_data['message'] = "That user doesn't exist.";
    } else if (200 === $status) {
        $json_data['code'] = 'OK';
        unset($json_data['message']);
        $json_data['user'] = $user_info;
    }

    $response = new WP_REST_Response($json_data);
    $response->set_status($status);

    return $response;
}

/**
 * Magik Login
 */
add_action(
    'init',
    function () {
        if( isset($_GET['token']) && isset($_GET['uid']) && isset($_GET['nonce']) ){
            $user_id = (int)$_GET['uid'];
            $token = sanitize_key($_GET['token']);
            $nonce = sanitize_key($_GET['nonce']);
            if ( ! ShifterOneLogin::chk_login_param($user_id, $token, $nonce) ) {
                wp_redirect(home_url('/wp-login.php'));
                exit;
            } else {
                wp_set_auth_cookie($user_id);
                wp_redirect(ShifterOneLogin::current_page_url());
                exit;
            }
        }
        return;
    }
);

// Shifter customize

/**
 * Remove /index.php/ from Permalink
 */
add_filter('got_rewrite', '__return_true');

/**
 * Create shifter_404.html
 */
add_action(
    'template_redirect',
    function () {
        if (!isset($_GET['urls'])) {
            $request_uri  = ShifterUrls::link_normalize(
                isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''
            );
            if (preg_match('#^/'.preg_quote(ShifterUrls::PATH_404_HTML).'/?$#i', $request_uri)) {
                header("HTTP/1.1 404 Not Found");
                $overridden_template = locate_template('404.php');
                if (!file_exists($overridden_template)) {
                    $overridden_template = locate_template('index.php');
                }
                load_template($overridden_template);
                die();
            } else {
                return;
            }
        }
    },
    1
);

/**
 * Relative path
 */
add_action(
    'init',
    function () {
        // upload dir -> relative path
        add_filter(
            'upload_dir',
            function ($uploads) {
                $parsed_url  = parse_url(home_url());
                $host_name   = $parsed_url['host'];
                $server_name = $host_name . (isset($parsed_url['port']) ? ':'.$parsed_url['port'] : '');
                if (isset($uploads['url'])) {
                    $uploads['url'] = preg_replace('#^(https?://|//)[^/]+/#', '/', $uploads['url']);
                }
                if (isset($uploads['baseurl'])) {
                    $uploads['baseurl'] = preg_replace('#^(https?://|//)[^/]+/#', '/', $uploads['baseurl']);
                }
                return $uploads;
            }
        );

        // shifter app url -> relative path
        $shifter_content_filter = function ($content) {
            $content     = preg_replace('#(https?://|//)?([a-z0-9\-]+\.)?app\.getshifter\.io:[0-9]+/#', '/', $content);
            return $content;
        };
        add_filter('the_editor_content', $shifter_content_filter);
        add_filter('the_content', $shifter_content_filter);
    }
);

/**
 * Remove meta tags
 */
add_action(
    'template_redirect',
    function () {
        if (is_user_logged_in()) {
            return;
        }

        // remove meta tag
        remove_action('wp_head', 'feed_links', 2); //サイト全体のフィード
        remove_action('wp_head', 'feed_links_extra', 3); //その他のフィード
        remove_action('wp_head', 'rsd_link'); //Really Simple Discoveryリンク
        remove_action('wp_head', 'wlwmanifest_link'); //Windows Live Writerリンク
        //remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0); //前後の記事リンク
        remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0); //ショートリンク
        remove_action('wp_head', 'rel_canonical'); //canonical属性
        //remove_action('wp_head', 'wp_generator'); //WPバージョン
        remove_action('wp_head', 'rest_output_link_wp_head'); // wp-json
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
    },
    1
);

/**
 * Option page
 */
add_action(
    'init',
    function () {
        // add menu
        if (is_admin()) {
            add_action('admin_menu', 'shifter_add_settings_menu');
        }
    }
);

/**
 * Callback function for admin menu
 *
 * @return nothing
 */
function shifter_add_settings_menu()
{
    add_submenu_page(
        'shifter',
        'Shifter Settings',
        'Settings',
        'administrator',
        'shifter-settings',
        'shifter_settings_page'
    );
    add_action(
        'admin_init',
        'shifter_register_settings'
    );
}

/**
 * Callback function for option values
 *
 * @return nothing
 */
function shifter_register_settings()
{
    register_setting('shifter-options', 'shifter_skip_attachment');
    register_setting('shifter-options', 'shifter_skip_yearly');
    register_setting('shifter-options', 'shifter_skip_monthly');
    register_setting('shifter-options', 'shifter_skip_daily');
    register_setting('shifter-options', 'shifter_skip_terms');
    register_setting('shifter-options', 'shifter_skip_author');
    register_setting('shifter-options', 'shifter_skip_feed');
}

/**
 * Callback function for setting box
 *
 * @return nothing
 */
function shifter_settings_page()
{
    $options = [
        "shifter_skip_attachment" => "media pages",
        "shifter_skip_yearly"     => "yearly archives",
        "shifter_skip_monthly"    => "monthly archives",
        "shifter_skip_daily"      => "daily archives",
        "shifter_skip_terms"      => "term archives",
        "shifter_skip_author"     => "author archives",
        "shifter_skip_feed"       => "feeds",
    ];
?>


<div class="wrap">

<h1>Shifter</h1>

<div class="card">
<h2>Generator Settings</h2>

<form method="post" action="options.php">
    <p>Skip content you may not need and speed up the generating process. Selecting these options will exlucde them from your static Artifact.</p>
    <?php settings_fields('shifter-options'); ?>
    <?php do_settings_sections('shifter-options'); ?>
    <table class="form-table">
<?php foreach ($options as $key => $title) { ?>
        <tr valign="top">
        <th scope="row"><?php echo ucfirst($title); ?></th>
        <td>
            <input type="checkbox" name="<?php echo esc_attr($key); ?>" id="<?php echo esc_attr($key); ?>" value="yes" <?php echo get_option($key) === 'yes' ? 'checked ' : '' ; ?>/>
            <label for="<?php echo esc_attr($key); ?>">Skip <?php echo $title; ?></label>
        </td>
        </tr>
<?php } ?>
    </table>

    <?php submit_button(); ?>

</form>
</div>
</div>
<?php
}
