---
name: llama-server-migration
description: "Ollama → llama.cpp/llama-server migration — status, decisions, and deferred cleanup"
metadata: 
  node_type: memory
  type: project
  originSessionId: d379bc26-5c8a-4252-a09c-6b6058589f91
---

Completed 2026-06-22. Replaced Ollama with llama-server (bundled in Unsloth Studio at `~/.unsloth/llama.cpp/build/bin/llama-server`) as the local LLM inference backend. All 7 GGUF models migrated without re-downloading — Ollama stored raw GGUF blobs, copied directly.

**Why:** Finer GPU offload control, no Ollama abstraction overhead, llama-server has a mature OpenAI-compatible API, Unsloth Studio already bundled it.

**How to apply:** llama-server is the only local inference backend. Ollama is gone. All harness scripts and downstream tools use the OpenAI-compatible API at http://localhost:11434/v1.

## Key Paths
- Binary: `~/.unsloth/llama.cpp/build/bin/llama-server` (tag b9739-mix-2d6bd50, CUDA 13.3)
- Models dir: `~/.unsloth/studio/models/` (~125 GB, 7 GGUF files)
- Model presets: `~/.unsloth/studio/models/presets.ini` (ctx-size and aliases per model)
- Systemd unit: `/etc/systemd/system/llama-server.service` (runs as coreconduit, port 11434)

## Model Aliases (from presets.ini)
14 GGUFs as of 2026-06-22. Models dir: `~/.unsloth/studio/models/`.

| GGUF File | Aliases |
|---|---|
| Qwen3-Coder-Next-UD-Q4_K_M.gguf (46G) | qwen3-coder, qwen3-coder:30b-a3b-q4_K_M |
| Qwen3-Coder-Next-Q4_K_M.gguf (46G) | qwen3-coder-next, qwen3-coder-next:q4_K_M, qwen3-coder-next:q4_K_M-32k |
| Qwen3.6-35B-A3B-UD-Q4_K_M.gguf (21G) | qwen3.6, qwen3.6:latest |
| Qwen3-30B-A3B-Q4_K_M.gguf (18G) | qwen3:30b-a3b, qwen3:30b-a3b-q4_K_M |
| Llama-3.3-70B-Instruct-Q4_K_M.gguf (40G) | llama3.3, llama3.3:70b-instruct-q4_K_M |
| DeepSeek-R1-70B-Llama-Q4_K_M.gguf (40G) | deepseek-r1:70b, deepseek-r1:70b-llama-distill-q4_K_M |
| DeepSeek-R1-32B-Qwen-Q4_K_M.gguf (19G) | deepseek-r1:32b, deepseek-r1:32b-qwen-distill-q4_K_M |
| Mistral-Small-24B-Q4_K_M.gguf (15G) | mistral-small, mistral-small3.2:24b, mistral-small3.2:24b-instruct-2506-q4_K_M-40k |
| Mistral-Small-3.2-24B-Instruct-2506-Q4_K_M.gguf (14G) | mistral-small3.2:24b-instruct-2506-q4_K_M, mistral-small3.2:24b-40k |
| GLM-4.7-Flash-Q4_K_M.gguf (18G) | glm-4.7-flash, glm-4.7-flash:latest |
| laguna-xs2-Q4_K_M.gguf (19G) | laguna-xs, laguna-xs.2:q4_K_M |
| Gemma-4-12B-Coder-Q4_K_M.gguf (6.9G) | gemma-4:12b |
| Hermes-3-8B.gguf (4.4G) | hermes3:8b |
| mxbai-embed-large-v1-F16.gguf (639M) | nomic-embed-text, nomic-embed-text:latest |

## Integration Points Updated
- `fileforge`: `ollama` pip → `openai>=1.0`, OpenAI client against `/v1`; 127/127 tests pass
- `monitor`: services.json + sys_monitor.py updated to `llama-server`
- `litellm-admin-ui`: `~/.config/litellm/config.yaml` — `ollama/*` → `openai/*`, `/v1` suffix; 7/7 tests pass
- `.claude/ harness`: cc-mode.sh, cc-task-router.sh, cc-ctx-enforce.sh, cc-offline-setup.sh, cc-offline-backup.sh, maintain.sh, local-yield.sh, settings.local.json all rewritten
- `pi TypeScript SDK`: overflow.test.ts, stream.test.ts, README.md, providers.md, models.md updated

## Cleanup — COMPLETE (2026-06-22)
All Ollama artifacts removed:
- `/usr/share/ollama/` blob store — gone
- `~/.ollama/` user config dir — gone
- `/usr/local/bin/ollama` binary — gone
- `/etc/systemd/system/ollama.service` — gone

Note: Some models originally downloaded via Ollama as multi-shard blobs (e.g. mistral-small3.2-24b-instruct-2506-40k) could not be migrated — they are gone. Re-download as single GGUF if needed.

## Verification (2026-06-22)
- `curl http://localhost:11434/health` → `{"status":"ok"}`
- `/v1/models` → 14 aliases (7 models × ~2 aliases each)
- Chat completion (Hermes-3-8B) → OK, GPU active
- Embeddings (mxbai-embed-large-v1-F16) → 1024-dim vector
- fileforge tests: 127/127 passed
- litellm-admin-ui tests: 7/7 passed
