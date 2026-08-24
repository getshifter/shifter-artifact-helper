<?php
/**
 * ShifterUrlsBase get('current_url') / get('request_path')
 *
 * current_url はパスをURLへ復元する箇所なので home 相対パスを基準にする必要がある。
 * request_path はサイト絶対パスのまま維持する (JSONレスポンスの出力値)。
 */

require_once __DIR__ . '/stubs.php';
require_once __DIR__ . '/assert.php';

/**
 * Build a fresh instance so the internal value cache does not leak between cases.
 */
function urls_for($home_url, $request_uri)
{
    WpStubState::reset();
    WpStubState::$home_url = $home_url;
    $_SERVER['REQUEST_URI'] = $request_uri;

    return new ShifterUrlsBase();
}

describe('current_url - root install');
$urls = urls_for('https://example.com', '/foo/?urls=0');
assert_same(
    'https://example.com/foo/',
    $urls->get_current_url(),
    'root install rebuilds the requested URL'
);

$urls = urls_for('https://example.com', '/?urls=0');
assert_same(
    'https://example.com/',
    $urls->get_current_url(),
    'root install front page'
);

describe('current_url - subdirectory install');
$urls = urls_for('https://example.com/SUBDIR', '/SUBDIR/foo/?urls=0');
assert_same(
    'https://example.com/SUBDIR/foo/',
    $urls->get_current_url(),
    'subdirectory install must not double the path prefix'
);

$urls = urls_for('https://example.com/SUBDIR', '/SUBDIR/?urls=0');
assert_same(
    'https://example.com/SUBDIR/',
    $urls->get_current_url(),
    'subdirectory front page'
);

describe('request_path stays a site absolute path (JSON response value)');
$urls = urls_for('https://example.com/SUBDIR', '/SUBDIR/foo/?urls=0');
assert_same(
    '/SUBDIR/foo/',
    $urls->get_request_path(),
    'request_path keeps the subdirectory prefix'
);
assert_same(
    '/foo/',
    $urls->get_relative_request_path(),
    'relative_request_path drops the subdirectory prefix'
);

$urls = urls_for('https://example.com', '/foo/?urls=0');
assert_same(
    '/foo/',
    $urls->get_request_path(),
    'root install: request_path unchanged'
);
assert_same(
    '/foo/',
    $urls->get_relative_request_path(),
    'root install: both representations agree'
);

exit(TestResult::summary());
