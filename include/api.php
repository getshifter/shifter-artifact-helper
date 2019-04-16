<?php
/**
 * Get URLs
 *
 * @param string  $request_path
 * @param boolean $rest_request
 * 
 * @return array|string
 */
function shifter_get_urls($request_path=null, $rest_request=false)
{
    if ($rest_request && '/'.ShifterUrlsBase::PATH_404_HTML !== $request_path) {
        $request_path = trailingslashit($request_path);
    }

    if (defined('POLYLANG_BASENAME')) {
        require_once __DIR__.'/class-shifter-urls-polylang.php';
        $shifter_urls = ShifterUrlsPolylang::get_instance();
    } else {
        $shifter_urls = ShifterUrlsBase::get_instance();
    }

    $page  = $shifter_urls->get_page(0);
    $limit = $shifter_urls->get_limit(100);
    $start = $page * $limit;

    $shifter_urls->set_url_count(0);
    $shifter_urls->set_transient_expires(intval(SHIFTER_URLS_CACHE_EXPIRES));
    $shifter_urls->set_start($start);
    $shifter_urls->set_end($start + $limit);
    if ($rest_request) {
        $shifter_urls->set_request_uri(home_url($request_path));
    }

    $json_data = [];
    $current_url_type = $shifter_urls->current_url_type($request_path, $rest_request);
    switch ($current_url_type) {
    case ShifterUrlsBase::URL_TOP:
        $json_data = $shifter_urls->get_urls_all();
        break;
    case ShifterUrlsBase::URL_ARCHIVE:
        $json_data = $shifter_urls->get_urls_archive();
        break;
    case ShifterUrlsBase::URL_SINGULAR:
        $json_data = $shifter_urls->get_urls_singular();
        break;
    case ShifterUrlsBase::URL_404:
        $json_data = $shifter_urls->get_urls_404();
        break;
    default:
        $json_data = $shifter_urls->get_urls();
    }
    unset($shifter_urls);

    // For debug
    if ($json_data['count'] > 0) {
        error_log("{$current_url_type}: {$request_path}");
        foreach ($json_data['items'] as $item) {
            error_log(json_encode($item));
        }
    }

    return $json_data;
}
