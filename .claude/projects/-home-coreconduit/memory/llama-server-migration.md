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
| GGUF File | Aliases |
|---|---|
| Qwen3-Coder-Next-UD-Q4_K_M.gguf | qwen3-coder, qwen3-coder:30b-a3b-q4_K_M |
| Mistral-Small-24B-Q4_K_M.gguf | mistral-small, mistral-small3.2:24b |
| mxbai-embed-large-v1-F16.gguf | nomic-embed-text, mxbai-embed-large-v1-F16 |
| DeepSeek-R1-70B-Llama-Q4_K_M.gguf | deepseek-r1:70b |
| DeepSeek-R1-32B-Qwen-Q4_K_M.gguf | deepseek-r1:32b |
| Gemma-4-12B-Coder-Q4_K_M.gguf | gemma-4:12b |
| Hermes-3-8B.gguf | hermes3:8b |

## Integration Points Updated
- `fileforge`: `ollama` pip → `openai>=1.0`, OpenAI client against `/v1`; 127/127 tests pass
- `monitor`: services.json + sys_monitor.py updated to `llama-server`
- `litellm-admin-ui`: `~/.config/litellm/config.yaml` — `ollama/*` → `openai/*`, `/v1` suffix; 7/7 tests pass
- `.claude/ harness`: cc-mode.sh, cc-task-router.sh, cc-ctx-enforce.sh, cc-offline-setup.sh, cc-offline-backup.sh, maintain.sh, local-yield.sh, settings.local.json all rewritten
- `pi TypeScript SDK`: overflow.test.ts, stream.test.ts, README.md, providers.md, models.md updated

## Deferred Cleanup (safe to do after 2026-06-23)
```bash
sudo rm -rf /usr/share/ollama/   # ~125 GB blob store
rm -rf ~/.ollama/                 # user config dir
```
Ollama blob data was the original source for all 7 GGUFs. After 24h stable operation, delete.

## Verification (2026-06-22)
- `curl http://localhost:11434/health` → `{"status":"ok"}`
- `/v1/models` → 14 aliases (7 models × ~2 aliases each)
- Chat completion (Hermes-3-8B) → OK, GPU active
- Embeddings (mxbai-embed-large-v1-F16) → 1024-dim vector
- fileforge tests: 127/127 passed
- litellm-admin-ui tests: 7/7 passed
