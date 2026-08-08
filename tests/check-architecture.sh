#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin_root="$repo_root/plugin/gloskin-site-core"

if [[ ! -d "$plugin_root" ]]; then
	echo "missing production plugin directory: $plugin_root" >&2
	exit 1
fi

while IFS= read -r -d '' file; do
	php -l "$file" >/dev/null
done < <(find "$plugin_root" -type f -name '*.php' -print0)

prohibited='Morgen_Core_|morgen_core_|morgen-ui6-|mg6-|CASE-PROD|PROD-[0-9]+|wp_ajax_nopriv_|alloptions|notoptions|\$wpdb|technical-library|quality-testing'
if grep -RInE "$prohibited" "$plugin_root" --include='*.php' --include='*.js' --include='*.css'; then
	echo "prohibited runtime identifier or architecture pattern found" >&2
	exit 1
fi

kernel_count="$(grep -RIl 'final class Gloskin_Site_Core_Kernel' "$plugin_root" --include='*.php' | wc -l | tr -d ' ')"
if [[ "$kernel_count" != "1" ]]; then
	echo "expected exactly one Kernel composition root, found $kernel_count" >&2
	exit 1
fi

asset_owner_count="$(grep -RIl "add_action( 'wp_enqueue_scripts'" "$plugin_root" --include='*.php' | wc -l | tr -d ' ')"
if [[ "$asset_owner_count" != "1" ]]; then
	echo "expected exactly one first-party frontend asset enqueue owner, found $asset_owner_count" >&2
	exit 1
fi

service_count="$(find "$plugin_root/includes" -maxdepth 1 -type f \( -name 'class-gloskin-site-core-*-service.php' -o -name 'class-gloskin-site-core-*-adapter.php' \) | wc -l | tr -d ' ')"
if (( service_count > 8 )); then
	echo "first-party bootable service budget exceeded: $service_count" >&2
	exit 1
fi

echo "architecture checks passed ($service_count bootable service classes)"
