<?php
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
$GLOBALS['opts']=[];
class WP_Error { private $m; public function __construct($c,$m){$this->m=$m;} public function get_error_message(){return $this->m;} }
function is_wp_error($v){return $v instanceof WP_Error;} function trailingslashit($s){return rtrim((string)$s,'/\\').'/';} function plugin_dir_path($f){return trailingslashit(dirname($f));} function sanitize_key($s){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$s));}
function get_option($k,$d=[]){return array_key_exists($k,$GLOBALS['opts'])?$GLOBALS['opts'][$k]:$d;} function update_option($k,$v,$a=false){$GLOBALS['opts'][$k]=$v;return true;} function add_option($k,$v,$x='',$a=false){if(array_key_exists($k,$GLOBALS['opts']))return false;$GLOBALS['opts'][$k]=$v;return true;} function delete_option($k){unset($GLOBALS['opts'][$k]);return true;} function wp_generate_uuid4(){return '00000000-0000-4000-8000-000000000001';}
require dirname(__DIR__).'/plugin/gloskin-site-core/includes/class-gloskin-site-core-sample-product-importer.php';
$i=new Gloskin_Site_Core_Sample_Product_Importer(dirname(__DIR__).'/plugin/gloskin-site-core/gloskin-site-core.php');
try{$i->advance('start');fwrite(STDERR,"expected Woo missing failure\n");exit(1);}catch(RuntimeException $e){if(strpos($e->getMessage(),'WooCommerce')===false){throw $e;}}
$s=$i->get_state(); if($s['status']!=='failed'||isset($GLOBALS['opts'][Gloskin_Site_Core_Sample_Product_Importer::LOCK_OPTION])){fwrite(STDERR,"Woo missing state/lock contract failed\n");exit(1);} echo "sample-product Woo-missing test passed\n";
