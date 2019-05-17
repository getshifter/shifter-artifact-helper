<?php
if (!defined('ABSPATH')) {
    exit; // don't access directly
};

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
        remove_action('wp_head', 'wp_shortlink_wp_head', 10); //ショートリンク
        remove_action('wp_head', 'rel_canonical'); //canonical属性
        //remove_action('wp_head', 'wp_generator'); //WPバージョン
        remove_action('wp_head', 'rest_output_link_wp_head'); // wp-json
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
    },
    1
);
