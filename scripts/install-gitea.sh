#!/usr/bin/env bash
#===============================================================================
#  SCRIPT NAME  :  install-gitea.sh
#  DESCRIPTION  :  Download, install, and configure Gitea with optional nginx proxy.
#  AUTHOR       :  Cory / CoreConduit
#  DATE         :  2026-06-16
#  VERSION      :  1.1.0
#  USAGE        :  sudo ./install-gitea.sh [options]
#  DEPENDENCIES :  curl, jq, git, nginx (optional), certbot (optional)
#===============================================================================

set -euo pipefail
IFS=$'\n\t'

#---------------------------------------
#  CONSTANTS & DEFAULTS
#---------------------------------------
SCRIPT_NAME="$(basename "$0")"
readonly SCRIPT_NAME
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
readonly SCRIPT_DIR
readonly SCRIPT_VERSION="1.1.0"

readonly LOG_FILE="${SCRIPT_DIR}/${SCRIPT_NAME%.sh}.log"

VERBOSE=false
DRY_RUN=false

# Gitea install config — override via env or flags
GITEA_USER="gitea"
GITEA_GROUP="gitea"
GITEA_HOME="/var/lib/gitea"
GITEA_CONFIG_DIR="/etc/gitea"
GITEA_BIN="/usr/local/bin/gitea"
GITEA_VERSION=""          # Empty = fetch latest from API
GITEA_HTTP_PORT="3000"
GITEA_SSH_PORT="2222"     # Non-privileged SSH; avoids clash with system SSH
GITEA_DOMAIN="localhost"
GITEA_APP_NAME="Gitea"
GITEA_DB_TYPE="sqlite3"   # sqlite3 | mysql | postgres

# Nginx reverse proxy config
NGINX_ENABLED=true
NGINX_TLS=false          # Self-signed HTTPS
NGINX_CERTBOT=false      # Let's Encrypt (implies TLS; requires real domain)
NGINX_SITES_AVAIL="/etc/nginx/sites-available"
NGINX_SITES_ENABLED="/etc/nginx/sites-enabled"
NGINX_SSL_DIR="/etc/ssl/gitea"

#---------------------------------------
#  COLORS (auto-disabled if not a tty)
#---------------------------------------
if [[ -t 1 ]]; then
    RED='\033[0;31m'
    GREEN='\033[0;32m'
    YELLOW='\033[1;33m'
    BLUE='\033[0;34m'
    CYAN='\033[0;36m'
    BOLD='\033[1m'
    NC='\033[0m'
else
    RED='' GREEN='' YELLOW='' BLUE='' CYAN='' BOLD='' NC=''
fi

