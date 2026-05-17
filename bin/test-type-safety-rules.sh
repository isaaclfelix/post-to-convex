#!/usr/bin/env bash
# Probes TypeSafety PHPCS rules with deliberate violations (scratch files under phpcs/TypeSafety/.probe/).
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

SERVICE="${PHP_LINT_SERVICE:-wordpress}"
WORK_DIR="/var/www/html"
PROBE_DIR="/tmp/typesafety-phpcs-probe"

run_phpcs() {
	docker compose exec -T -w "$WORK_DIR" "$SERVICE" php vendor/bin/phpcs \
		--standard=phpcs/TypeSafety/ruleset.xml \
		--sniffs="$1" \
		"${PROBE_DIR}/$(basename "$2")" 2>&1
}

expect_code() {
	local sniff="$1"
	local file="$2"
	local expect="$3"
	local label="$4"
	set +e
	local out
	out="$(run_phpcs "$sniff" "$file")"
	local status=$?
	set -e

	if [[ "$expect" == "error" && $status -ne 1 && $status -ne 2 ]]; then
		echo "FAIL: $label — expected violations, phpcs exit $status"
		echo "$out"
		exit 1
	fi
	if [[ "$expect" == "clean" && $status -ne 0 ]]; then
		echo "FAIL: $label — expected clean, phpcs exit $status"
		echo "$out"
		exit 1
	fi
	if [[ "$expect" == "error" ]] && ! echo "$out" | grep -q "ERROR"; then
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

docker compose exec -T "$SERVICE" rm -rf "$PROBE_DIR"
docker compose exec -T "$SERVICE" mkdir -p "$PROBE_DIR"

docker compose exec -T "$SERVICE" tee "${PROBE_DIR}/missing-constant-var.php" >/dev/null <<'PHP'
<?php
/**
 * @package TypeSafetyProbe
 */

declare( strict_types=1 );

namespace TypeSafetyProbe;

class MissingConstantVar {

	/**
	 * No @var and no native type.
	 */
	public const FOO = 'bar';

	/**
	 * @return void
	 */
	public function ok(): void {
	}
}
PHP

docker compose exec -T "$SERVICE" tee "${PROBE_DIR}/valid-constant-var.php" >/dev/null <<'PHP'
<?php
/**
 * @package TypeSafetyProbe
 */

declare( strict_types=1 );

namespace TypeSafetyProbe;

class ValidConstantVar {

	/**
	 * @var string
	 */
	public const FOO = 'bar';

	/**
	 * @return void
	 */
	public function ok(): void {
	}
}
PHP

docker compose exec -T "$SERVICE" tee "${PROBE_DIR}/missing-strict-types.php" >/dev/null <<'PHP'
<?php
/**
 * @package TypeSafetyProbe
 */

namespace TypeSafetyProbe;

class MissingStrictTypes {

	/**
	 * @return void
	 */
	public function ok(): void {
	}
}
PHP

docker compose exec -T "$SERVICE" tee "${PROBE_DIR}/missing-param-doc.php" >/dev/null <<'PHP'
<?php
/**
 * @package TypeSafetyProbe
 */

declare( strict_types=1 );

namespace TypeSafetyProbe;

class MissingParamDoc {

	/**
	 * @return void
	 */
	public function run( string $value ): void {
	}
}
PHP

docker compose exec -T "$SERVICE" tee "${PROBE_DIR}/missing-return-type.php" >/dev/null <<'PHP'
<?php
/**
 * @package TypeSafetyProbe
 */

declare( strict_types=1 );

namespace TypeSafetyProbe;

class MissingReturnType {

	/**
	 * @return void
	 */
	public function run() {
	}
}
PHP

expect_code "TypeSafety.TypeHints.ClassConstantVarAnnotation" "missing-constant-var.php" "error" "class constant without @var is reported"
expect_code "TypeSafety.TypeHints.ClassConstantVarAnnotation" "valid-constant-var.php" "clean" "class constant with @var passes"
expect_code "SlevomatCodingStandard.TypeHints.DeclareStrictTypes" "missing-strict-types.php" "error" "missing strict_types is reported"
expect_code "Squiz.Commenting.FunctionComment" "missing-param-doc.php" "error" "missing @param is reported"
expect_code "SlevomatCodingStandard.TypeHints.ReturnTypeHint" "missing-return-type.php" "error" "missing native return type is reported"

docker compose exec -T "$SERVICE" rm -rf "$PROBE_DIR"

echo ""
echo "All TypeSafety rule probes passed."
