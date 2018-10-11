<?php
/*
Plugin Name: Shifter – Artifact Helper
Plugin URI: https://github.com/getshifter/shifter-artifact-helper
Description: Helper tool for building Shifter Artifacts
Version: 1.0.0
Author: Shifter Team
Author URI: https://getshifter.io
License: GPLv2 or later
*/

// Shifter URLs
require_once __DIR__ . '/include/class-shifter-urls.php';
function shifter_init_urls($request_path=null, $rest_request=false)
{
    static $shifter_urls;

    if (!$shifter_urls) {
        $shifter_urls = ShifterUrls::get_instance();

        $page  = $shifter_urls->get_page(0);
        $limit = $shifter_urls->get_limit(100);
        $start = $page * $limit;

        $shifter_urls->set_url_count(0);
        $shifter_urls->set_transient_expires(300);
        $shifter_urls->set_start($start);
        $shifter_urls->set_end($start + $limit);
        if ($rest_request) {
            $shifter_urls->set_request_uri(home_url($request_path));
        }
    }

    return $shifter_urls;
}
function shifter_get_urls($request_path, $rest_request=false)
{
    if ($rest_request && '/'.ShifterUrls::PATH_404_HTML !== $request_path) {
        $request_path = trailingslashit($request_path);
    }
    $shifter_urls = shifter_init_urls($request_path, $rest_request);

    $json_data = [];
    switch ($shifter_urls->current_url_type($request_path, $rest_request)) {
    case ShifterUrls::URL_TOP:
        $json_data = $shifter_urls->get_urls_all();
        break;
    case ShifterUrls::URL_ARCHIVE:
        $json_data = $shifter_urls->get_urls_archive();
        break;
    case ShifterUrls::URL_SINGULAR:
        $json_data = $shifter_urls->get_urls_singular();
        break;
    case ShifterUrls::URL_404:
        $json_data = $shifter_urls->get_urls_404();
        break;
    default:
        $json_data = $shifter_urls->get_urls();
    }
    unset($shifter_urls);

    // For debug
    if ($json_data['count'] > 0) {
        error_log('');
        foreach ($json_data['items'] as $item) {
            error_log(json_encode($item));
        }
    }

    return $json_data;
}

// Shifter URLs v1
add_action(
    'template_redirect',
    function () {
        $shifter_urls = shifter_init_urls();
        $request_uri  = $shifter_urls->get_request_uri();
        if (!isset($_GET['urls'])) {
            if (preg_match('#/'.preg_quote(ShifterUrls::PATH_404_HTML).'/?$#i', $request_uri)) {
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

        $json_data = shifter_get_urls(
            preg_replace('#^https?://[^/]+/#', '/', $request_uri),
            false
        );

        if ($json_data['count'] <= 0) {
            header("HTTP/1.1 404 Not Found");
        }
        header('Content-Type: application/json');
        echo json_encode($json_data);
        die();
    }
);

// Shifter URLs v2 (WP JSON REST API)
add_action(
    'rest_api_init',
    function () {
        register_rest_route(
            ShifterUrls::REST_ENDPOINT,
            ShifterUrls::REST_PATH,
            [
                'methods'  => WP_REST_Server::READABLE,
                'callback' => 'shifter_urls'
            ]
        );
        register_rest_route(
            ShifterUrls::REST_ENDPOINT,
            ShifterUrls::REST_PATH.'/(?P<path>.+)',
            [
                'methods'  => WP_REST_Server::READABLE,
                'callback' => 'shifter_urls'
            ]
        );
    }
);

function shifter_urls($data=[])
{
    if (!defined('SHIFTER_REST_REQUEST')) {
        define('SHIFTER_REST_REQUEST', true);
    }

    $json_data = shifter_get_urls(
        '/' . (isset($data['path']) && !empty($data['path']) ? $data['path'] : ''),
        true
    );
    $json_data['page']++;

    $response = new WP_REST_Response($json_data);
    $response->set_status(200);

    return $response;
}

// Shifter customize

// remove /index.php/ from Permalink
add_filter('got_rewrite', '__return_true');

// relative path
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

// remove meta tags
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

// Option page
add_action(
    'init',
    function () {
        // add menu
        if (is_admin()) {
            add_action('admin_menu', 'shifter_add_settings_menu');
        }
    }
);

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
