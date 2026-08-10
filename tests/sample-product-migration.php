<?php
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
class WP_Error { private $m; public function __construct($c,$m){$this->m=$m;} public function get_error_message(){return $this->m;} }
function is_wp_error($v){return $v instanceof WP_Error;}
function trailingslashit($s){return rtrim((string)$s,'/\\').'/';}
function plugin_dir_path($f){return trailingslashit(dirname($f));}
function wp_strip_all_tags($s){return strip_tags((string)$s);}
function sanitize_title($s){$s=strtolower(trim((string)$s));$s=preg_replace('/[^a-z0-9]+/','-',$s);return trim((string)$s,'-');}
function sanitize_key($s){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$s));}
function wp_parse_url($url,$component=-1){return parse_url((string)$url,$component);}
function wp_delete_file($file){@unlink((string)$file);}
function ok($x,$m){if(!$x){fwrite(STDERR,"FAIL: $m\n");exit(1);}}
require dirname(__DIR__).'/plugin/gloskin-site-core/includes/class-gloskin-site-core-sample-product-bundle.php';
$src=dirname(__DIR__).'/plugin/gloskin-site-core/migration-runtime/gloskin-sample-products-v1';
$tmp=sys_get_temp_dir().'/gloskin-sample-test-'.bin2hex(random_bytes(4));
$runtime=$tmp.'/migration-runtime/gloskin-sample-products-v1'; mkdir($runtime,0777,true);
foreach(['manifest.json','products.json','media.json'] as $f){copy($src.'/'.$f,$runtime.'/'.$f);} file_put_contents($tmp.'/plugin.php','<?php');
$b=new Gloskin_Site_Core_Sample_Product_Bundle($tmp.'/plugin.php'); $v=$b->validate();
ok(count($v['products'])===13,'13 products'); ok(count($v['media'])===58,'58 media'); ok(count(array_filter($v['products'],fn($p)=>$p['type']==='simple'))===8,'8 simple'); ok(count(array_filter($v['products'],fn($p)=>$p['type']==='variable'))===5,'5 variable');
$vars=0; foreach($v['products'] as $p){$vars+=count($p['variations']); ok(count($v['media_by_product'][$p['source_id']])===$p['media_count'],'media checkpoint'); ok($p['status']==='draft','parent status is draft'); foreach($p['variations'] as $variation){ok($variation['status']==='publish','variation status is publish');}} ok($vars===10,'10 variations');
$header=$b->read_header(); ok(!is_wp_error($header)&&$header['expected_media']===58,'cheap header');
file_put_contents($runtime.'/unexpected.txt','x'); try{$b->validate();ok(false,'unexpected file rejected');}catch(RuntimeException $e){ok(strpos($e->getMessage(),'tidak dideklarasikan')!==false,'unexpected file message');} unlink($runtime.'/unexpected.txt');
$m=json_decode(file_get_contents($runtime.'/manifest.json'),true); $m['checksums']['media.json']=str_repeat('0',64); file_put_contents($runtime.'/manifest.json',json_encode($m)); try{$b->validate();ok(false,'checksum rejected');}catch(RuntimeException $e){ok(strpos($e->getMessage(),'Checksum')!==false,'checksum message');}
copy($src.'/manifest.json',$runtime.'/manifest.json'); $v=$b->validate(); $c=$b->cleanup($v['manifest']); ok($c['ok']===true&&!is_dir($runtime),'cleanup declared runtime only'); @unlink($tmp.'/plugin.php'); @rmdir($tmp.'/migration-runtime'); @rmdir($tmp);
echo "sample-product migration bundle runtime: OK\n";
