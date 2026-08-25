<?php
/**
 * ShifterUrlsBase::is_404_html_request()
 *
 * shifter-artifact-helper.php の template_redirect フックが
 * shifter_404.html を出力するかどうかの判定。
 */

require_once __DIR__ . '/stubs.php';
require_once __DIR__ . '/assert.php';

function is_404_html_request_for($home_url, $request_uri)
{
    WpStubState::reset();
    WpStubState::$home_url = $home_url;

    return ShifterUrlsBase::is_404_html_request($request_uri);
}

describe('is_404_html_request() - root install');
assert_same(
    true,
    is_404_html_request_for('https://example.com', '/shifter_404.html'),
    '/shifter_404.html -> true'
);
assert_same(
    true,
    is_404_html_request_for('https://example.com', '/shifter_404.html/'),
    'trailing slash -> true'
);
assert_same(
    true,
    is_404_html_request_for('https://example.com', '/shifter_404.html?max=10'),
    'query args stripped by link_normalize() -> true'
);
assert_same(
    false,
    is_404_html_request_for('https://example.com', '/'),
    'front page -> false'
);
assert_same(
    false,
    is_404_html_request_for('https://example.com', '/foo/'),
    'other page -> false'
);
assert_same(
    false,
    is_404_html_request_for('https://example.com', '/foo/shifter_404.html'),
    'nested path is not the placeholder -> false'
);

describe('is_404_html_request() - subdirectory install [STATIC-5310]');
assert_same(
    true,
    is_404_html_request_for('https://example.com/SUBDIR', '/SUBDIR/shifter_404.html'),
    '/SUBDIR/shifter_404.html -> true'
);
assert_same(
    true,
    is_404_html_request_for('https://example.com/SUBDIR', '/SUBDIR/shifter_404.html/'),
    'trailing slash -> true'
);
assert_same(
    false,
    is_404_html_request_for('https://example.com/SUBDIR', '/SUBDIR/'),
    'subdirectory front page -> false'
);
assert_same(
    false,
    is_404_html_request_for('https://example.com/SUBDIR', '/SUBDIR/foo/shifter_404.html'),
    'nested path under the subdirectory is not the placeholder -> false'
);

describe('is_404_html_request() - the generated placeholder URL is accepted');
// _top_page_urls() は home_url('/') . PATH_404_HTML を列挙する。
// クローラーがその完全URLで戻ってきても判定できること。
assert_same(
    true,
    is_404_html_request_for(
        'https://example.com/SUBDIR',
        'https://example.com/SUBDIR/shifter_404.html'
    ),
    'full URL form -> true'
);

exit(TestResult::summary());
