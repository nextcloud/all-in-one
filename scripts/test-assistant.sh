#!/usr/bin/env bash
# Verify Nextcloud Assistant integration is healthy.
#
# Checks: config values, network reachability, llama-server API (models + inference),
# taskprocessing worker health, richdocuments language patch, and optionally an
# end-to-end chat task round-trip.
#
# Usage:
#   ./test-assistant.sh
#   ./test-assistant.sh --e2e-user admin --e2e-pass <password>
#   NC_URL=https://workspace.coreconduit.com ./test-assistant.sh --e2e-user admin --e2e-pass <pass>

set -uo pipefail

CONTAINER="nextcloud-aio-nextcloud"
LLAMA_GW="http://172.19.0.1:11435"
EXPECTED_URL="${LLAMA_GW}/v1"
EXPECTED_MODEL="Mistral-Small-3.2-24B-Instruct-2506-Q4_K_M"
DOC_SVC="/var/www/html/custom_apps/richdocuments/lib/Service/DocumentGenerationService.php"
NC_URL="${NC_URL:-https://workspace.coreconduit.com}"

# Worker: designed to run for --timeout=300 then exit cleanly (ExecMainStatus=0).
# Systemd restarts it every ~310 seconds. Max expected hourly rate = 3600/310 ≈ 12/h.
WORKER_MAX_RATE=15  # restarts/hour; above this indicates crash-looping

E2E_USER=""
E2E_PASS=""
PASS=0
FAIL=0
WARN=0

# ── arg parsing ───────────────────────────────────────────────────────────────
while [[ $# -gt 0 ]]; do
  case $1 in
    --e2e-user) E2E_USER="$2"; shift 2 ;;
    --e2e-pass) E2E_PASS="$2"; shift 2 ;;
    --nc-url)   NC_URL="$2"; shift 2 ;;
    *) echo "Unknown option: $1"; exit 1 ;;
  esac
done

