<?php
/**
 * ShifterUrlsBase::current_url_type() for root and subdirectory installs.
 */

require_once __DIR__ . '/stubs.php';
require_once __DIR__ . '/assert.php';

/**
 * Build a fresh instance so the internal value cache does not leak between cases.
 */
function url_type_for_request($home_url, $request_uri, $wp_state = [])
{
    WpStubState::reset();
    WpStubState::$home_url = $home_url;
    foreach ($wp_state as $key => $value) {
        WpStubState::$$key = $value;
    }
    $_SERVER['REQUEST_URI'] = $request_uri;

    $urls = new ShifterUrlsBase();
    return $urls->current_url_type();
}

function url_type_for_rest($home_url, $request_path, $wp_state = [])
{
    WpStubState::reset();
    WpStubState::$home_url = $home_url;
    foreach ($wp_state as $key => $value) {
        WpStubState::$$key = $value;
    }
    $_SERVER['REQUEST_URI'] = '/wp-json/shifter/v1/urls' . $request_path;

    $urls = new ShifterUrlsBase();
    return $urls->current_url_type($request_path, true);
}

describe('current_url_type() - root install (?urls=)');
assert_same(
    ShifterUrlsBase::URL_TOP,
    url_type_for_request('https://example.com', '/?urls=0', ['is_front_page' => true]),
    'front page -> TOP'
);
assert_same(
    ShifterUrlsBase::URL_ARCHIVE,
    url_type_for_request('https://example.com', '/category/news/?urls=0'),
    'category archive -> ARCHIVE'
);
assert_same(
    ShifterUrlsBase::URL_SINGULAR,
    url_type_for_request('https://example.com', '/hello/?urls=0', ['is_singular' => true]),
    'single post -> SINGULAR'
);
assert_same(
    ShifterUrlsBase::URL_404,
    url_type_for_request('https://example.com', '/shifter_404.html'),
    '404 placeholder -> 404'
);

describe('current_url_type() - subdirectory install (?urls=) [STATIC-5310]');
assert_same(
    ShifterUrlsBase::URL_TOP,
    url_type_for_request('https://example.com/SUBDIR', '/SUBDIR/?urls=0', ['is_front_page' => true]),
    'subdirectory front page -> TOP'
);
assert_same(
    ShifterUrlsBase::URL_ARCHIVE,
    url_type_for_request('https://example.com/SUBDIR', '/SUBDIR/category/news/?urls=0'),
    'subdirectory category archive -> ARCHIVE'
);
assert_same(
    ShifterUrlsBase::URL_SINGULAR,
    url_type_for_request('https://example.com/SUBDIR', '/SUBDIR/hello/?urls=0', ['is_singular' => true]),
    'subdirectory single post -> SINGULAR'
);
assert_same(
    ShifterUrlsBase::URL_404,
    url_type_for_request('https://example.com/SUBDIR', '/SUBDIR/shifter_404.html'),
    'subdirectory 404 placeholder -> 404'
);
assert_same(
    ShifterUrlsBase::URL_404,
    url_type_for_request('https://example.com/SUBDIR', '/SUBDIR/missing/?urls=0', ['is_404' => true]),
    'subdirectory 404 response -> 404'
);

describe('current_url_type() - REST v2 receives home relative paths');
assert_same(
    ShifterUrlsBase::URL_TOP,
    url_type_for_rest('https://example.com/SUBDIR', '/'),
    "REST '/' on a subdirectory install -> TOP"
);
assert_same(
    ShifterUrlsBase::URL_SINGULAR,
    url_type_for_rest(
        'https://example.com/SUBDIR',
        '/hello/',
        ['post_ids' => ['https://example.com/SUBDIR/hello/' => 12]]
    ),
    'REST single post resolves post_id through home_url()'
);
assert_same(
    ShifterUrlsBase::URL_ARCHIVE,
    url_type_for_rest('https://example.com/SUBDIR', '/category/news/'),
    'REST path with no post_id -> ARCHIVE'
);
assert_same(
    ShifterUrlsBase::URL_404,
    url_type_for_rest('https://example.com/SUBDIR', '/shifter_404.html'),
    'REST 404 placeholder -> 404'
);

describe('current_url_type() - REST path must not be re-normalized');
// home_path is '/blog/' and the requested post slug is also 'blog'.
// The REST argument is already home relative, so it must stay '/blog/'.
assert_same(
    ShifterUrlsBase::URL_SINGULAR,
    url_type_for_rest(
        'https://example.com/blog',
        '/blog/',
        ['post_ids' => ['https://example.com/blog/blog/' => 34]]
    ),
    "REST '/blog/' under home_path '/blog/' stays SINGULAR"
);

exit(TestResult::summary());