#---------------------------------------
#  LOGGING & OUTPUT
#---------------------------------------
log()    { echo -e "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "$LOG_FILE"; }
info()   { log "${GREEN}[INFO]${NC}  $*"; }
warn()   { log "${YELLOW}[WARN]${NC}  $*"; }
error()  { log "${RED}[ERROR]${NC} $*" >&2; }
debug()  { $VERBOSE && log "${CYAN}[DEBUG]${NC} $*" || true; }
header() { echo -e "\n${BOLD}${BLUE}:: $* ::${NC}"; }

die() {
    error "$1"
    exit "${2:-1}"
}

#---------------------------------------
#  CLEANUP TRAP
#---------------------------------------
cleanup() {
    local exit_code=$?
    rm -f /tmp/gitea-download 2>/dev/null || true
    debug "Cleanup complete (exit code: $exit_code)"
    exit $exit_code
}
trap cleanup EXIT
trap 'die "Interrupted by signal" 130' INT TERM

#---------------------------------------
#  HELPERS
#---------------------------------------
usage() {
    cat <<EOF
${BOLD}${SCRIPT_NAME}${NC} v${SCRIPT_VERSION}

${BOLD}DESCRIPTION${NC}
    Download the latest (or pinned) Gitea binary, create a system user,
    configure directories, write app.ini, register a systemd service,
    and optionally configure an nginx reverse proxy (default: enabled).

${BOLD}USAGE${NC}
    sudo ${SCRIPT_NAME} [OPTIONS]

${BOLD}OPTIONS${NC}
    -h, --help              Show this help message
    -v, --verbose           Enable verbose/debug output
    -V, --version           Print version and exit
    -n, --dry-run           Show what would be done without doing it
        --gitea-version X   Pin to a specific Gitea version (e.g. 1.22.1)
        --port N            Internal Gitea HTTP port (default: ${GITEA_HTTP_PORT})
        --ssh-port N        SSH listen port (default: ${GITEA_SSH_PORT})
        --domain DOMAIN     External domain/hostname (default: ${GITEA_DOMAIN})
        --app-name NAME     Instance name shown in UI (default: ${GITEA_APP_NAME})
        --db-type TYPE      Database backend: sqlite3|mysql|postgres (default: ${GITEA_DB_TYPE})
        --no-nginx          Skip nginx installation and configuration
        --tls               Configure nginx with a self-signed TLS certificate
        --certbot           Obtain a Let's Encrypt certificate via certbot (implies --tls)

${BOLD}EXAMPLES${NC}
    sudo ${SCRIPT_NAME}
    sudo ${SCRIPT_NAME} --domain git.example.com --tls
    sudo ${SCRIPT_NAME} --domain git.example.com --certbot
    sudo ${SCRIPT_NAME} --no-nginx --gitea-version 1.22.1 --dry-run
EOF
}

check_dependencies() {
    local deps=("curl" "jq" "git" "openssl")
    local missing=()

    for cmd in "${deps[@]}"; do
        command -v "$cmd" &>/dev/null || missing+=("$cmd")
    done

    [[ ${#missing[@]} -gt 0 ]] && die "Missing dependencies: ${missing[*]}\nInstall: sudo apt install ${missing[*]}"
    debug "All dependencies present"
}

check_root() {
    [[ $EUID -eq 0 ]] || die "Run as root: sudo $SCRIPT_NAME"
}

run_cmd() {
    if $DRY_RUN; then
        info "[DRY RUN] $*"
    else
        debug "Running: $*"
        "$@"
    fi
}

spinner() {
    local pid=$1
    local msg="${2:-Working...}"
    local chars='⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏'
    local i=0
    while kill -0 "$pid" 2>/dev/null; do
        printf "\r${CYAN}%s${NC} %s" "${chars:i++%${#chars}:1}" "$msg"
        sleep 0.1
    done
    printf "\r\033[K"
}

detect_arch() {
    local arch
    arch="$(uname -m)"
    case "$arch" in
        x86_64)          echo "linux-amd64" ;;
        aarch64|arm64)   echo "linux-arm64" ;;
        armv7l)          echo "linux-arm-6" ;;
        *)               die "Unsupported architecture: $arch" ;;
    esac
}

fetch_latest_version() {
    local ver
    ver="$(curl -fsSL "https://api.github.com/repos/go-gitea/gitea/releases/latest" \
        | jq -r '.tag_name' | sed 's/^v//')"
    [[ -n "$ver" && "$ver" != "null" ]] || die "Could not fetch latest Gitea version"
    echo "$ver"
}

