#!/bin/sh
#
# Mirror a commit's distributable plugin files into the WordPress.org SVN checkout.
#
#   bin/svn-sync.sh [<commit-ish>]     (default: main)
#
# Runs automatically from .githooks/pre-push whenever main is pushed. It exports
# the given commit with `git archive` — never the working tree, so uncommitted
# changes and other branches can't leak in — then copies everything not listed
# in .distignore into <svn>/trunk, deleting files there that no longer exist.
# If the commit has a .wordpress-org/ folder it is mirrored into <svn>/assets.
# It never runs `svn add`/`svn commit` — review with `svn status` in the
# checkout and commit by hand.
#
# The checkout defaults to ../../repositories-svn/svn-cosi-tags; override
# with COSI_TAGS_SVN_DIR=/path/to/checkout. If the checkout is missing the
# sync is skipped (with a warning), so a push never fails because of it.

set -e

REF=${1:-main}
PLUGIN_DIR=$(cd "$(dirname "$0")/.." && pwd)
SVN_DIR=${COSI_TAGS_SVN_DIR:-"$PLUGIN_DIR/../../repositories-svn/svn-cosi-tags"}

if [ ! -d "$SVN_DIR/.svn" ]; then
	echo "svn-sync: no SVN checkout at $SVN_DIR — skipping." >&2
	exit 0
fi

SVN_DIR=$(cd "$SVN_DIR" && pwd)
EXPORT_DIR=$(mktemp -d)
trap 'rm -rf "$EXPORT_DIR"' EXIT

git -C "$PLUGIN_DIR" archive "$REF" | tar -x -C "$EXPORT_DIR"

if [ ! -f "$EXPORT_DIR/.distignore" ]; then
	echo "svn-sync: $REF has no .distignore — refusing to sync everything." >&2
	exit 1
fi

mkdir -p "$SVN_DIR/trunk"
rsync -a --delete --delete-excluded \
	--exclude-from="$EXPORT_DIR/.distignore" \
	"$EXPORT_DIR/" "$SVN_DIR/trunk/"

if [ -d "$EXPORT_DIR/.wordpress-org" ]; then
	mkdir -p "$SVN_DIR/assets"
	rsync -a --delete --exclude=.DS_Store \
		"$EXPORT_DIR/.wordpress-org/" "$SVN_DIR/assets/"
fi

echo "svn-sync: synced $REF ($(git -C "$PLUGIN_DIR" rev-parse --short "$REF")) to $SVN_DIR"