# ── helpers ───────────────────────────────────────────────────────────────────
pass() { echo "  PASS  $1"; PASS=$((PASS + 1)); }
fail() { echo "  FAIL  $1"; FAIL=$((FAIL + 1)); }
warn() { echo "  WARN  $1"; WARN=$((WARN + 1)); }
section() { printf '\n── %s ' "$1"; printf '%.0s─' $(seq 1 $((50 - ${#1}))); echo; }
occ()  { sudo docker exec --user www-data "$CONTAINER" php occ "$@" 2>/dev/null; }
nc_curl() { sudo docker exec "$CONTAINER" bash -c "curl -sf $*" 2>/dev/null; }

# ── 1. Configuration ──────────────────────────────────────────────────────────
section "Configuration"

val=$(occ config:app:get integration_openai url)
if [[ "$val" == "$EXPECTED_URL" ]]; then
  pass "integration_openai URL: $val"
else
  fail "integration_openai URL — expected '$EXPECTED_URL', got '${val:-<unset>}'"
fi

val=$(occ config:app:get integration_openai default_completion_model_id)
if [[ "$val" == "$EXPECTED_MODEL" ]]; then
  pass "default_completion_model_id: $val"
else
  fail "default_completion_model_id — expected '$EXPECTED_MODEL', got '${val:-<unset>}'"
fi

val=$(occ config:app:get assistant chat_user_instructions)
if [[ "$val" == *"You are Nextcloud Assistant"* ]]; then
  pass "chat_user_instructions: custom prompt set"
else
  fail "chat_user_instructions — expected custom prompt, got '${val:-<unset>}'"
fi

enabled=$(occ app:list 2>/dev/null)
for app in assistant integration_openai richdocuments; do
  if echo "$enabled" | grep -q "$app"; then
    pass "app enabled: $app"
  else
    fail "app not enabled: $app"
  fi
done

# ── 2. Network ────────────────────────────────────────────────────────────────
section "Network (NC container → llama-server)"

if nc_curl "--max-time 5 ${LLAMA_GW}/health > /dev/null"; then
  pass "llama-server reachable from NC container (${LLAMA_GW})"
else
  fail "llama-server NOT reachable — check ufw rule: allow from 172.16.0.0/12 to any port 11435"
fi

# ── 3. llama-server API ───────────────────────────────────────────────────────
section "llama-server API"

models_json=$(nc_curl "--max-time 10 ${LLAMA_GW}/v1/models")
if [[ -z "$models_json" ]]; then
  fail "GET /v1/models: no response"
else
  if echo "$models_json" | grep -q "$EXPECTED_MODEL"; then
    pass "GET /v1/models: '$EXPECTED_MODEL' listed"
  else
    available=$(echo "$models_json" | grep -o '"id":"[^"]*"' | sed 's/"id":"//;s/"//' | head -3 | tr '\n' ' ')
    warn "GET /v1/models: expected model not found — loaded models: ${available:-none}"
  fi
fi

# Quick inference — 5-token budget keeps latency low
infer_payload='{"model":"'"$EXPECTED_MODEL"'","messages":[{"role":"user","content":"Reply with only the word: PONG"}],"max_tokens":5,"temperature":0}'
infer_json=$(nc_curl "--max-time 60 -X POST ${LLAMA_GW}/v1/chat/completions -H 'Content-Type: application/json' -d '$infer_payload'")
if echo "$infer_json" | grep -q '"content"'; then
  content=$(echo "$infer_json" | grep -o '"content":"[^"]*"' | head -1 | sed 's/"content":"//;s/"//')
  pass "inference: model responded (content: $content)"
else
  fail "inference: no 'content' field in response — $(echo "$infer_json" | head -c 200)"
fi

# ── 4. Task Processing Worker ─────────────────────────────────────────────────
section "Task Processing Worker"

if systemctl is-active --quiet nextcloud-taskprocessing-worker.service; then
  pass "service: nextcloud-taskprocessing-worker is active"
else
  fail "service: nextcloud-taskprocessing-worker NOT active — run: sudo systemctl start nextcloud-taskprocessing-worker"
fi

exit_code=$(systemctl show nextcloud-taskprocessing-worker.service --property=ExecMainStatus 2>/dev/null | cut -d= -f2)
exit_code=${exit_code:-0}
if [[ "$exit_code" == "0" ]]; then
  pass "worker last exit: clean (status=$exit_code)"
else
  fail "worker last exit: non-zero (status=$exit_code) — check: sudo journalctl -u nextcloud-taskprocessing-worker -n 20"
fi

# Check restart rate over the last hour (expected: ~12/h from 300s timeout cycles;
# crash-looping would produce 30+/h with sub-10s cycles).
restarts_1h=$(sudo journalctl -u nextcloud-taskprocessing-worker.service --since="-1h" \
  --no-pager 2>/dev/null | grep -c "Scheduled restart job" || true)
if [[ "$restarts_1h" -le "$WORKER_MAX_RATE" ]]; then
  pass "worker restart rate: ${restarts_1h}/h (last hour) — normal 300s timeout cycling"
else
  fail "worker restart rate: ${restarts_1h}/h (last hour) — exceeds ${WORKER_MAX_RATE}/h; likely crash-looping"
fi

task_count=$(occ taskprocessing:task:stats 2>/dev/null | grep "Number of tasks:" | grep -o '[0-9]*')
if [[ -n "$task_count" && "$task_count" -gt 0 ]]; then
  pass "taskprocessing has ${task_count} historical tasks"
else
  warn "taskprocessing: no tasks in history"
fi

# ── 5. Richdocuments Language Patch ──────────────────────────────────────────
section "Richdocuments Language Patch"

if sudo docker exec "$CONTAINER" grep -q "do not default to any other language" "$DOC_SVC" 2>/dev/null; then
  pass "language patch applied to DocumentGenerationService.php"
else
  fail "language patch NOT applied — run: scripts/patch-richdocuments-language.sh"
fi

# Warn about patch persistence: AUTOMATIC_UPDATES=1 means the container gets replaced
# nightly and the in-container patch is lost. A post-update hook is needed.
if sudo docker inspect nextcloud-aio-mastercontainer \
   --format '{{range .Config.Env}}{{println .}}{{end}}' 2>/dev/null | grep -q "AUTOMATIC_UPDATES=1"; then
  warn "AUTOMATIC_UPDATES=1 — container updates may overwrite the richdocuments patch; consider a post-update cron hook"
fi

# ── 6. Language Regression Check (recent task history) ────────────────────────
section "Language Regression Check (task history)"

# Look for any recent successful chat tasks whose output contains German-language
# markers but whose input appears to be English. Uses simple heuristics.
task_list=$(occ taskprocessing:task:list --type=core:text2text:chat 2>/dev/null)
lang_regressions=0
if [[ -n "$task_list" ]]; then
  # Extract (input, output) pairs from YAML-ish occ output via awk
  # Flag any task where output has German articles/verbs but input lacks French/German
  while IFS= read -r line; do
    echo "$line"
  done <<< "$task_list" | awk '
    /- input:/ { in_input=1; in_output=0 }
    /- output:/ { in_input=0; in_output=1 }
    /- id:/ { id=$NF }
    in_input && /input:/ && !/system_prompt|history|memories|max_tokens|model/ { input=$0 }
    in_output && /output:/ { output=$0 }
    /status: STATUS_SUCCESSFUL/ {
      if (output ~ /\b(die|der|das|ist|sind|nicht|haben|werden)\b/ &&
          input !~ /\b(le |la |les |je |vous |nous |ihr |sie |das )\b/) {
        printf "REGRESSION task=%s input=%s output=%s\n", id, substr(input,1,80), substr(output,1,80)
      }
    }
  ' | while IFS= read -r regression; do
    warn "possible language regression — $regression"
    lang_regressions=$((lang_regressions + 1))
  done
fi
if [[ "$lang_regressions" -eq 0 ]]; then
  pass "language regression check: no German responses detected in chat task history"
fi

# ── 7. End-to-End Chat Round-Trip (requires --e2e-user / --e2e-pass) ──────────
if [[ -n "$E2E_USER" && -n "$E2E_PASS" ]]; then
  section "End-to-End Chat Round-Trip"

  # Submit a chat task via NC taskprocessing API
  e2e_payload='{"type":"core:text2text:chat","appId":"test-assistant","input":{"input":"What color is the sky on a clear day? One sentence.","system_prompt":"You are a test assistant. Always respond in English only.","history":[],"memories":[]}}'

  submit_json=$(curl -sf --max-time 15 \
    -u "${E2E_USER}:${E2E_PASS}" \
    -H "OCS-APIRequest: true" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -X POST "${NC_URL}/ocs/v2.php/taskprocessing/task" \
    -d "$e2e_payload" 2>/dev/null)

  task_id=$(echo "$submit_json" | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)

  if [[ -z "$task_id" ]]; then
    fail "e2e submit: failed to create task — $(echo "$submit_json" | head -c 300)"
  else
    pass "e2e submit: task created (id=$task_id)"

    # Poll for completion (max 120s; average running time from stats is ~43s)
    completed=false
    for _ in $(seq 1 24); do
      sleep 5
      task_json=$(curl -sf --max-time 10 \
        -u "${E2E_USER}:${E2E_PASS}" \
        -H "OCS-APIRequest: true" \
        -H "Accept: application/json" \
        "${NC_URL}/ocs/v2.php/taskprocessing/task/${task_id}" 2>/dev/null)

      # status values: 1=queued 2=running 3=successful 4=failed 5=cancelled
      status=$(echo "$task_json" | grep -o '"status":[0-9]*' | head -1 | cut -d: -f2)
      case "${status:-0}" in
        3) completed=true; break ;;
        4|5) fail "e2e task status: $([ "$status" = "4" ] && echo failed || echo cancelled)"; break ;;
      esac
    done

    if $completed; then
      pass "e2e: task completed"
      output=$(echo "$task_json" | python3 -c "
import sys, json, re
try:
    d = json.load(sys.stdin)
    tasks = d.get('ocs',{}).get('data',{}).get('task',{})
    print(tasks.get('output',{}).get('output',''))
except Exception:
    sys.stdout.write('')
" 2>/dev/null)

      if [[ -z "$output" ]]; then
        warn "e2e: task completed but output was empty"
      else
        pass "e2e output: ${output:0:100}"
        # Language check: English = fine; German/French articles = regression
        if echo "$output" | grep -qiE '\b(die|der|das|ist|sind)\b|\b(le |la |les |je |vous )\b'; then
          fail "e2e language check: response appears to be in German or French — system prompt fix may not be working"
        else
          pass "e2e language check: response appears to be in English"
        fi
      fi
    else
      fail "e2e: task did not complete within 120s"
    fi
  fi
else
  echo "  SKIP  End-to-end test — pass --e2e-user and --e2e-pass to enable"
fi

# ── Summary ───────────────────────────────────────────────────────────────────
section "Results"
echo "  Passed: $PASS  |  Failed: $FAIL  |  Warnings: $WARN"
echo
[[ $FAIL -gt 0 ]] && echo "${FAIL} test(s) failed." && exit 1
[[ $WARN -gt 0 ]] && echo "Tests passed with ${WARN} warning(s)."
echo "All tests passed."
