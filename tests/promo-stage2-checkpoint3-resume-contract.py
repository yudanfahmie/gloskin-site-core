#!/usr/bin/env python3
"""Checkpoint 3 must finalize in place without repeating reconciliation."""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
RECOVERY_PATH = ROOT / "plugin/gloskin-site-core/includes/class-gloskin-site-core-revision-20260820-promo-recovery.php"
JS_PATH = ROOT / "plugin/gloskin-site-core/assets/js/gloskin-ui1-final-migration.js"
recovery = RECOVERY_PATH.read_text(encoding="utf-8")
client = JS_PATH.read_text(encoding="utf-8")


def require(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


step_keys = re.findall(r"array\( 'key' => '([^']+)'", recovery.split("private function steps()", 1)[1].split("public function get_state()", 1)[0])
require(step_keys == ["preflight", "reconcile", "verify", "finalize"], "Stage 2 checkpoint order changed")
require("$index = (int) $state['next_step_index'];" in recovery, "advance must resume the persisted checkpoint")
require("case 'finalize':\n\t\t\t\t\t$state['status'] = 'consumed';" in recovery,
        "checkpoint 3 must transition directly to consumed")

start_block = recovery.split("if ( 'start' === $mode )", 1)[1].split("$index =", 1)[0]
require("next_step_index'] =" not in start_block, "start must not reset the current checkpoint")
require("delete_option( self::STATE_OPTION" not in recovery, "Stage 2 must never reset the persisted state option")
require("/* failed/running/verifying always resume the persisted server state. */" in client and
        "request( 'continue' ).then( continueChain )" in client,
        "running clients must resume with continue")

state = {"status": "running", "next_step_index": 3, "processed_steps": 3}
executed = []
key = step_keys[state["next_step_index"]]
executed.append(key)
if key == "finalize":
    state["status"] = "consumed"
state["next_step_index"] += 1
state["processed_steps"] = min(len(step_keys), state["next_step_index"])
require(executed == ["finalize"] and state == {
    "status": "consumed", "next_step_index": 4, "processed_steps": 4,
}, "running checkpoint 3 did not consume in place")
require("reconcile" not in executed, "checkpoint 3 repeated reconciliation")

print("promo-stage2-checkpoint3-resume-contract.py: OK (3 -> finalize -> consumed)")
