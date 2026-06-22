# CoreConduit — Global Preferences

## Identity

CoreConduit Consulting Services — Technical Craftsman: Cory.
Mission: Empower community-focused organizations and nonprofits with dynamic, tailored operational technology that strengthens effectiveness, protects data sovereignty, and supports long-term sustainability.
All open-source tools are MIT-licensed; they serve as reputation and lead-gen vehicles for consulting, not standalone revenue.

## Stack

- Workstation: Ubuntu 24.04 (x86_64), 62 GB RAM, 20 cores, RTX 5070 (12 GB VRAM). GPU-hybrid inference: models ≤12 GB run fully on GPU; larger models (18 GB qwen3-coder, 15 GB mistral) offload ~70% of layers to GPU with remainder on CPU. Self-hosted, cloud-independent, offline-capable.
- Deployment target: Raspberry Pi 5 nodes for NEXUS multi-Pi platform; the harness itself does not assume Pi hardware.
- Languages: Python 3.13, JavaScript/Node 22
- Frameworks: FastAPI, React, WebSocket
- Databases: SQLite, ChromaDB (NEXUS RAG memory)
- AI: llama-server (llama.cpp) local inference at `:11434` (OpenAI-compatible API). Binary: `~/.unsloth/llama.cpp/build/bin/llama-server`. Models: `~/.unsloth/studio/models/`. See `cc-mode.sh` and `cc-task-router.sh` for routing config.
  - `qwen3-coder` — Qwen3-Coder-Next-UD-Q4_K_M (46G) — primary coding model
  - `qwen3-coder-next` — Qwen3-Coder-Next-Q4_K_M (46G)
  - `qwen3.6` / `qwen3.6:latest` — Qwen3.6-35B-A3B-UD-Q4_K_M (21G)
  - `qwen3:30b-a3b-q4_K_M` — Qwen3-30B-A3B-Q4_K_M (18G)
  - `llama3.3` — Llama-3.3-70B-Instruct-Q4_K_M (40G)
  - `deepseek-r1:70b` — DeepSeek-R1-70B-Llama-Q4_K_M (40G)
  - `deepseek-r1:32b` — DeepSeek-R1-32B-Qwen-Q4_K_M (19G)
  - `mistral-small` / `mistral-small3.2:24b` — Mistral-Small-24B-Q4_K_M (15G) — subagent model
  - `mistral-small3.2:24b-instruct-2506-q4_K_M` — Mistral-Small-3.2-24B-Instruct-2506-Q4_K_M (14G)
  - `glm-4.7-flash` — GLM-4.7-Flash-Q4_K_M (18G)
  - `laguna-xs.2:q4_K_M` — laguna-xs2-Q4_K_M (19G)
  - `gemma-4:12b` — Gemma-4-12B-Coder-Q4_K_M (6.9G)
  - `hermes3:8b` — Hermes-3-8B (4.4G)
  - `nomic-embed-text` — mxbai-embed-large-v1-F16 (639M) — embeddings
- Services: systemd, NEXUS (:5000, not yet deployed)

## Offline Mode (Local LLM)

When running with `ANTHROPIC_BASE_URL=http://localhost:11434` you are backed by a
local llama-server model (llama.cpp), NOT Claude. This changes how you must operate:

### Know Your Limits
- **Context window**: 32K tokens enforced for primary, 40K for subagent (vs 1M on cloud).
  Native model windows go up to 262K but we cap KV cache to keep RAM use predictable
  on hybrid inference. You will fill this fast.
- **Latency**: GPU-hybrid inference (RTX 5070). Expect 10–30 tokens/sec for GPU-cached layers;
  CPU layers still bottleneck for oversized models. qwen3-coder:30b offloads 29/41 layers to GPU.
- **Tool call reliability**: Multi-step tool chains degrade after ~15 turns. Break
  complex work into discrete prompts instead of long agentic sessions.
- **Reasoning depth**: Multi-file reasoning works on the 24B+ models in this lineup,
  but verify tool output before chaining — local models hallucinate paths and JSON
  more readily than cloud Claude.

