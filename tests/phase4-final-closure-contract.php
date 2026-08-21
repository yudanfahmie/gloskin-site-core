<?php
declare(strict_types=1);
$root = dirname( __DIR__ );
$plugin = $root . '/plugin/gloskin-site-core';
function p4fail( string $m ): void { fwrite( STDERR, "phase4-final-closure-contract.php: FAIL: {$m}\n" ); exit( 1 ); }
function p4must( bool $ok, string $m ): void { if ( ! $ok ) { p4fail( $m ); } }
function p4text( string $p ): string { $v = @file_get_contents( $p ); if ( false === $v ) { p4fail( 'cannot read ' . $p ); } return $v; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/tmp/wordpress/' ); }
if ( ! class_exists( 'Gloskin_Site_Core_Content_Service' ) ) {
	class Gloskin_Site_Core_Content_Service {
		public const ADMIN_MENU_SLUG='gloskin-content', PROMO_POST_TYPE='gloskin_promo', ACHIEVEMENT_POST_TYPE='gloskin_achievement';
		public const FAMILY_TAXONOMY='gloskin_product_family', CONCERN_TAXONOMY='gloskin_concern', FAMILY_SKINCARE='skincare', FAMILY_TREATMENT='treatment';
	}
}
$fpath = $plugin . '/includes/class-gloskin-site-core-phase4-finalizer-admin.php';
require_once $fpath;
$f=p4text($fpath); $k=p4text($plugin.'/includes/class-gloskin-site-core-kernel.php'); $b=p4text($plugin.'/gloskin-site-core.php');
$t=p4text($plugin.'/includes/class-gloskin-site-core-translation.php'); $h=p4text($plugin.'/templates/pages/home.php');
$p=p4text($plugin.'/templates/pages/promo.php'); $a=p4text($plugin.'/templates/pages/about.php'); $s=p4text($plugin.'/templates/parts/phase4-home-selection.php');
$skin=Gloskin_Site_Core_Phase4_Finalizer_Admin::skincare_category_map(); $treat=Gloskin_Site_Core_Phase4_Finalizer_Admin::treatment_product_slugs();
p4must(count($skin)===25 && count($treat)===48 && count(array_unique(array_merge(array_keys($skin),$treat)))===73,'canonical 25+48 scope');
p4must(count(array_filter($skin))===25 && strpos($f,"'perawatan'")!==false && strpos($f,"'uncategorized'")!==false,'native Woo category contract');
foreach(['post_excerpt','post_content','_gloskin_phase4_content_source','_gloskin_phase4_content_version','should_write_product_field'] as $n){p4must(strpos($f,$n)!==false,'content owner '.$n);}
foreach(["25 !== absint( \$content['skincare_short']","25 !== absint( \$content['skincare_full']","48 !== absint( \$content['treatment_short']","48 !== absint( \$content['treatment_full']"] as $n){p4must(strpos($f,$n)!==false,'content verifier '.$n);}
foreach(['_gloskin_sample_source_id','_gloskin_sample_data','_gloskin_sample_bundle_id','_gloskin_demo_identity','FAMILY_TAXONOMY','wp_trash_post'] as $n){p4must(strpos($f,$n)!==false,'legacy evidence '.$n);}
foreach(['wp_delete_post','wp_delete_attachment','wp_delete_file','levenshtein','similar_text'] as $n){p4must(strpos($f,$n)===false,'forbidden '.$n);}
foreach(['unrelated_woo_mutations','hard_deleted_posts','media_deletions'] as $n){p4must((bool)preg_match("/'".preg_quote($n,'/')."'\\s*=>\\s*0/",$f),'zero invariant '.$n);}
p4must(strpos($f,"'manage_options'")!==false && strpos($f,'check_admin_referer')!==false,'operator gate');
p4must(strpos($f,"'status' => 'already_complete', 'mutations' => 0")!==false,'second-run no-op');
$last=-1; foreach(['resolve_canonical_products()','reconcile_product_content( $canonical )','apply_woo_categories( $canonical )','prepare_promos()','prepare_piagam()','trash_explicit_legacy_products( $canonical_ids )'] as $n){$x=strpos($f,$n);p4must($x!==false&&$x>$last,'resolver order '.$n);$last=$x;}
$x=strpos($f,"['status']",$last+1); while($x!==false&&strpos(substr($f,$x,120),"'complete'")===false){$x=strpos($f,"['status']",$x+1);} p4must($x!==false,'complete only after final verify');
$art=$plugin.'/assets/images/phase4'; foreach(['promo-01.png','promo-02.png','promo-03.png','piagam-01.png','piagam-02.png','piagam-03.png','piagam-04.png'] as $n){$q=$art.'/'.$n;$z=@getimagesize($q);p4must(is_file($q)&&filesize($q)>1000&&is_array($z)&&$z[2]===IMAGETYPE_PNG,'artwork '.$n);}
p4must(substr_count($f,"'asset' => 'promo-")===3 && substr_count($f,"'asset' => 'piagam-")===4,'3+4 replacements');
p4must(strpos($f,'set_post_thumbnail')!==false && strpos($f,'attachment_is_usable_image')!==false,'image binding');
p4must(strpos($h,'data-gloskin-phase4-home-hero')!==false && strpos($h,'0, 6')!==false && strpos($s,'0, 3')!==false && strpos($s,'6 - count')!==false,'Home treatment/video contract');
p4must(strpos($h,'0, 3')!==false && strpos($h,'0, 4')!==false && strpos($h,'home-closing')===false && strpos($h,'render_closing_cta')===false,'Home 3 Testimoni + 4 Piagam / no CTA');
p4must(substr_count($p,'$gloskin_phase4_render_promo_carousel(')===2 && strpos($p,'Promo Terbatas')!==false && strpos($p,'Promo Poster')!==false,'Promo two carousels');
foreach(['promo-content','promo-closing','data-gloskin-promo-thumb'] as $n){p4must(strpos($p,$n)===false,'obsolete Promo '.$n);}
foreach(['about-header','about-story','about-founder','about-principles','Tentang Kami','Tentang GLOSKIN','Visi · Misi · Nilai'] as $n){p4must(strpos($a,$n)!==false,'About '.$n);}
foreach(['about-doctors','about-clinics','render_achievements','about-closing','render_closing_cta'] as $n){p4must(strpos($a,$n)===false,'obsolete About '.$n);}
p4must(strpos($k,'Demo_Content_Reset')===false && !file_exists($plugin.'/resources/phase3'),'retired demo/resources runtime');
p4must(strpos($k,'class-gloskin-site-core-phase4-finalizer-admin.php')!==false && strpos($k,'Gloskin_Site_Core_Phase4_Finalizer_Admin')!==false,'Kernel finalizer wiring');
p4must(strpos($k,"const VERSION = '0.7.183'")!==false && strpos($b,'Version: 0.7.183')!==false,'version 0.7.183 sync');
foreach(["'product' => array( 'label' => 'Product', 'fields' => \$base",'Promo Poster','Kenapa Memilih GLOSKIN','Testimoni','Piagam','Tentang GLOSKIN','Visi · Misi · Nilai'] as $n){p4must(strpos($t,$n)!==false,'Phase5 translation '.$n);}
echo "phase4-final-closure-contract.php: OK (73 canonical, 25+48 copy/category, 3+4 image-ready, Trash-only, Home/Promo/About, Phase-5 preserved)\n";
