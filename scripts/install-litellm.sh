#!/usr/bin/env bash
# install-litellm.sh — installs LiteLLM proxy + admin UI as a systemd user service
# Requires: uv (~/.local/bin/uv), PostgreSQL running, sudo for DB + firewall setup
set -euo pipefail

LITELLM_PORT=4000
LITELLM_HOST=0.0.0.0
CONFIG_DIR="$HOME/.config/litellm"
SERVICE_DIR="$HOME/.config/systemd/user"
DB_NAME=litellm
DB_USER=litellm
DB_PASS=litellm_pass
DB_URL="postgresql://${DB_USER}:${DB_PASS}@localhost:5432/${DB_NAME}"
MASTER_KEY="sk-$(python3 -c 'import secrets; print(secrets.token_urlsafe(32))')"

echo "==> Installing litellm with proxy extras via uv..."
uv tool install 'litellm[proxy]' --with prisma --force

LITELLM_BIN="$HOME/.local/share/uv/tools/litellm/bin"
PRISMA_SCHEMA="$HOME/.local/share/uv/tools/litellm/lib/python3.11/site-packages/litellm/proxy/schema.prisma"
PRISMA_SCHEMA_DIR="$(dirname "$PRISMA_SCHEMA")"

echo "==> Generating Prisma client..."
cd "$PRISMA_SCHEMA_DIR"
PATH="$LITELLM_BIN:$PATH" "$LITELLM_BIN/prisma" generate --schema schema.prisma

echo "==> Setting up PostgreSQL database..."
# Try connecting as the litellm user first — skip creation if it already works
if PGPASSWORD="${DB_PASS}" psql -U "${DB_USER}" -h localhost -d "${DB_NAME}" -c "" >/dev/null 2>&1; then
  echo "    Database '${DB_NAME}' already exists and is reachable, skipping creation."
else
  echo "    (requires sudo for 'postgres' user — enter your password if prompted)"
  if ! sudo -n true 2>/dev/null; then
    echo "    ERROR: sudo requires a password. Run these manually then re-run this script:"
    echo "      sudo -u postgres psql -c \"CREATE USER ${DB_USER} WITH PASSWORD '${DB_PASS}';\""
    echo "      sudo -u postgres psql -c \"CREATE DATABASE ${DB_NAME} OWNER ${DB_USER};\""
    exit 1
  fi
  sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='${DB_USER}'" \
    | grep -q 1 \
    || sudo -u postgres psql -c "CREATE USER ${DB_USER} WITH PASSWORD '${DB_PASS}';"
  sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" \
    | grep -q 1 \
    || sudo -u postgres psql -c "CREATE DATABASE ${DB_NAME} OWNER ${DB_USER};"
fi

echo "==> Writing config to ${CONFIG_DIR}/config.yaml..."
mkdir -p "$CONFIG_DIR"
cat > "$CONFIG_DIR/config.yaml" <<YAML
model_list:
  - model_name: "ollama/qwen3-coder"
    litellm_params:
      model: "ollama/qwen3-coder:30b-a3b-q4_K_M"
      api_base: "http://localhost:11434"

  - model_name: "ollama/mistral-small"
    litellm_params:
      model: "ollama/mistral-small3.2:24b-instruct-2506-q4_K_M"
      api_base: "http://localhost:11434"

  - model_name: "ollama/qwen3-long"
    litellm_params:
      model: "ollama/qwen3.6:latest"
      api_base: "http://localhost:11434"

  - model_name: "ollama/nomic-embed"
    litellm_params:
      model: "ollama/nomic-embed-text:latest"
      api_base: "http://localhost:11434"

general_settings:
  master_key: "${MASTER_KEY}"
  database_url: "${DB_URL}"
  ui_access_mode: "all"
YAML

echo "==> Writing systemd user service..."
mkdir -p "$SERVICE_DIR"
cat > "$SERVICE_DIR/litellm.service" <<SERVICE
[Unit]
Description=LiteLLM Proxy + Admin UI
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
ExecStart=${HOME}/.local/bin/litellm \\
  --config ${CONFIG_DIR}/config.yaml \\
  --host ${LITELLM_HOST} \\
  --port ${LITELLM_PORT}
