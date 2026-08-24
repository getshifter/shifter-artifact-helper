<?php
/**
 * Tiny assertion helper shared by the test scripts.
 */

class TestResult
{
    public static $passed = 0;
    public static $failed = 0;

    public static function ok($label)
    {
        self::$passed++;
        echo "  ok   {$label}\n";
    }

    public static function fail($label, $expected, $actual)
    {
        self::$failed++;
        echo "  FAIL {$label}\n";
        echo "       expected: " . var_export($expected, true) . "\n";
        echo "       actual:   " . var_export($actual, true) . "\n";
    }

    public static function summary()
    {
        $total = self::$passed + self::$failed;
        echo "\n{$total} assertions, " . self::$passed . " passed, " . self::$failed . " failed\n";
        return 0 === self::$failed ? 0 : 1;
    }
}

function assert_same($expected, $actual, $label)
{
    if ($expected === $actual) {
        TestResult::ok($label);
    } else {
        TestResult::fail($label, $expected, $actual);
    }
}

function describe($label)
{
    echo "\n{$label}\n";
}
