#!/bin/bash
# Run the PHP stub tests. No WordPress or PHPUnit required.
set -u
cd "$(dirname "$0")"

status=0
for test in test-*.php; do
    echo "== ${test}"
    php "${test}" || status=1
done
exit ${status}
