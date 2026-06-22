#!/usr/bin/env bash
# ──────────────────────────────────────────────────────────────────
# cc-session-start.sh — SessionStart hook for Claude Code
# Outputs context that gets injected at session start.
#
# Checks:
#   1. Project name, working directory, current mode
#   2. Knowledge graph orientation
#   3. Codebase summary freshness
#   4. Handoff detection and handoff.md injection
#   5. Memory index size warnings
#
# NOTE: context-refresh.md is loaded via the @reference in CLAUDE.md.
#       Do NOT cat it here — that causes double-loading and wastes tokens.
# ──────────────────────────────────────────────────────────────────

PROJECT_NAME=$(basename "$(pwd)")
MARKER_FILE="$HOME/.claude/.current-mode"
SUMMARY_FILE=".claude/codebase-summary.md"
# Replace all / with - to match Claude Code's project directory encoding
_PROJECT_KEY="$(pwd | sed 's|/|-|g')"
HANDOFF_FILE="$HOME/.claude/projects/${_PROJECT_KEY}/handoff.md"
MEMORY_FILE="$HOME/.claude/projects/${_PROJECT_KEY}/memory/MEMORY.md"

# ── Mode awareness ────────────────────────────────────────────────
MODE="online"
if [ -f "$MARKER_FILE" ]; then
    MODE=$(cat "$MARKER_FILE")
elif [[ "${ANTHROPIC_BASE_URL:-}" =~ localhost|127\.0\.0\.1 ]]; then
    # Fallback: detect offline from env when running claude directly (not via cc-session)
    MODE="offline"
fi

if [ "$MODE" = "offline" ]; then
    MODEL_INFO="OFFLINE — llama-server (llama.cpp) at :11435"
else
    MODEL_INFO="ONLINE — Anthropic API"
fi

# ── Offline: warn about network-dependent plugins ─────────────────────
OFFLINE_PLUGIN_WARNING=""
if [ "$MODE" = "offline" ]; then
    OFFLINE_PLUGIN_WARNING=$(python3 - "$HOME/.claude/settings.json" 2>/dev/null <<'PYEOF'
import json, sys
try:
    with open(sys.argv[1]) as f:
        d = json.load(f)
    plugins = d.get("enabledPlugins", {})
    network_plugins = {
        "context7@claude-plugins-official":   "context7 (fetches docs from context7.com)",
        "coderabbit@claude-plugins-official": "coderabbit (calls CodeRabbit cloud API)",
    }
    degraded = [label for key, label in network_plugins.items() if plugins.get(key)]
    if degraded:
        print("⚠ Network-dependent plugins active (will fail offline): " + ", ".join(degraded))
except Exception:
    pass
PYEOF
    )
fi

# ── Codebase summary check ────────────────────────────────────────
SUMMARY_STATUS=""
if [ -f "$SUMMARY_FILE" ]; then
    # Check age
    NOW=$(date +%s)
    MOD=$(stat -c %Y "$SUMMARY_FILE" 2>/dev/null || stat -f %m "$SUMMARY_FILE" 2>/dev/null || echo "$NOW")
    AGE_DAYS=$(( (NOW - MOD) / 86400 ))
    if [ "$AGE_DAYS" -gt 7 ]; then
        SUMMARY_STATUS="⚠ codebase-summary.md is ${AGE_DAYS} days old — consider running: cc-reindex"
    fi
else
    # Check if CLAUDE.md references it (dead reference)
    for claude_md in "CLAUDE.md" ".claude/CLAUDE.md"; do
        if [ -f "$claude_md" ] && grep -q "codebase-summary" "$claude_md" 2>/dev/null; then
            SUMMARY_STATUS="⚠ CLAUDE.md references codebase-summary.md but the file does not exist. Run: cc-reindex"
            break
        fi
    done
fi

# ─── Detect and announce existing handoff.md ──────────────────────
HANDOFF_STATUS=""
if [ -f "$HANDOFF_FILE" ]; then
    HANDOFF_STATUS="═══════════════════════════════════════════
  HANDOFF DETECTED: handoff.md exists
  This session can resume from prior state.
  Tell Claude: 'Resume from handoff.md'
═══════════════════════════════════════════

--- HANDOFF CONTEXT ---
"
    HANDOFF_STATUS+=$(cat "$HANDOFF_FILE" 2>/dev/null || true)
    HANDOFF_STATUS+="
--- END HANDOFF ---
"
fi

# ─── Memory index size check ───────────────────────────────────────
MEMORY_STATUS=""
if [ -f "$MEMORY_FILE" ]; then
    LINE_COUNT=$(wc -l < "$MEMORY_FILE" 2>/dev/null || echo "0")
    if [ "$LINE_COUNT" -gt 200 ]; then
        MEMORY_STATUS="⚠ MEMORY.md is $LINE_COUNT lines (limit: 200). Consider pruning."
    fi
fi

