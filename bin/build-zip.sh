#!/usr/bin/env bash
# Build an installable plugin zip from a committed ref (default: HEAD).
#
#   bin/build-zip.sh            -> dist/echo-ui-wordpress-toastr-plugin-<version>.zip
#   bin/build-zip.sh v0.2.0
#
# Dev-only files are excluded via export-ignore rules in .gitattributes.
set -euo pipefail

cd "$(dirname "$0")/.."

slug="echo-ui-wordpress-toastr-plugin"
ref="${1:-HEAD}"
version="$(git show "$ref:$slug.php" | sed -n 's/^ \* Version: *//p' | tr -d '\r')"

if [ -z "$version" ]; then
	echo "Could not read the Version header from $slug.php at $ref" >&2
	exit 1
fi

mkdir -p dist
out="dist/$slug-$version.zip"
git archive --format=zip --prefix="$slug/" -o "$out" "$ref"
echo "$out"
