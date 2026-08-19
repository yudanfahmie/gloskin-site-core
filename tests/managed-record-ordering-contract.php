<?php
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
$GLOBALS['gh_order_meta'] = array();
function get_post_meta($id,$key,$single=true){ return $GLOBALS['gh_order_meta'][$id][$key] ?? ''; }
require dirname(__DIR__) . '/plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php';
$service_ref = new ReflectionClass(Gloskin_Site_Core_Template_Service::class);
$service = $service_ref->newInstanceWithoutConstructor();
$method = new ReflectionMethod($service, 'compare_managed_posts'); $method->setAccessible(true);
$posts = array();
for($i=1;$i<=55;$i++){ $o=new stdClass(); $o->ID=$i; $o->post_title=sprintf('Record %02d',$i); $posts[]=$o; $GLOBALS['gh_order_meta'][$i]['gl_order']=0; }
$GLOBALS['gh_order_meta'][55]['gl_order']=1;
usort($posts, static function($a,$b) use($method,$service){ return $method->invoke($service,$a,$b,'gl_order'); });
if((int)$posts[0]->ID!==55){ fwrite(STDERR,"FAIL: >40 high-priority record was not sorted first\n"); exit(1); }
$a=new stdClass(); $a->ID=7; $a->post_title='Same'; $b=new stdClass(); $b->ID=9; $b->post_title='Same';
$GLOBALS['gh_order_meta'][7]['gl_order']=2; $GLOBALS['gh_order_meta'][9]['gl_order']=2;
if($method->invoke($service,$a,$b,'gl_order')>=0){ fwrite(STDERR,"FAIL: ID tiebreaker is not deterministic\n"); exit(1); }
$source=(string)file_get_contents(dirname(__DIR__).'/plugin/gloskin-site-core/includes/class-gloskin-site-core-template-service.php');
if(str_contains($source,'max( $limit * 4, 40 )') || substr_count($source,"'posts_per_page' => -1")<2){ fwrite(STDERR,"FAIL: managed queries still truncate before ordering\n"); exit(1); }
echo "managed-record-ordering-contract.php: OK (>40 deterministic)\n";
