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

# Broad architecture guard. Pre-existing, file-scoped compatibility/query
# owners are checked separately below instead of weakening the prohibition.
prohibited='Morgen_Core_|morgen_core_|morgen-ui6-|mg6-|CASE-PROD|PROD-[0-9]+|wp_ajax_nopriv_|alloptions|notoptions|technical-library|quality-testing'
if grep -RInE "$prohibited" "$plugin_root" --include='*.php' --include='*.js' --include='*.css'; then
	echo "prohibited runtime identifier or architecture pattern found" >&2
	exit 1
fi

contact_bootstrap_file="$plugin_root/includes/gloskin-site-core-contact-service-bootstrap-trait.php"
shop_catalog_file="$plugin_root/includes/class-gloskin-site-core-woocommerce-adapter-shop-catalog.php"
template_service_file="$plugin_root/includes/class-gloskin-site-core-template-service.php"
shop_discovery_route_file="$plugin_root/includes/gloskin-site-core-shop-discovery-route-trait.php"

# Direct $wpdb access remains prohibited except for the exact three SQL owners
# already present in the handoff baseline: Contact post-type compatibility,
# adapter-owned bounded Shop query SQL, and Template Service's existing query
# projection. A fourth owner anywhere else fails.
wpdb_files="$(grep -RIl '\$wpdb' "$plugin_root" --include='*.php' || true)"
unexpected_wpdb="$(echo "$wpdb_files" | grep -vF "$contact_bootstrap_file" | grep -vF "$shop_catalog_file" | grep -vF "$template_service_file" || true)"
[[ -z "$unexpected_wpdb" ]] || { echo "direct wpdb access outside documented baseline SQL owners: $unexpected_wpdb" >&2; exit 1; }
for allowed_wpdb_file in "$contact_bootstrap_file" "$shop_catalog_file" "$template_service_file"; do
	echo "$wpdb_files" | grep -qF "$allowed_wpdb_file" || { echo "documented baseline SQL owner missing: $allowed_wpdb_file" >&2; exit 1; }
done
grep -q 'function migrate_message_post_type' "$contact_bootstrap_file" || { echo "Contact direct-DB exception escaped migrate_message_post_type() owner" >&2; exit 1; }
grep -q 'function filter_shop_product_query_clauses' "$shop_catalog_file" || { echo "Shop SQL exception escaped adapter query owner" >&2; exit 1; }

kernel_count="$(grep -RIl 'final class Gloskin_Site_Core_Kernel' "$plugin_root" --include='*.php' | wc -l | tr -d ' ')"
[[ "$kernel_count" == "1" ]] || { echo "expected exactly one Kernel composition root, found $kernel_count" >&2; exit 1; }

# AssetService is the canonical owner. Two exact route/surface-specific hooks
# were already present in the handoff baseline: Contact reCAPTCHA and Shop
# Discovery assets. A fourth wp_enqueue_scripts owner anywhere else fails.
asset_service_file="$plugin_root/includes/class-gloskin-site-core-asset-service.php"
asset_owner_files="$(grep -RIl "add_action( 'wp_enqueue_scripts'" "$plugin_root" --include='*.php' || true)"
for allowed_asset_file in "$asset_service_file" "$contact_bootstrap_file" "$shop_discovery_route_file"; do
	echo "$asset_owner_files" | grep -qF "$allowed_asset_file" || { echo "documented frontend asset owner missing: $allowed_asset_file" >&2; exit 1; }
done
unexpected_asset_owners="$(echo "$asset_owner_files" | grep -vF "$asset_service_file" | grep -vF "$contact_bootstrap_file" | grep -vF "$shop_discovery_route_file" || true)"
[[ -z "$unexpected_asset_owners" ]] || { echo "unexpected frontend asset enqueue owner(s): $unexpected_asset_owners" >&2; exit 1; }
asset_owner_count="$(echo "$asset_owner_files" | grep -c . || true)"
[[ "$asset_owner_count" == "3" ]] || { echo "expected exactly 3 handoff frontend asset owners, found $asset_owner_count" >&2; exit 1; }
grep -q "add_action( 'wp_enqueue_scripts', array( \$this, 'enqueue_recaptcha' )" "$contact_bootstrap_file" || { echo "Contact asset exception escaped enqueue_recaptcha owner" >&2; exit 1; }
grep -q "add_action( 'wp_enqueue_scripts', array( \$this, 'enqueue_shop_assets' )" "$shop_discovery_route_file" || { echo "Shop Discovery asset exception escaped enqueue_shop_assets owner" >&2; exit 1; }

# The handoff baseline already contains exactly nine bootable service/adapter
# class files. Assert that exact count so a new service still fails without
# falsely rejecting the existing architecture.
service_count="$(find "$plugin_root/includes" -maxdepth 1 -type f \( -name 'class-gloskin-site-core-*-service.php' -o -name 'class-gloskin-site-core-*-adapter.php' \) | wc -l | tr -d ' ')"
[[ "$service_count" == "9" ]] || { echo "expected exactly 9 bootable service/adapter classes from handoff baseline, found $service_count" >&2; exit 1; }

# Woo availability must resolve through the canonical adapter plus the exact
# pre-existing lifecycle exception documented by the baseline contract.
woo_gate_files="$(grep -RIl "class_exists( 'WooCommerce'" "$plugin_root/includes" --include='*.php' || true)"
woo_gate_count="$(echo "$woo_gate_files" | grep -c . || true)"
adapter_file="$plugin_root/includes/class-gloskin-site-core-woocommerce-adapter.php"
lifecycle_file="$plugin_root/includes/class-gloskin-site-core-lifecycle-service.php"
echo "$woo_gate_files" | grep -qF "$adapter_file" || { echo "canonical WooCommerce_Adapter no longer resolves Woo availability" >&2; exit 1; }
unexpected_gates="$(echo "$woo_gate_files" | grep -vF "$adapter_file" | grep -vF "$lifecycle_file" || true)"
[[ -z "$unexpected_gates" ]] || { echo "Woo availability gate(s) outside the adapter and documented LifecycleService exception: $unexpected_gates" >&2; exit 1; }
[[ "$woo_gate_count" -le 2 ]] || { echo "expected at most 2 Woo availability gates (adapter + lifecycle exception), found $woo_gate_count: $woo_gate_files" >&2; exit 1; }

required_templates=(home about treatments promo treatment skincare skincare-category clinics clinic doctors doctor contact insights shop)
for template in "${required_templates[@]}"; do
	[[ -f "$plugin_root/templates/pages/$template.php" ]] || { echo "missing template: $template" >&2; exit 1; }
done

for script in "$plugin_root"/assets/js/*.js; do
	node --check "$script" >/dev/null
done

echo "architecture checks passed ($service_count bootable service classes)"
