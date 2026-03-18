#!/usr/bin/env bash
# Build script: minify JS and CSS for production
# Requirements: npm install -g terser clean-css-cli
# Usage: bash bin/build.sh

set -e
DIR="$(cd "$(dirname "$0")/.." && pwd)"
JS_DIR="$DIR/webroot/js"
CSS_DIR="$DIR/webroot/css"

echo "Building production assets..."

# JS: Minify pages.js → pages.min.js
if command -v terser &>/dev/null; then
    terser "$JS_DIR/pages.js" -c -m -o "$JS_DIR/pages.min.js"
    echo "  ✓ pages.min.js ($(wc -c < "$JS_DIR/pages.min.js") bytes)"
else
    echo "  ⚠ terser not found. Install: npm install -g terser"
    cp "$JS_DIR/pages.js" "$JS_DIR/pages.min.js"
fi

# CSS: Minify app.css → app.min.css
if command -v cleancss &>/dev/null; then
    cleancss -o "$CSS_DIR/app.min.css" "$CSS_DIR/app.css"
    echo "  ✓ app.min.css ($(wc -c < "$CSS_DIR/app.min.css") bytes)"
else
    echo "  ⚠ clean-css-cli not found. Install: npm install -g clean-css-cli"
    cp "$CSS_DIR/app.css" "$CSS_DIR/app.min.css"
fi

echo "Done. Set debug=false in config/app.php to serve minified assets."
