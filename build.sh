#!/usr/bin/env bash
# Build the distributable zip of Uraio.
#
# Usage: ./build.sh
# Output: dist/uraio-v<version>.zip  (e.g. dist/uraio-v1.0.5.zip)

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

if [[ ! -d .git ]]; then
    echo "build.sh must run inside a git checkout of the repository." >&2
    exit 1
fi

VERSION="$(grep -m1 'UBOS_VERSION' includes/bootstrap.php | sed -E "s/.*'([0-9][0-9.]*)'.*/\1/")"
if [[ -z "$VERSION" ]]; then
    echo "Could not read UBOS_VERSION from includes/bootstrap.php." >&2
    exit 1
fi

OUT="$ROOT/dist/uraio-v${VERSION}.zip"
mkdir -p "$ROOT/dist"
rm -f "$OUT"

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

# Export exactly what is committed, with the archive rooted in an uraio/ folder.
git archive --format=tar --prefix=uraio/ HEAD > "$STAGE/uraio.tar"
mkdir -p "$STAGE/x"
tar -xf "$STAGE/uraio.tar" -C "$STAGE/x"

# Sanity checks: never ship secrets or an install lock.
for forbidden in config/credentials.php storage/installed.lock; do
    if [[ -f "$STAGE/x/uraio/$forbidden" ]]; then
        echo "Refusing to build: $forbidden is present in the package." >&2
        exit 1
    fi
done

(cd "$STAGE/x" && zip -r -X "$OUT" uraio -x '*.DS_Store' >/dev/null)

echo "Built $OUT (v$VERSION)"
unzip -l "$OUT" | tail -4
