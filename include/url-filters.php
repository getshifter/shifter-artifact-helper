<?php
if (!defined('ABSPATH')) {
    exit;
}

add_filter('ShifterURLS::AppendURLtoAll', function($urls) {
    $custom_urls = get_option('shifter_custom_urls', '');
    if (!empty($custom_urls)) {
        $custom_url_list = array_filter(array_map('trim', explode("\n", $custom_urls)));
        $urls = array_merge($urls, $custom_url_list);
    }
    return $urls;
});

add_filter('ShifterURLS::CheckURL', function($correct, $link) {
    if (!$correct) {
        return $correct;
    }
    
    $exclude_patterns = get_option('shifter_exclude_urls', '');
    if (empty($exclude_patterns)) {
        return $correct;
    }
    
    $patterns = array_filter(array_map('trim', explode("\n", $exclude_patterns)));
    foreach ($patterns as $pattern) {
        if (strpos($link, $pattern) !== false) {
            return false;
        }
        
        if (substr($pattern, 0, 1) === '.' && substr($link, -strlen($pattern)) === $pattern) {
            return false;
        }
    }
    
    return $correct;
}, 10, 2);