#---------------------------------------
#  ARGUMENT PARSING
#---------------------------------------
parse_args() {
    while [[ $# -gt 0 ]]; do
        case "$1" in
            -h|--help)           usage; exit 0 ;;
            -v|--verbose)        VERBOSE=true; shift ;;
            -V|--version)        echo "${SCRIPT_NAME} v${SCRIPT_VERSION}"; exit 0 ;;
            -n|--dry-run)        DRY_RUN=true; shift ;;
            --gitea-version)
                [[ -z "${2:-}" ]] && die "$1 requires a value"
                GITEA_VERSION="$2"; shift 2 ;;
            --port)
                [[ -z "${2:-}" ]] && die "$1 requires a value"
                GITEA_HTTP_PORT="$2"; shift 2 ;;
            --ssh-port)
                [[ -z "${2:-}" ]] && die "$1 requires a value"
                GITEA_SSH_PORT="$2"; shift 2 ;;
            --domain)
                [[ -z "${2:-}" ]] && die "$1 requires a value"
                GITEA_DOMAIN="$2"; shift 2 ;;
            --app-name)
                [[ -z "${2:-}" ]] && die "$1 requires a value"
                GITEA_APP_NAME="$2"; shift 2 ;;
            --db-type)
                [[ -z "${2:-}" ]] && die "$1 requires a value"
                GITEA_DB_TYPE="$2"; shift 2 ;;
            --no-nginx)  NGINX_ENABLED=false; shift ;;
            --tls)       NGINX_TLS=true; shift ;;
            --certbot)   NGINX_CERTBOT=true; NGINX_TLS=true; shift ;;
            --)  shift; break ;;
            -*)  die "Unknown option: $1 (see --help)" ;;
            *)   break ;;
        esac
    done
}

#---------------------------------------
#  INSTALL STEPS
#---------------------------------------
step_resolve_version() {
    header "Resolving Gitea version"
    if [[ -z "$GITEA_VERSION" ]]; then
        info "Fetching latest release from GitHub..."
        GITEA_VERSION="$(fetch_latest_version)"
    fi
    info "Target version: ${BOLD}${GITEA_VERSION}${NC}"
}

step_download_binary() {
    header "Downloading Gitea binary"
    local arch
    arch="$(detect_arch)"
    local url="https://dl.gitea.com/gitea/${GITEA_VERSION}/gitea-${GITEA_VERSION}-${arch}"
    local sig_url="${url}.asc"

    info "Architecture : ${arch}"
    info "Download URL : ${url}"

    if $DRY_RUN; then
        info "[DRY RUN] Would download ${url} → ${GITEA_BIN}"
        return
    fi

    curl -fsSL "$url" -o /tmp/gitea-download &
    spinner $! "Downloading gitea-${GITEA_VERSION}-${arch}..."
    wait $!

    install -o root -g root -m 0755 /tmp/gitea-download "$GITEA_BIN"
    rm -f /tmp/gitea-download
    info "Binary installed to ${GITEA_BIN}"

    local installed_ver
    installed_ver="$("$GITEA_BIN" --version 2>&1 | awk '{print $3}')"
    info "Verified: gitea ${installed_ver}"
}

step_create_user() {
    header "Creating system user"
    if id "$GITEA_USER" &>/dev/null; then
        info "User '${GITEA_USER}' already exists — skipping"
        return
    fi
    run_cmd groupadd --system "$GITEA_GROUP"
    run_cmd useradd \
        --system \
        --gid "$GITEA_GROUP" \
        --home-dir "$GITEA_HOME" \
        --shell /bin/bash \
        --comment "Gitea service account" \
        "$GITEA_USER"
    info "Created system user '${GITEA_USER}'"
}

step_create_directories() {
    header "Creating directory structure"
    local dirs=(
        "${GITEA_HOME}/custom"
        "${GITEA_HOME}/data"
        "${GITEA_HOME}/log"
        "${GITEA_CONFIG_DIR}"
    )
    for dir in "${dirs[@]}"; do
        run_cmd mkdir -p "$dir"
        debug "Created ${dir}"
    done

    # Config dir writable only by gitea during initial setup;
    # tightened to 0750 after first run via post-install note.
    run_cmd chown -R "${GITEA_USER}:${GITEA_GROUP}" "$GITEA_HOME"
    run_cmd chown -R "root:${GITEA_GROUP}" "$GITEA_CONFIG_DIR"
    run_cmd chmod 0770 "$GITEA_CONFIG_DIR"
    info "Directories configured"
}

