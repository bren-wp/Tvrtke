#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."
php -S 127.0.0.1:18123 -t backend/public backend/public/router.php >/tmp/tvrtke-http-test.log 2>&1 &
PID=$!
trap 'kill "$PID" 2>/dev/null || true' EXIT
sleep 1
assert_code() {
  local expected=$1 path=$2 actual
  actual=$(curl -sS -o /dev/null -w '%{http_code}' "http://127.0.0.1:18123${path}")
  [[ "$actual" == "$expected" ]] || { echo "Expected $expected for $path; got $actual"; exit 1; }
}
assert_code 200 '/v1/health'
assert_code 422 '/v1/subjects?status=abc'
assert_code 422 '/v1/subjects?limit=500'
assert_code 422 '/v1/subjects?legal_form=abc'
assert_code 422 '/v1/subjects?q%5B%5D=x'
assert_code 422 '/v1/directory?type=invalid'
assert_code 422 '/v1/directory?page=501'
assert_code 422 '/v1/directory?limit=501'
assert_code 422 '/v1/directory?type%5B%5D=obrt'
assert_code 404 '/v1/directory/obrt/not-a-valid-hash'
assert_code 404 '/v1/directory/sudreg/abc'
assert_code 404 '/v1/not-found'
echo 'HTTP validation tests OK'
