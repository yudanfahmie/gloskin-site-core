<?php
declare(strict_types=1);

$root = dirname(__DIR__) . '/plugin/gloskin-site-core';
$svg = (string) file_get_contents($root . '/assets/images/gloskin-arrow.svg');
$css = (string) file_get_contents($root . '/assets/css/gloskin-ui1-icons.css');
$assets = (string) file_get_contents($root . '/config/assets.php');
$composition = (string) file_get_contents($root . '/templates/parts/composition-helpers.php');
$promo = (string) file_get_contents($root . '/templates/pages/promo.php');
$home = (string) file_get_contents($root . '/templates/pages/home.php');
$insights = (string) file_get_contents($root . '/templates/pages/insights.php');

function arrow_must(bool $ok, string $message): void {
    if (!$ok) {
        fwrite(STDERR, "shared-arrow-icon-contract.php: FAIL: {$message}\n");
        exit(1);
    }
}

arrow_must(false !== strpos($svg, 'viewBox="0 0 24 24"'), 'canonical 24x24 SVG viewBox remains');
arrow_must(false !== strpos($svg, 'M13 18.75C12.9015'), 'provided arrow head path remains canonical');
arrow_must(false !== strpos($svg, 'M19 12.75H5C4.80109'), 'provided arrow shaft path remains canonical');
arrow_must(false === stripos($svg, '<script'), 'SVG contains no script payload');
arrow_must(false === strpos($svg, 'width="800px"') && false === strpos($svg, 'height="800px"'), 'oversized intrinsic dimensions removed');

arrow_must(false !== strpos($css, "mask:url('../images/gloskin-arrow.svg')"), 'shared CSS projects canonical SVG resource');
arrow_must(false !== strpos($css, 'background:currentColor'), 'arrow inherits existing semantic colors');
arrow_must(false !== strpos($css, '.gloskin-ui1-arrow-icon--prev{transform:rotate(180deg)}'), 'previous direction mirrors one canonical resource');
arrow_must(false !== strpos($css, '.gloskin-ui1 .gloskin-ui1-category-card .gloskin-ui1-category-card__arrow{display:flex;align-self:stretch;align-items:center;justify-content:center;font-size:0;line-height:0}'), 'legacy category glyph is suppressed and the icon stays vertically centered');
arrow_must(false !== strpos($css, '.gloskin-ui1 .gloskin-ui1-category-card .gloskin-ui1-category-card__arrow::before'), 'category cards project exactly one canonical mask');
arrow_must(false !== strpos($css, '.gloskin-ui1 .gloskin-ui1-card__body .gloskin-ui1-text-link>span[aria-hidden="true"]'), 'legacy shared-card raw glyph is visually retired');

arrow_must(false !== strpos($assets, "'gloskin-ui1-icons'"), 'semantic icon stylesheet is registered');
arrow_must(false !== strpos($assets, "'deps'  => array( 'gloskin-ui1-icons' )"), 'core depends on shared icon owner');
arrow_must(1 === substr_count($composition, 'function gloskin_ui1_arrow_icon('), 'one shared arrow markup helper exists');
arrow_must(false === strpos($composition, '> →<'), 'pathway no longer emits raw arrow glyph');
arrow_must(false === strpos($promo, '&larr;') && false === strpos($promo, '&rarr;'), 'Promo no longer emits raw arrow entities');
arrow_must(false !== strpos($promo, "gloskin_ui1_arrow_icon( 'prev' )") && false !== strpos($promo, 'gloskin_ui1_arrow_icon()'), 'Promo controls use shared resource');
arrow_must(false === strpos($home, 'm11 4-5 5 5 5') && false === strpos($home, 'm7 4 5 5-5 5'), 'Home testimonial forked chevrons retired');
arrow_must(false !== strpos($home, "gloskin_ui1_arrow_icon( 'prev' )") && false !== strpos($home, 'gloskin_ui1_arrow_icon()'), 'Home testimonial controls use shared resource');
arrow_must(false !== strpos($insights, "'prev_text'") && false !== strpos($insights, "'next_text'") && false !== strpos($insights, 'gloskin_ui1_arrow_icon'), 'Insights pagination explicitly uses shared resource');

echo "shared-arrow-icon-contract.php: OK (one SVG resource + currentColor mask + one centered category arrow + card/pathway/promo/home/pagination convergence)\n";