step_write_config() {
    header "Writing app.ini"
    local config_file="${GITEA_CONFIG_DIR}/app.ini"

    if [[ -f "$config_file" ]] && ! $DRY_RUN; then
        warn "Config already exists at ${config_file} — skipping (delete to regenerate)"
        return
    fi

    local secret_key
    secret_key="$(openssl rand -hex 32 2>/dev/null || head -c 32 /dev/urandom | base64 | tr -dc 'a-zA-Z0-9' | head -c 32)"
    local internal_token
    internal_token="$(openssl rand -hex 32 2>/dev/null || head -c 32 /dev/urandom | base64 | tr -dc 'a-zA-Z0-9' | head -c 32)"

    if $DRY_RUN; then
        info "[DRY RUN] Would write ${config_file}"
        return
    fi

    # When nginx fronts Gitea, bind locally and set ROOT_URL to the public address.
    local http_bind="0.0.0.0"
    local root_url="http://${GITEA_DOMAIN}:${GITEA_HTTP_PORT}/"
    if $NGINX_ENABLED; then
        http_bind="127.0.0.1"
        if $NGINX_TLS; then
            root_url="https://${GITEA_DOMAIN}/"
        else
            root_url="http://${GITEA_DOMAIN}/"
        fi
    fi

    cat > "$config_file" <<EOF
APP_NAME = ${GITEA_APP_NAME}
RUN_MODE = prod
RUN_USER = ${GITEA_USER}

[server]
PROTOCOL              = http
DOMAIN                = ${GITEA_DOMAIN}
HTTP_ADDR             = ${http_bind}
HTTP_PORT             = ${GITEA_HTTP_PORT}
ROOT_URL              = ${root_url}
SSH_DOMAIN            = ${GITEA_DOMAIN}
SSH_PORT              = ${GITEA_SSH_PORT}
START_SSH_SERVER      = true
DISABLE_SSH           = false
OFFLINE_MODE          = false

[database]
DB_TYPE  = ${GITEA_DB_TYPE}
PATH     = ${GITEA_HOME}/data/gitea.db

[repository]
ROOT = ${GITEA_HOME}/data/repositories

[log]
MODE      = file
LEVEL     = info
ROOT_PATH = ${GITEA_HOME}/log

[security]
INSTALL_LOCK       = false
SECRET_KEY         = ${secret_key}
INTERNAL_TOKEN     = ${internal_token}
PASSWORD_HASH_ALGO = argon2

[service]
DISABLE_REGISTRATION              = false
REQUIRE_SIGNIN_VIEW               = false
REGISTER_EMAIL_CONFIRM            = false
ENABLE_NOTIFY_MAIL                = false
ALLOW_ONLY_EXTERNAL_REGISTRATION  = false
ENABLE_CAPTCHA                    = false
DEFAULT_KEEP_EMAIL_PRIVATE        = true
DEFAULT_ALLOW_CREATE_ORGANIZATION = true

[mailer]
ENABLED = false

[cache]
ADAPTER = memory

[session]
PROVIDER = file

[picture]
DISABLE_GRAVATAR        = false
ENABLE_FEDERATED_AVATAR = true

[openid]
ENABLE_OPENID_SIGNIN = false
ENABLE_OPENID_SIGNUP = false
EOF

    chown "root:${GITEA_GROUP}" "$config_file"
    chmod 0640 "$config_file"
    info "Config written to ${config_file}"
}

step_write_systemd_unit() {
    header "Installing systemd service"
    local unit_file="/etc/systemd/system/gitea.service"

    if $DRY_RUN; then
        info "[DRY RUN] Would write ${unit_file}"
        return
    fi

    cat > "$unit_file" <<EOF
[Unit]
Description=Gitea (self-hosted Git service)
After=network.target
After=syslog.target
Wants=network.target

[Service]
Type=simple
User=${GITEA_USER}
Group=${GITEA_GROUP}
WorkingDirectory=${GITEA_HOME}
ExecStart=${GITEA_BIN} web --config ${GITEA_CONFIG_DIR}/app.ini
Restart=always
RestartSec=5
Environment=USER=${GITEA_USER} HOME=${GITEA_HOME} GITEA_WORK_DIR=${GITEA_HOME}
CapabilityBoundingSet=CAP_NET_BIND_SERVICE
AmbientCapabilities=CAP_NET_BIND_SERVICE
PrivateTmp=true
ProtectSystem=full

[Install]
WantedBy=multi-user.target
EOF

    systemctl daemon-reload
    systemctl enable gitea
    info "Service enabled"
}

