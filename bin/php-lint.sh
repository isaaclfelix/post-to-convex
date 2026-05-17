#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

SERVICE="${PHP_LINT_SERVICE:-wordpress}"
WORK_DIR="/var/www/html"

if ! docker compose exec -T "$SERVICE" true 2>/dev/null; then
	echo "Error: WordPress service is not running. Start it with: docker compose up -d" >&2
	exit 1
fi

run_phpcs() {
	docker compose exec -T -w "$WORK_DIR" "$SERVICE" php vendor/bin/phpcs "$@"
}

run_phpcbf() {
	docker compose exec -T -w "$WORK_DIR" "$SERVICE" php vendor/bin/phpcbf "$@"
}

if [[ "${1:-}" == "--fix" ]]; then
	shift
	run_phpcbf "$@"
else
	run_phpcs "$@"
fi
