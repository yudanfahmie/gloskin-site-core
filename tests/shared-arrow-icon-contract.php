<?php
declare(strict_types=1);

$root        = dirname(__DIR__) . '/plugin/gloskin-site-core';
$icon        = (string) file_get_contents($root . '/templates/parts/icon-helpers.php');
$css         = (string) file_get_contents($root . '/assets/css/gloskin-ui1-icons.css');
$assets      = (string) file_get_contents($root . '/config/assets.php');
$template    = (string) file_get_contents($root . '/templates/parts/template-helpers.php');
$composition = (string) file_get_contents($root . '/templates/parts/composition-helpers.php');
$shop        = (string) file_get_contents($root . '/templates/parts/shop-results.php');
$promo       = (string) file_get_contents($root . '/templates/pages/promo.php');
$home        = (string) file_get_contents($root . '/templates/pages/home.php');
$insights    = (string) file_get_contents($root . '/templates/pages/insights.php');

function arrow_must(bool $ok, string $message): void {
	if (!$ok) {
		fwrite(STDERR, "shared-arrow-icon-contract.php: FAIL: {$message}\n");
		exit(1);
	}
}

arrow_must(1 === substr_count($icon, 'function gloskin_ui1_arrow_icon('), 'one canonical arrow PHP owner exists');
arrow_must(false !== strpos($icon, 'viewBox=\"0 0 24 24\"'), 'canonical 24x24 viewBox remains');
arrow_must(false !== strpos($icon, 'M13 18.75C12.9015'), 'approved arrow head geometry remains');
arrow_must(false !== strpos($icon, 'M19 12.75H5C4.80109'), 'approved arrow shaft geometry remains');
arrow_must(2 === substr_count($icon, 'fill=\"currentColor\"'), 'both canonical paths inherit semantic color');
arrow_must(false === stripos($icon, '<script'), 'inline icon owner contains no script payload');
arrow_must(false === is_file($root . '/assets/images/gloskin-arrow.svg'), 'duplicate standalone arrow asset is retired');

arrow_must(false === strpos($css, 'mask:'), 'icon CSS does not project SVG masks');
arrow_must(false === strpos($css, '-webkit-mask'), 'icon CSS does not project WebKit masks');
arrow_must(false === strpos($css, '::before') && false === strpos($css, '::after'), 'icon CSS has no pseudo-element arrow synthesis');
arrow_must(false === strpos($css, 'font-size:0'), 'icon CSS does not hide legacy glyphs');
arrow_must(false !== strpos($css, '.gloskin-ui1-category-card__arrow .gloskin-ui1-arrow-icon'), 'category card sizes the real SVG child');
arrow_must(false !== strpos($css, '.gloskin-ui1-arrow-icon--prev{transform:rotate(180deg)}'), 'previous direction mirrors the same geometry');

arrow_must(false !== strpos($assets, "'gloskin-ui1-icons'"), 'semantic icon stylesheet remains registered as a primitive owner');
arrow_must(false !== strpos($template, "require_once __DIR__ . '/icon-helpers.php';"), 'template helpers load the canonical icon owner directly');
arrow_must(false !== strpos($composition, "require_once __DIR__ . '/icon-helpers.php';"), 'composition helpers load the canonical icon owner directly');
arrow_must(false === strpos($composition, 'function gloskin_ui1_arrow_icon('), 'composition no longer forks the arrow owner');

foreach (array($template, $composition, $shop, $promo, $home, $insights) as $surface) {
	arrow_must(false === strpos($surface, '&rarr;') && false === strpos($surface, '&larr;'), 'managed arrow surfaces contain no raw arrow entities');
}
arrow_must(false === strpos($template, '>→<') && false === strpos($template, '> →<'), 'template helpers contain no raw directional glyph markup');
arrow_must(false === strpos($shop, '>→<') && false === strpos($shop, '>←<'), 'Shop pagination contains no raw directional glyph markup');
arrow_must(false !== strpos($template, 'gloskin-ui1-category-card__arrow') && false !== strpos($template, 'gloskin_ui1_arrow_icon()'), 'category card emits the canonical SVG directly');
arrow_must(false !== strpos($template, "gloskin_ui1_arrow_icon( 'prev' )") && substr_count($template, 'gloskin_ui1_arrow_icon()') >= 3, 'shared template controls converge on canonical SVG');
arrow_must(false !== strpos($shop, "gloskin_ui1_arrow_icon( 'prev' )") && false !== strpos($shop, 'gloskin_ui1_arrow_icon()'), 'Shop SSR/AJAX pagination emits canonical SVG directly');
arrow_must(false !== strpos($promo, "gloskin_ui1_arrow_icon( 'prev' )") && false !== strpos($promo, 'gloskin_ui1_arrow_icon()'), 'Promo controls use shared SVG');
arrow_must(false !== strpos($home, "gloskin_ui1_arrow_icon( 'prev' )") && false !== strpos($home, 'gloskin_ui1_arrow_icon()'), 'Home testimonial controls use shared SVG');
arrow_must(false !== strpos($insights, "'prev_text'") && false !== strpos($insights, "'next_text'") && false !== strpos($insights, 'gloskin_ui1_arrow_icon'), 'Insights pagination explicitly uses shared SVG');

echo "shared-arrow-icon-contract.php: OK (one inline SVG owner + no masks/pseudo overlays + source-markup convergence across category/card/shop/promo/home/insights)\n";
