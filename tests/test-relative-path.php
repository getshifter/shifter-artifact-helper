<?php
/**
 * ShifterUrlsBase::home_path() / relative_path()
 */

require_once __DIR__ . '/stubs.php';
require_once __DIR__ . '/assert.php';

describe('home_path() - root install');
WpStubState::reset();
assert_same('/', ShifterUrlsBase::home_path(), "home_url 'https://example.com' -> '/'");

WpStubState::$home_url = 'https://example.com/';
assert_same('/', ShifterUrlsBase::home_path(), "home_url 'https://example.com/' -> '/'");

describe('home_path() - subdirectory install');
WpStubState::$home_url = 'https://example.com/SUBDIR';
assert_same('/SUBDIR/', ShifterUrlsBase::home_path(), "home_url '.../SUBDIR' -> '/SUBDIR/'");

WpStubState::$home_url = 'https://example.com/SUBDIR/';
assert_same('/SUBDIR/', ShifterUrlsBase::home_path(), "home_url '.../SUBDIR/' -> '/SUBDIR/'");

WpStubState::$home_url = 'https://example.com/foo/bar';
assert_same('/foo/bar/', ShifterUrlsBase::home_path(), "nested subdirectory -> '/foo/bar/'");

describe('relative_path() - root install');
WpStubState::reset();
assert_same('/', ShifterUrlsBase::relative_path('/'), "'/' -> '/'");
assert_same('/foo/', ShifterUrlsBase::relative_path('/foo/'), "'/foo/' -> '/foo/'");
assert_same(
    '/foo/',
    ShifterUrlsBase::relative_path('https://example.com/foo/'),
    "full URL -> '/foo/'"
);
assert_same(
    '/',
    ShifterUrlsBase::relative_path('https://example.com/'),
    "home URL -> '/'"
);
assert_same(
    '/',
    ShifterUrlsBase::relative_path('https://example.com'),
    "home URL without trailing slash -> '/'"
);

describe('relative_path() - subdirectory install');
WpStubState::$home_url = 'https://example.com/SUBDIR';
assert_same('/', ShifterUrlsBase::relative_path('/SUBDIR/'), "'/SUBDIR/' -> '/'");
assert_same('/', ShifterUrlsBase::relative_path('/SUBDIR'), "'/SUBDIR' -> '/'");
assert_same('/foo/', ShifterUrlsBase::relative_path('/SUBDIR/foo/'), "'/SUBDIR/foo/' -> '/foo/'");
assert_same(
    '/foo/',
    ShifterUrlsBase::relative_path('https://example.com/SUBDIR/foo/'),
    "full URL -> '/foo/'"
);
assert_same(
    '/shifter_404.html',
    ShifterUrlsBase::relative_path('/SUBDIR/shifter_404.html'),
    "404 path -> '/shifter_404.html'"
);
assert_same(
    '/subdir/foo/',
    ShifterUrlsBase::relative_path('/SUBDIR/subdir/foo/'),
    'only the leading home_path is stripped, once'
);

describe('relative_path() - already home relative (idempotency)');
WpStubState::$home_url = 'https://example.com/SUBDIR';
assert_same('/', ShifterUrlsBase::relative_path('/'), "'/' stays '/'");
assert_same(
    '/foo/',
    ShifterUrlsBase::relative_path(ShifterUrlsBase::relative_path('/SUBDIR/foo/')),
    'applying twice does not strip a second segment'
);

describe('relative_path() - a path segment equal to home_path name');
WpStubState::$home_url = 'https://example.com/blog';
assert_same('/', ShifterUrlsBase::relative_path('/blog/'), "'/blog/' -> '/'");
assert_same('/blog/', ShifterUrlsBase::relative_path('/blog/blog/'), "'/blog/blog/' -> '/blog/'");

describe('relative_path() - edge inputs');
WpStubState::reset();
assert_same('/', ShifterUrlsBase::relative_path(''), "empty string -> '/'");
assert_same('/foo/', ShifterUrlsBase::relative_path('foo/'), "no leading slash -> '/foo/'");

WpStubState::$home_url = 'https://example.com/SUBDIR';
assert_same(
    '/SUBDIRECTORY/',
    ShifterUrlsBase::relative_path('/SUBDIRECTORY/'),
    'a longer segment sharing the prefix is not stripped'
);

exit(TestResult::summary());
