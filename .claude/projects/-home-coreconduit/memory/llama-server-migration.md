---
name: llama-server-migration
description: "Ollama + llama-server dual inference setup — ports, paths, and harness config"
metadata: 
  node_type: memory
  type: project
  originSessionId: d379bc26-5c8a-4252-a09c-6b6058589f91
---

Originally migrated from Ollama to llama-server 2026-06-22. Ollama reinstalled 2026-06-22 — both now run concurrently on separate ports.

**Why:** llama-server (Unsloth) serves the fine-tuned GGUF model roster; Ollama serves as a separate model manager/server on its native port.

**How to apply:** Two local inference backends. llama-server API at http://localhost:11435/v1 (harness offline mode). Ollama API at http://localhost:11434 (native port, LAN-accessible). Do not confuse the ports.

## Port Assignments (2026-06-22)
| Service | Port | Bind | Systemd unit |
|---|---|---|---|
| Ollama | 11434 | 0.0.0.0 | `/etc/systemd/system/ollama.service` |
| llama-server (Unsloth) | 11435 | 0.0.0.0 | `/etc/systemd/system/llama-server.service` |
| Unsloth Studio UI | 8888 | 0.0.0.0 | `~/.config/systemd/user/unsloth-studio.service` |

## Key Paths
- llama-server binary: `~/.unsloth/llama.cpp/build/bin/llama-server` (tag b9739-mix-2d6bd50, CUDA 13.3)
- llama-server models: `~/.unsloth/studio/models/` (~125 GB, 14 GGUF files)
- Model presets: `~/.unsloth/studio/models/presets.ini` (ctx-size and aliases per model)
- Ollama models: `/usr/share/ollama/.ollama/models/` (separate store, populated via `ollama pull`)

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

## Harness Config (offline mode → llama-server on 11435)
- `cc-mode.sh`, `cc-task-router.sh`, `cc-offline-setup.sh`: all reference `http://localhost:11435`
- `settings.local.json`: allowlist curl health/model checks point to 11435
- `ANTHROPIC_BASE_URL=http://localhost:11435` when running Claude Code offline

## Ollama Setup (2026-06-22)
- Binary: `/usr/local/bin/ollama` (v0.30.10)
- Service fixed: `/usr/share/ollama` created, owned by `ollama:ollama`
- `OLLAMA_HOST=0.0.0.0:11434` set in service unit so LAN can reach it
- No models pulled yet — populate with `ollama pull <model>`

## Verification (2026-06-22)
- `curl http://localhost:11435/health` → `{"status":"ok"}` (llama-server)
- `curl http://localhost:11434/api/tags` → Ollama (0 models initially)
- llama-server `/v1/models` → 14 aliases across 14 GGUFs
- Chat completion (Hermes-3-8B via llama-server) → OK, GPU active
- Embeddings (mxbai-embed-large-v1-F16) → 1024-dim vector
- fileforge tests: 127/127 passed
- litellm-admin-ui tests: 7/7 passed
