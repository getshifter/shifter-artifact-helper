<?php
if (!defined('ABSPATH')) {
    exit; // don't access directly
};

/**
 * Relative path
 */
function shifter_convert_app_url($content) {
    return preg_replace(
        '#(https://|//)[0-9a-z]{8}-[0-9a-z]{4}-[0-9a-z]{4}-[0-9a-z]{4}-[0-9a-z]{12}\.(app|appdev)\.getshifter\.io(:[0-9]{5})?/#',
        '/',
        $content
    );
}
add_action(
    'init',
    function () {
        // upload dir -> relative path
        add_filter(
            'upload_dir',
            function ($uploads) {
                /** @var string[] */
                $parsed_url  = parse_url(home_url());
                $host_name   = $parsed_url['host'];
                $server_name = $host_name . (isset($parsed_url['port']) ? ':'.$parsed_url['port'] : '');
                if (isset($uploads['url'])) {
                    $uploads['url'] = shifter_convert_app_url($uploads['url']);
                }
                if (isset($uploads['baseurl'])) {
                    $uploads['baseurl'] = shifter_convert_app_url($uploads['baseurl']);
                }
                return $uploads;
            }
        );
        add_filter('the_editor_content', 'shifter_convert_app_url');
        add_filter('the_content', 'shifter_convert_app_url');
    }
);
