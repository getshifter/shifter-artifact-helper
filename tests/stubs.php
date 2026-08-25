<?php
/**
 * Minimal WordPress function stubs to exercise ShifterUrlsBase path handling
 * without a WordPress installation.
 *
 * Only the functions reached by the tested code paths are implemented.
 */

define('SHIFTER_REST_ENDPOINT', 'shifter/v1');

class WpStubState
{
    /** @var string home_url() base, without trailing slash */
    public static $home_url = 'https://example.com';

    /** @var boolean */
    public static $is_404 = false;

    /** @var boolean */
    public static $is_front_page = false;

    /** @var boolean */
    public static $is_singular = false;

    /** @var array map of URL => post ID */
    public static $post_ids = [];

    /** @var array transient store */
    public static $transients = [];

    public static function reset()
    {
        self::$home_url = 'https://example.com';
        self::$is_404 = false;
        self::$is_front_page = false;
        self::$is_singular = false;
        self::$post_ids = [];
        self::$transients = [];
    }
}

function home_url($path = '')
{
    $base = rtrim(WpStubState::$home_url, '/');
    if ('' === $path) {
        return $base;
    }
    return $base . '/' . ltrim($path, '/');
}

function trailingslashit($string)
{
    return rtrim($string, '/\\') . '/';
}

function untrailingslashit($string)
{
    return rtrim($string, '/\\');
}

function remove_query_arg($keys, $query)
{
    $parts = explode('?', $query, 2);
    if (!isset($parts[1])) {
        return $parts[0];
    }
    $kept = [];
    foreach (explode('&', $parts[1]) as $pair) {
        if ('' === $pair) {
            continue;
        }
        $name = explode('=', $pair, 2)[0];
        if (!in_array($name, (array)$keys, true)) {
            $kept[] = $pair;
        }
    }
    return empty($kept) ? $parts[0] : $parts[0] . '?' . implode('&', $kept);
}

function esc_html($text)
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function is_404()
{
    return WpStubState::$is_404;
}

function is_front_page()
{
    return WpStubState::$is_front_page;
}

function is_singular()
{
    return WpStubState::$is_singular;
}

function url_to_postid($url)
{
    return isset(WpStubState::$post_ids[$url]) ? WpStubState::$post_ids[$url] : 0;
}

function get_transient($key)
{
    return isset(WpStubState::$transients[$key]) ? WpStubState::$transients[$key] : false;
}

function set_transient($key, $value, $expires = 0)
{
    WpStubState::$transients[$key] = $value;
    return true;
}

function delete_transient($key)
{
    unset(WpStubState::$transients[$key]);
    return true;
}

function get_option($name, $default = false)
{
    return $default;
}

function get_query_var($name, $default = '')
{
    return $default;
}

function apply_filters($tag, $value)
{
    return $value;
}

require_once __DIR__ . '/../include/class-shifter-urls-base.php';