# ─── Check context-refresh.md freshness ────────────────────────────
REFRESH_FILE="$HOME/.claude/context-refresh.md"
REFRESH_WARNING=""
if [ -f "$REFRESH_FILE" ]; then
    _REFRESH_AGE_SEC=$(( $(date +%s) - $(stat -c %Y "$REFRESH_FILE") ))
    REFRESH_DAYS=$(( _REFRESH_AGE_SEC / 86400 ))
    if [ "$REFRESH_DAYS" -gt 7 ]; then
        REFRESH_WARNING="⚠ context-refresh.md is ${REFRESH_DAYS} days old. Consider updating."
    fi
fi

# ── Offline: pre-warm primary model (background, no output) ──────────
# keep_alive=5m: model stays resident across normal turn-taking but unloads
# during long CPU-bound tool calls (e.g. `php artisan test`, builds), freeing
# RAM and cores for the test process. 24h pinned ~53 GiB indefinitely and
# caused CPU contention with local builds.
if [ "$MODE" = "offline" ]; then
    SETTINGS="$HOME/.claude/settings.json"
    _MODEL=$(python3 -c "
import json
with open('$SETTINGS') as f:
    env = json.load(f).get('env', {})
print(env.get('ANTHROPIC_MODEL', env.get('ANTHROPIC_DEFAULT_SONNET_MODEL', '')))
" 2>/dev/null)
    if [ -n "$_MODEL" ]; then
        curl -s http://localhost:11435/v1/chat/completions \
            -H 'Content-Type: application/json' \
            -d "{\"model\":\"$_MODEL\",\"messages\":[{\"role\":\"user\",\"content\":\"ping\"}],\"max_tokens\":1,\"stream\":false}" \
            --max-time 300 >/dev/null 2>&1 &
    fi
fi

# ── Output ────────────────────────────────────────────────────────
cat << EOF
## Session Context
Project: ${PROJECT_NAME}
Working directory: $(pwd)
Mode: ${MODEL_INFO}
EOF

if [ -n "$OFFLINE_PLUGIN_WARNING" ]; then
    echo "$OFFLINE_PLUGIN_WARNING"
fi

if [ -n "$SUMMARY_STATUS" ]; then
    echo "$SUMMARY_STATUS"
fi

if [ -n "$HANDOFF_STATUS" ]; then
    echo "$HANDOFF_STATUS"
fi

if [ -n "$MEMORY_STATUS" ]; then
    echo "$MEMORY_STATUS"
fi

if [ -n "$REFRESH_WARNING" ]; then
    echo "$REFRESH_WARNING"
fi

# ── Consolidation freshness check ─────────────────────────
# Check when memory was last consolidated
# Note: `date -d` requires GNU date (works on Pi 5 Ubuntu, not on macOS/BSD)
consolidation_check() {
    MEMORY_FILE="$HOME/.claude/projects/${_PROJECT_KEY}/memory/MEMORY.md"
    if [ -f "$MEMORY_FILE" ]; then
        # grep -o + sed: portable alternative to grep -oP (GNU-only PCRE flag)
        LAST_CONSOL=$(grep -o 'Last consolidated: [0-9-]*' "$MEMORY_FILE" 2>/dev/null | sed 's/Last consolidated: //')
        if [ -n "$LAST_CONSOL" ]; then
            # GNU date for Linux (Pi 5)
            # Fallback to epoch 0: a bad date parse gives a huge age, triggering the warning
            CONSOL_AGE=$(( ($(date +%s) - $(date -d "$LAST_CONSOL" +%s 2>/dev/null || echo 0)) / 86400 ))
            if [ "$CONSOL_AGE" -gt 30 ]; then
                echo "⚠ Memory last consolidated ${CONSOL_AGE} days ago. Run /consolidate."
            fi
        fi
    fi
}
consolidation_check

cat << EOF

At the start of this session:
1. Check the knowledge graph (search_nodes) for "${PROJECT_NAME}" and list any open bugs or pending tasks.
   If the knowledge graph is unavailable, note: "Memory: NOT AVAILABLE — /checkpoint and /pickup will not persist."
2. If maintenance-escalations.md exists, note it — the user can run /maintain to handle it.
3. If patterns_${PROJECT_NAME} exists in memory, review learned patterns to avoid repeating mistakes.
4. If handoff.md exists, read it first before doing any work.
5. Use mcp__memory__search_nodes to find relevant memories for this project.
EOF

# ── Correction queue check ──────────────────────────────
QUEUE_FILE="$HOME/.claude/learnings-queue.json"
if [ -f "$QUEUE_FILE" ]; then
    QUEUE_COUNT=$(python3 -c "
import json
try:
    with open('$QUEUE_FILE') as f:
        data = json.load(f)
    print(len(data) if isinstance(data, list) else 0)
except: print(0)
" 2>/dev/null)
    if [ "$QUEUE_COUNT" -gt 10 ]; then
        echo "⚠ ${QUEUE_COUNT} corrections queued. Run /reflect to process."
    fi
fi
