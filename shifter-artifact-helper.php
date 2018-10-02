<?php
/*
Plugin Name: Shifter – Artifact Helper
Plugin URI: https://github.com/getshifter/shifter-artifact-helper
Description: Helper tool for building Shifter Artifacts
Version: 0.11.1
Author: Shifter Team
Author URI: https://getshifter.io
License: GPLv2 or later
*/

function shifter_add_settings_menu(){
    add_submenu_page( 'shifter', 'Shifter Settings', 'Settings', 'administrator', 'shifter-settings', 'shifter_settings_page' );
}

// remove /index.php/ from Permalink
add_filter('got_rewrite', '__return_true');

require_once __DIR__ . '/include/class-shifter-urls.php';
add_action('template_redirect', function () {
    $shifter_urls = ShifterUrls::get_instance();
    $home_url     = $shifter_urls->get_home_url();
    $request_uri  = $shifter_urls->get_request_uri();

    if (!isset($_GET['urls'])) {
        if (preg_match('#/shifter_404\.html/?$#i', $request_uri)) {
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

    $shifter_urls->set_url_count(0);
    $shifter_urls->set_transient_expires(300);

    $shifter_urls->set_page(
        is_numeric($_GET['urls'])
        ? intval($_GET['urls'])
        : 0
    );
    $shifter_urls->set_limit(
        (isset($_GET['max']) && is_numeric($_GET['max']))
        ? intval($_GET['max'])
        : 100
    );
    $shifter_urls->set_start($shifter_urls->get_page() * $shifter_urls->get_limit());
    $shifter_urls->set_end($shifter_urls->get_start() + $shifter_urls->get_limit());

    $urls = $shifter_urls->get_urls();

    header('Content-Type: application/json');

    if (preg_match('#/shifter_404\.html/?$#i', $request_uri)) {
        $urls['items'] = [];
        $shifter_urls->set_url_count(0);
        $shifter_urls->set_urls($urls);

    } else if (is_front_page() && preg_replace('#^https://[^/]+/#', '/', $home_url) === preg_replace('#^https://[^/]+/#', '/', $request_uri)) {
        // top page & feed links
        $shifter_urls->top_page_urls($urls);

        // posts links
        $shifter_urls->posts_urls($urls);

        // archive links
        $shifter_urls->post_type_archive_urls($urls);

        // term links
        $shifter_urls->post_type_term_urls($urls);

        // date archives
        $shifter_urls->archive_urls($urls);

        // pagenate links
        $shifter_urls->pagenate_urls($urls);

        // authors link
        $shifter_urls->authors_urls($urls);

        // redirection link (redirection plugin)
        $shifter_urls->redirection_urls($urls);

    } else if (!is_singular()) {
        // pagenate links
        $shifter_urls->pagenate_urls($urls, $request_uri);

    } else {
        // single page links
        $shifter_urls->singlepage_pagenate_urls($urls, $request_uri);
    }

    $urls['count'] = count($urls['items']);
    $urls['finished'] = $urls['count'] < $shifter_urls->get_limit();
    if ($urls['count'] <= 0) {
        header("HTTP/1.1 404 Not Found");
    } else {
        error_log('');
        foreach ($urls['items'] as $item) {
            error_log(json_encode($item));
        }
    }

    echo json_encode($urls);
    die();
});

add_action( 'init', function() {
    // upload dir -> relative path
    add_filter( 'upload_dir', function($uploads) {
        $parsed_url  = parse_url( home_url() );
        $host_name   = $parsed_url['host'];
        $server_name = $host_name . ( isset($parsed_url['port']) ? ':'.$parsed_url['port'] : '' );
        if ( isset( $uploads['url'] ) )
            $uploads['url'] = preg_replace( '#^(https?://|//)[^/]+/#', '/', $uploads['url'] );
        if ( isset( $uploads['baseurl'] ) )
            $uploads['baseurl'] = preg_replace( '#^(https?://|//)[^/]+/#', '/', $uploads['baseurl'] );
        return $uploads;
    });

    // shifter app url -> relative path
    $shifter_content_filter = function($content) {
        $content     = preg_replace( '#(https?://|//)?([a-z0-9\-]+\.)?app\.getshifter\.io:[0-9]+/#', '/', $content );
        return $content;
    };
    add_filter( 'the_editor_content', $shifter_content_filter );
    add_filter( 'the_content', $shifter_content_filter );

    // add menu
    if ( is_admin() ){
        add_action( 'admin_menu', 'shifter_add_settings_menu' );
    }
});

add_action( 'template_redirect', function() {
    if ( is_user_logged_in() ) {
        return;
    }

    // remove meta tag
    remove_action( 'wp_head', 'feed_links', 2 ); //サイト全体のフィード
    remove_action( 'wp_head', 'feed_links_extra', 3 ); //その他のフィード
    remove_action( 'wp_head', 'rsd_link' ); //Really Simple Discoveryリンク
    remove_action( 'wp_head', 'wlwmanifest_link' ); //Windows Live Writerリンク
    //remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0 ); //前後の記事リンク
    remove_action( 'wp_head', 'wp_shortlink_wp_head', 10, 0 ); //ショートリンク
    remove_action( 'wp_head', 'rel_canonical' ); //canonical属性
    //remove_action( 'wp_head', 'wp_generator' ); //WPバージョン
    remove_action( 'wp_head', 'rest_output_link_wp_head' ); // wp-json
    remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
    remove_action( 'wp_head', 'wp_oembed_add_host_js' );
}, 1);

function shifter_register_settings(){
    register_setting( 'shifter-options', 'shifter_skip_attachment' );
    register_setting( 'shifter-options', 'shifter_skip_yearly' );
    register_setting( 'shifter-options', 'shifter_skip_monthly' );
    register_setting( 'shifter-options', 'shifter_skip_daily' );
    register_setting( 'shifter-options', 'shifter_skip_terms' );
    register_setting( 'shifter-options', 'shifter_skip_author' );
    register_setting( 'shifter-options', 'shifter_skip_feed' );
}

function shifter_settings_page(){
    $options = array(
        "shifter_skip_attachment" => "media pages",
        "shifter_skip_yearly"     => "yearly archives",
        "shifter_skip_monthly"    => "monthly archives",
        "shifter_skip_daily"      => "daily archives",
        "shifter_skip_terms"      => "term archives",
        "shifter_skip_author"     => "author archives",
        "shifter_skip_feed"       => "feeds",
    );
?>


<div class="wrap">

<h1>Shifter</h1>

<div class="card">
<h2>Generator Settings</h2>

<form method="post" action="options.php">
    <p>Skip content you may not need and speed up the generating process. Selecting these options will exlucde them from your static Artifact.</p>
    <?php settings_fields( 'shifter-options' ); ?>
    <?php do_settings_sections( 'shifter-options' ); ?>
    <table class="form-table">
<?php foreach($options as $key => $title) { ?>
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
