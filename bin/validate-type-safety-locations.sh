#!/usr/bin/env bash
# Checklist item 5: deliberate violations under plugin/ and phpcs/ with full ruleset.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

SERVICE="${PHP_LINT_SERVICE:-wordpress}"
WORK_DIR="/var/www/html"

run_full_phpcs() {
	docker compose exec -T -w "$WORK_DIR" "$SERVICE" php vendor/bin/phpcs \
		--standard=.phpcs.xml.dist \
		"$1" 2>&1
}

expect_errors() {
	local file="$1"
	local label="$2"
	set +e
	local out
	out="$(run_full_phpcs "$file")"
	local status=$?
	set -e

	if [[ $status -ne 1 && $status -ne 2 ]]; then
		echo "FAIL: $label — expected violations, phpcs exit $status"
		echo "$out"
		exit 1
	fi
	if ! echo "$out" | grep -q "ERROR"; then
		echo "FAIL: $label — expected ERROR in output"
		echo "$out"
		exit 1
	fi
	echo "OK: $label"
}

if ! docker compose exec -T "$SERVICE" true 2>/dev/null; then
	echo "Error: WordPress service is not running. Start it with: docker compose up -d" >&2
	exit 1
fi

# Outside **/tmp/** (excluded in .phpcs.xml.dist).
PLUGIN_PROBE="${WORK_DIR}/wp-content/plugins/post-to-convex/typesafety-location-probe.php"
PHPCS_PROBE="${WORK_DIR}/phpcs/TypeSafety/typesafety-location-probe.php"

docker compose exec -T "$SERVICE" mkdir -p "$(dirname "$PLUGIN_PROBE")" "$(dirname "$PHPCS_PROBE")"

docker compose exec -T "$SERVICE" tee "$PLUGIN_PROBE" >/dev/null <<'PHP'
<?php
/**
 * @package PostToConvex
 */

namespace PostToConvex;

class TypeSafetyLocationProbe {

	/**
	 * @return void
	 */
	public function run( string $value ): void {
	}
}
PHP

docker compose exec -T "$SERVICE" tee "$PHPCS_PROBE" >/dev/null <<'PHP'
<?php
/**
 * @package TypeSafety
 */

namespace TypeSafetyProbe;

class TypeSafetyLocationProbe {

	/**
	 * @return void
	 */
	public function run( string $value ): void {
	}
}
PHP

expect_errors "$PLUGIN_PROBE" "plugin path: missing @param reports ERROR"
expect_errors "$PHPCS_PROBE" "phpcs/ path: missing @param reports ERROR"

docker compose exec -T "$SERVICE" rm -f "$PLUGIN_PROBE" "$PHPCS_PROBE"

echo ""
echo "Location probes passed."