step_configure_nginx() {
    $NGINX_ENABLED || { debug "nginx skipped (--no-nginx)"; return; }
    header "Configuring nginx reverse proxy"

    # Install nginx if absent
    if ! command -v nginx &>/dev/null; then
        info "nginx not found — installing..."
        run_cmd apt-get install -y nginx
    fi

    local site_conf="${NGINX_SITES_AVAIL}/gitea"
    local site_link="${NGINX_SITES_ENABLED}/gitea"

    if $DRY_RUN; then
        info "[DRY RUN] Would write ${site_conf} and reload nginx"
        return
    fi

    # ── TLS: self-signed cert ──────────────────────────────────────────────────
    local ssl_cert="" ssl_key=""
    if $NGINX_TLS && ! $NGINX_CERTBOT; then
        ssl_cert="${NGINX_SSL_DIR}/cert.pem"
        ssl_key="${NGINX_SSL_DIR}/key.pem"
        if [[ ! -f "$ssl_cert" ]]; then
            info "Generating self-signed certificate for ${GITEA_DOMAIN}..."
            mkdir -p "$NGINX_SSL_DIR"
            chmod 0700 "$NGINX_SSL_DIR"
            openssl req -x509 -nodes -days 3650 \
                -newkey rsa:4096 \
                -keyout "$ssl_key" \
                -out "$ssl_cert" \
                -subj "/CN=${GITEA_DOMAIN}/O=Gitea/C=US" \
                -addext "subjectAltName=DNS:${GITEA_DOMAIN}" \
                2>/dev/null
            chmod 0600 "$ssl_key"
            info "Self-signed cert written to ${ssl_cert}"
        else
            info "Existing cert found at ${ssl_cert} — skipping generation"
        fi
    fi

    # ── Write nginx site config ────────────────────────────────────────────────
    if $NGINX_TLS; then
        cat > "$site_conf" <<NGINX
# Gitea reverse proxy — HTTPS
# Managed by install-gitea.sh

server {
    listen 80;
    listen [::]:80;
    server_name ${GITEA_DOMAIN};
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl;
    listen [::]:443 ssl;
    server_name ${GITEA_DOMAIN};

    ssl_certificate     ${ssl_cert};
    ssl_certificate_key ${ssl_key};
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305;
    ssl_prefer_server_ciphers off;
    ssl_session_cache   shared:SSL:10m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;

    # HSTS — enable once cert is stable
    # add_header Strict-Transport-Security "max-age=63072000" always;

    client_max_body_size 512m;

    location / {
        proxy_pass         http://127.0.0.1:${GITEA_HTTP_PORT};
        proxy_http_version 1.1;
        proxy_set_header   Host              \$host;
        proxy_set_header   X-Real-IP         \$remote_addr;
        proxy_set_header   X-Forwarded-For   \$proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto \$scheme;
        proxy_read_timeout 300s;
        proxy_connect_timeout 10s;
    }
}
NGINX
    else
        cat > "$site_conf" <<NGINX
# Gitea reverse proxy — HTTP
# Managed by install-gitea.sh

server {
    listen 80;
    listen [::]:80;
    server_name ${GITEA_DOMAIN};

    client_max_body_size 512m;

    location / {
        proxy_pass         http://127.0.0.1:${GITEA_HTTP_PORT};
        proxy_http_version 1.1;
        proxy_set_header   Host              \$host;
        proxy_set_header   X-Real-IP         \$remote_addr;
        proxy_set_header   X-Forwarded-For   \$proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto \$scheme;
        proxy_read_timeout 300s;
        proxy_connect_timeout 10s;
    }
}
NGINX
    fi

    # Enable site
    ln -sf "$site_conf" "$site_link"

    # Remove default site if it would conflict on port 80
    if [[ -f "${NGINX_SITES_ENABLED}/default" ]]; then
        warn "Removing nginx default site to avoid port 80 conflict"
        rm -f "${NGINX_SITES_ENABLED}/default"
    fi

    # Test config before reloading
    nginx -t 2>&1 | tee -a "$LOG_FILE" || die "nginx config test failed — see ${LOG_FILE}"

    systemctl enable nginx
    systemctl reload-or-restart nginx
    info "nginx configured and reloaded"

    # ── Certbot (runs after nginx is up with HTTP config) ──────────────────────
    if $NGINX_CERTBOT; then
        if ! command -v certbot &>/dev/null; then
            info "Installing certbot..."
            apt-get install -y certbot python3-certbot-nginx
        fi
        info "Requesting Let's Encrypt certificate for ${GITEA_DOMAIN}..."
        certbot --nginx \
            --non-interactive \
            --agree-tos \
            --email "admin@${GITEA_DOMAIN}" \
            --domains "${GITEA_DOMAIN}" \
            --redirect || warn "certbot failed — check DNS and try: certbot --nginx -d ${GITEA_DOMAIN}"
        info "Certbot complete"
    fi
}