### Operating Rules (offline only)
1. **Aggressive compaction**: Run `/compact` at the first CAUTION warning (50%
   context remaining). Do not wait. Your context window is 30x smaller than cloud.
2. **Break down tasks**: A task spanning 5+ files MUST be split into sub-tasks
   that each touch ≤2 files. Complete and compact between sub-tasks.
3. **Verify tool output**: Inspect tool call results before chaining. You are
   more likely to emit malformed JSON or wrong file paths than cloud Claude.
4. **Prefer Write over Edit**: `Write` is atomic and self-verifying. `Edit` with
   old_string matching is less reliable with smaller models.
5. **Use task routing**: For background analysis (log scanning, error
   classification), use `cc-task-router.sh run "task" --tier light`. Reserve
   the heavy model only for code generation and debugging.

### Available Harness Tools (offline)
| Tool | Purpose |
|------|---------|
| `cc-mode.sh status` | Check online/offline mode |
| `cc-task-router.sh list-tiers` | Show model routing tiers |
| `cc-ctx-enforce.sh MODEL NUM_CTX` | Ensure model has adequate context window |
| `cc-session --profile offline` | Launch with offline profile |

### Key File Locations (for when you lose context)
- Harness config: `~/.claude/settings.json`, `~/.claude/settings.local.json`
- CLAUDE.md (this file): `~/.claude/CLAUDE.md`
- Context refresh rules: `~/.claude/context-refresh.md`
- Language rules: `~/.claude/rules/{python,php,javascript,arduino,common}/`
- Project memory: `~/.claude/projects/*/memory/MEMORY.md`
- Hook scripts: `~/.claude/hooks/`, `~/.claude/cc-*.sh`

## Brand v2.1 (Silver Theme)

When building CoreConduit-branded UI:

- Backgrounds: dark navy (#0d1421) topbar/statusbar, silver (#e9edf2) content
- Fonts: Exo 2 (display/headings), Plus Jakarta Sans (body), IBM Plex Mono (code)
- Accents: blue (#2b7de9), orange (#e07018)
- Tool naming: split with orange second half (e.g., Log|Fix, Net|Watch)
- Cards: 3px gradient bar (blue→orange) on top edge
- Brand voice: balanced, confident, human-centered, technically credible, never condescending

## Rules

Rules are loaded automatically from `~/.claude/rules/`.
See `common/`, `python/`, `php/`, `javascript/`, `arduino/`
for language-specific conventions.

Do NOT duplicate rules here — edit the rules/ files directly.

## Workflow

- Always run tests after making changes — if a test suite exists, run it.
- When fixing a bug, verify the fix by running the relevant test.
- Delegate verbose reads and research to subagents to preserve main context.

## Definition of Done

A task is complete only when ALL of these pass:
1. Tests pass (run the relevant test suite)
2. No open syntax errors (syntax-check hook must pass)
3. Code follows style rules for the language
4. Public API or behaviour changes have updated docs

## Decision Autonomy

Do not ask unnecessary questions. Make a reasonable decision, state the
assumption briefly, and proceed. Reserve questions for genuinely ambiguous
requirements where the wrong choice would cause rework.

## Git Backup Before Risky Operations

Before refactors, schema changes, or multi-file rewrites:
`git checkout -b backup/before-<description>`
Push if multi-session. Delete after the change ships.

## Lesson Capture

Discoveries, corrections, and validated patterns are stored in
`~/.claude/projects/*/memory/` via the memory system (MEMORY.md index).
Run /reflect at session end to capture anything new. The /remember skill
stores one-off facts immediately.

## Context Refresh

@~/.claude/context-refresh.md

## Context Management

- Monitor context usage silently. Compact proactively at ~70%.
- At 80%+: write handoff.md (task progress, modified files, decisions, next steps), commit WIP, announce.
- At 90%+: stop new work, finalize handoff.md, commit, tell user to /clear and resume.
- After resuming from handoff, delete the old handoff.md.

## Project Portfolio

- **NEXUS** — Multi-Pi agentic platform (gateway, reasoner, tool registry, curiosity engine, voice). The single active focus.