Restart=on-failure
RestartSec=5
Environment=HOME=${HOME}
Environment=PATH=${LITELLM_BIN}:${HOME}/.local/bin:/usr/local/bin:/usr/bin:/bin
Environment=DATABASE_URL=${DB_URL}

[Install]
WantedBy=default.target
SERVICE

echo "==> Opening port ${LITELLM_PORT} in UFW firewall..."
if systemctl is-active --quiet ufw 2>/dev/null; then
  LAN_SUBNET=$(ip route show | awk '/proto kernel.*src/ {print $1}' | grep -v "^127\|^172\." | head -1)
  if [ -n "$LAN_SUBNET" ]; then
    sudo ufw allow from "$LAN_SUBNET" to any port "$LITELLM_PORT" comment 'LiteLLM proxy LAN' 2>/dev/null \
      || echo "    (ufw rule may already exist, continuing)"
    echo "    Allowed ${LAN_SUBNET} → port ${LITELLM_PORT}"
  else
    sudo ufw allow "${LITELLM_PORT}/tcp" comment 'LiteLLM proxy' 2>/dev/null \
      || echo "    (ufw rule may already exist, continuing)"
    echo "    Allowed any → port ${LITELLM_PORT}"
  fi
else
  echo "    UFW not active, skipping firewall rule"
fi

echo "==> Enabling and starting litellm.service..."
systemctl --user daemon-reload
systemctl --user enable litellm.service
systemctl --user restart litellm.service

echo "==> Waiting for service to come up..."
for _ in $(seq 1 15); do
  if curl -sf "http://localhost:${LITELLM_PORT}/health/readiness" >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

LAN_IP=$(ip route get 1 2>/dev/null | awk '{print $7; exit}')

# ── Install litellm-admin-ui companion ───────────────────────────────────────
ADMIN_DIR="$HOME/litellm-admin-ui"
ADMIN_PORT=4001

if [ -d "$ADMIN_DIR" ]; then
  echo "==> Installing litellm-admin-ui companion..."
  cd "$ADMIN_DIR"
  python3 -m venv .venv
  .venv/bin/pip install -q -r requirements.txt

  ADMIN_PASS=$(python3 -c 'import secrets; print(secrets.token_urlsafe(16))')
  cat > "$ADMIN_DIR/.env" <<ADMINENV
LITELLM_URL=http://localhost:${LITELLM_PORT}
LITELLM_KEY=${MASTER_KEY}
ADMIN_USER=admin
ADMIN_PASS=${ADMIN_PASS}
LITELLM_CONFIG=${CONFIG_DIR}/config.yaml
ADMINENV

  cp "$ADMIN_DIR/litellm-admin.service" "$SERVICE_DIR/litellm-admin.service"

  if systemctl is-active --quiet ufw 2>/dev/null; then
    LAN_SUBNET=$(ip route show | awk '/proto kernel.*src/ {print $1}' | grep -v "^127\|^172\." | head -1)
    if [ -n "$LAN_SUBNET" ]; then
      sudo ufw allow from "$LAN_SUBNET" to any port "$ADMIN_PORT" comment 'LiteLLM admin UI LAN' 2>/dev/null \
        || echo "    (ufw rule may already exist)"
    else
      sudo ufw allow "${ADMIN_PORT}/tcp" comment 'LiteLLM admin UI' 2>/dev/null \
        || echo "    (ufw rule may already exist)"
    fi
  fi

  systemctl --user daemon-reload
  systemctl --user enable litellm-admin.service
  systemctl --user restart litellm-admin.service
  echo "    Admin UI  : http://${LAN_IP}:${ADMIN_PORT}  (admin / ${ADMIN_PASS})"
else
  echo "    NOTE: litellm-admin-ui not found at $ADMIN_DIR — skipping companion install."
fi

echo ""
echo "==> LiteLLM is running!"
echo "    Proxy API     : http://${LAN_IP}:${LITELLM_PORT}"
echo "    Built-in UI   : http://${LAN_IP}:${LITELLM_PORT}/ui  (password: ${MASTER_KEY})"
echo "    CoreConduit UI: http://${LAN_IP}:${ADMIN_PORT}"
echo "    Master key    : ${MASTER_KEY}"
echo "    Config        : ${CONFIG_DIR}/config.yaml"
echo "    NOTE: master_key is saved in ${CONFIG_DIR}/config.yaml"