step_start_service() {
    header "Starting Gitea"
    if $DRY_RUN; then
        info "[DRY RUN] Would start and enable gitea.service"
        return
    fi

    systemctl start gitea
    # Brief wait then check status
    sleep 2
    if systemctl is-active --quiet gitea; then
        info "Gitea is ${GREEN}running${NC}"
    else
        warn "Gitea did not start cleanly — check: journalctl -u gitea -n 50"
    fi
}

print_summary() {
    header "Installation complete"

    local public_url
    if $NGINX_ENABLED; then
        $NGINX_TLS && public_url="https://${GITEA_DOMAIN}/" || public_url="http://${GITEA_DOMAIN}/"
    else
        public_url="http://${GITEA_DOMAIN}:${GITEA_HTTP_PORT}/"
    fi

    cat <<EOF

  ${BOLD}Gitea ${GITEA_VERSION}${NC} installed successfully.

  ${BOLD}Web UI${NC}       ${public_url}
  ${BOLD}SSH clone${NC}    git@${GITEA_DOMAIN}:${GITEA_SSH_PORT}
  ${BOLD}Config${NC}       ${GITEA_CONFIG_DIR}/app.ini
  ${BOLD}Data${NC}         ${GITEA_HOME}/data/
  ${BOLD}Gitea logs${NC}   ${GITEA_HOME}/log/
EOF

    if $NGINX_ENABLED; then
        cat <<EOF
  ${BOLD}nginx site${NC}   ${NGINX_SITES_AVAIL}/gitea
  ${BOLD}nginx logs${NC}   /var/log/nginx/
EOF
    fi

    cat <<EOF

  ${YELLOW}Next steps:${NC}
    1. Open the web UI and complete the setup wizard.
    2. Once the admin account is created, tighten the config dir:
         sudo chmod 0750 ${GITEA_CONFIG_DIR}
    3. Set DISABLE_REGISTRATION=true in app.ini when open signups aren't needed.
EOF

    if $NGINX_TLS && ! $NGINX_CERTBOT; then
        cat <<EOF
    4. Self-signed cert in use — browsers will warn. To use Let's Encrypt:
         sudo certbot --nginx -d ${GITEA_DOMAIN}
       Or re-run this script with --certbot instead of --tls.
EOF
    fi

    cat <<EOF

  ${BOLD}Service commands:${NC}
    sudo systemctl status gitea
    sudo systemctl restart gitea
    sudo journalctl -u gitea -f

EOF
}

#---------------------------------------
#  MAIN
#---------------------------------------
main() {
    parse_args "$@"
    header "${SCRIPT_NAME} v${SCRIPT_VERSION}"

    check_root
    check_dependencies

    $DRY_RUN && warn "Dry-run mode — no changes will be made"

    step_resolve_version
    step_download_binary
    step_create_user
    step_create_directories
    step_write_config
    step_write_systemd_unit
    step_configure_nginx
    step_start_service
    print_summary
}

main "$@"
