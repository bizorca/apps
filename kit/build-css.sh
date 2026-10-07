#!/usr/bin/env bash
# Regenerate the vendored Tailwind stylesheet.
#
# Run after adding or changing Tailwind utility classes anywhere under
# public/, includes/, or templates/. The output (public/assets/tailwind.css)
# is a committed build artifact, so the server never needs Node.
#
#   ./build-css.sh
set -euo pipefail
cd "$(dirname "$0")"
npx -y tailwindcss@3.4.17 \
  -c tailwind.config.js \
  -i src/tailwind.css \
  -o public/assets/tailwind.css \
  --minify
echo "wrote public/assets/tailwind.css ($(wc -c < public/assets/tailwind.css | tr -d ' ') bytes)"
