<?php
declare(strict_types=1);
/** Static contract: doctor photo batch processing and cursor-based resume for v0.7.153. */
$root=dirname(__DIR__);$passed=0;$failed=0;
function ok_batch(bool $cond,string $msg):void{global $passed,$failed;if($cond){echo "ok: {$msg}\n";$passed++;}else{echo "FAIL: {$msg}\n";$failed++;}}
$migration=file_get_contents($root.'/plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260819-final-migration.php');$kernel=file_get_contents($root.'/plugin/gloskin-site-core/includes/class-gloskin-site-core-kernel.php');$plugin_h=file_get_contents($root.'/plugin/gloskin-site-core/gloskin-site-core.php');if(false===$migration||false===$kernel||false===$plugin_h){fwrite(STDERR,"Cannot read final migration release sources\n");exit(1);}
ok_batch((bool)preg_match('/const BATCH_SIZE\s*=\s*3\s*;/',$migration),'BATCH_SIZE constant must equal 3');
ok_batch(str_contains($migration,"'doctor_cursor'"),"State defaults must include 'doctor_cursor' key");
ok_batch(str_contains($migration,'function run_doctor_photos_batch'),'run_doctor_photos_batch() method must be defined');
ok_batch(str_contains($migration,"'doctor_cursor'")&&(bool)preg_match("/run_doctor_photos_batch.*?doctor_cursor/s",$migration),"run_doctor_photos_batch() must read doctor_cursor from \$state");
ok_batch(str_contains($migration,"'applied'")&&str_contains($migration,"'reused'"),"run_doctor_photos_batch() must merge 'applied' and 'reused' from existing audit");
ok_batch(str_contains($migration,"'complete'"),"run_doctor_photos_batch() must return 'complete' boolean");
ok_batch(str_contains($migration,"'cursor'")&&str_contains($migration,"'total'"),"run_doctor_photos_batch() must return 'cursor' and 'total' for progress display");
ok_batch((bool)preg_match("/case 'doctor_photos'.*?run_doctor_photos_batch/s",$migration),"advance() case 'doctor_photos' must call run_doctor_photos_batch()");
ok_batch(str_contains($migration,'$step_complete         = false')||str_contains($migration,'$step_complete = false'),"\$step_complete must be set to false when batch is not complete");
ok_batch(str_contains($migration,'if ( $step_complete )')&&(bool)preg_match("/if \( \\\$step_complete \).*?next_step_index/s",$migration),'next_step_index increment must be gated on $step_complete');
ok_batch(str_contains($migration,"'doctor_cursor']       = 0")||str_contains($migration,"'doctor_cursor'] = 0"),"preflight case must reset doctor_cursor to 0");
ok_batch(str_contains($migration,"'doctor_audit']        = array()")||str_contains($migration,"'doctor_audit'] = array()"),"preflight case must reset doctor_audit to empty array");
ok_batch(str_contains($migration,'upload_unavailable:'),"run_doctor_photos_batch() must throw 'upload_unavailable:' error when uploads unavailable");
$has_headroom=(bool)preg_match('/\$limit\s*=\s*count\(\s*\$this->steps\(\)\s*\)\s*\+\s*(\d+)/',$migration,$m)&&(int)$m[1]>3;ok_batch($has_headroom,'run_to_completion() loop limit must have batch headroom (addend > 3)');
ok_batch((bool)preg_match("/const VERSION\s*=\s*'0\.7\.153'/",$kernel),'Kernel VERSION must be 0.7.153');ok_batch((bool)preg_match('/^ \* Version: 0\.7\.153$/m',$plugin_h),'Plugin header Version must be 0.7.153');
echo "\nfinal-migration-batch-contract.php: {$passed} passed, {$failed} failed\n";exit($failed>0?1:0);
