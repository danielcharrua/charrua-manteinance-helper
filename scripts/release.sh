#!/usr/bin/env bash
set -e

# Read version from plugin header.
VERSION=$(grep -m1 '^ \* Version:' charrua-maintenance-helper.php | awk '{print $3}')

if [ -z "$VERSION" ]; then
    echo "Error: could not read version from plugin header." >&2
    exit 1
fi

# Ensure working tree is clean.
if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "Error: there are uncommitted changes. Commit or stash them first." >&2
    exit 1
fi

echo "Releasing version $VERSION..."

if gh release view "$VERSION" &>/dev/null; then
    echo "Release $VERSION already exists, deleting it..."
    gh release delete "$VERSION" --yes --cleanup-tag
fi

gh release create "$VERSION" \
    --title "$VERSION" \
    --notes "" \
    --target main

echo "Done. GitHub Action will build and attach the zip automatically."
