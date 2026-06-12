#!/usr/bin/env bash
# Nextcloud All-in-One — Installation Script
# Source: https://github.com/nextcloud/all-in-one
# Supports: Linux x86_64 / aarch64
#
# Usage:
#   sudo bash install-nextcloud-aio.sh               # standard (AIO owns 80/443)
#   sudo bash install-nextcloud-aio.sh --reverse-proxy  # behind nginx/caddy/traefik

set -euo pipefail

# ── Colours ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'; YELLOW='\033[1;33m'; GREEN='\033[0;32m'
CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'

info()  { echo -e "${CYAN}[INFO]${RESET}  $*"; }
ok()    { echo -e "${GREEN}[OK]${RESET}    $*"; }
warn()  { echo -e "${YELLOW}[WARN]${RESET}  $*"; }
die()   { echo -e "${RED}[ERROR]${RESET} $*" >&2; exit 1; }

# ── Arg parsing ───────────────────────────────────────────────────────────────
REVERSE_PROXY=false
for arg in "$@"; do
    case "$arg" in
        --reverse-proxy) REVERSE_PROXY=true ;;
        --help|-h)
            echo "Usage: sudo $0 [--reverse-proxy]"
            echo ""
            echo "  (no flag)        Standard install. AIO manages ports 80 and 443."
            echo "  --reverse-proxy  Behind an existing reverse proxy (nginx, Caddy,"
            echo "                   Traefik). AIO's Apache listens on APACHE_PORT"
            echo "                   (default 11000); your proxy forwards HTTPS to it."
            exit 0
            ;;
        *) die "Unknown argument: $arg  (try --help)" ;;
    esac
done

# ── Defaults (override via environment before running) ────────────────────────
: "${NC_DATA_DIR:=/mnt/ncdata}"
: "${NC_UPLOAD_LIMIT:=16G}"
: "${NC_MAX_TIME:=3600}"
: "${NC_MEMORY_LIMIT:=512M}"
: "${TALK_PORT:=3478}"
: "${AIO_IMAGE:=ghcr.io/nextcloud-releases/all-in-one:latest}"
: "${CONTAINER_NAME:=nextcloud-aio-mastercontainer}"
: "${VOLUME_NAME:=nextcloud_aio_mastercontainer}"
# Reverse-proxy mode only
: "${APACHE_PORT:=11000}"
: "${APACHE_IP_BINDING:=127.0.0.1}"   # 127.0.0.1 when proxy is on same host
: "${AIO_ADMIN_PORT:=8080}"           # remap if 8080 is already occupied

# ── Helpers ───────────────────────────────────────────────────────────────────
require_root() {
    [[ $EUID -eq 0 ]] || die "Run this script as root (sudo $0)"
}

check_arch() {
    local arch
    arch=$(uname -m)
    case "$arch" in
        x86_64|aarch64) ok "Architecture: $arch" ;;
        *) die "Unsupported architecture: $arch (need x86_64 or aarch64)" ;;
    esac
}

check_os() {
    if [[ "$(uname -s)" != "Linux" ]]; then
        die "This script targets Linux. macOS/Windows users: see the README for platform-specific commands."
    fi
    ok "OS: Linux"
}

install_docker() {
    info "Installing Docker via official convenience script..."
    curl -fsSL https://get.docker.com | sh
    systemctl enable --now docker
    ok "Docker installed and started."
}

check_docker() {
    if ! command -v docker &>/dev/null; then
        warn "Docker not found."
        read -rp "Install Docker now? [y/N] " yn
        [[ "${yn,,}" == "y" ]] || die "Docker is required. Install it from https://docs.docker.com/engine/install/ and re-run."
        install_docker
    fi

    # Reject snap Docker — it doesn't work with AIO
    if docker info 2>/dev/null | grep -q "/var/snap/docker/"; then
        die "Snap-based Docker is not supported. Remove it (snap remove docker) and install the official package."
    fi

    ok "Docker: $(docker --version)"
}

check_ports() {
    local ports blocked=()

    if [[ "$REVERSE_PROXY" == true ]]; then
        # AIO only needs the admin interface port; proxy owns 80/443
        ports=("$AIO_ADMIN_PORT" "$APACHE_PORT")
    else
        ports=(80 443 8080 8443)
    fi

    for p in "${ports[@]}"; do
        if ss -tlnp 2>/dev/null | grep -q ":${p} " || \
           netstat -tlnp 2>/dev/null | grep -q ":${p} "; then
            blocked+=("$p")
        fi
    done

    if [[ ${#blocked[@]} -gt 0 ]]; then
        warn "The following ports are already in use: ${blocked[*]}"
        if [[ "$REVERSE_PROXY" == true ]]; then
            warn "Ports ${AIO_ADMIN_PORT} (admin) and ${APACHE_PORT} (Apache) must be free."
            warn "Override if needed: AIO_ADMIN_PORT=8090 APACHE_PORT=11001 sudo $0 --reverse-proxy"
        else
            warn "Nextcloud AIO requires ports 80, 443, 8080, and 8443 to be free."
            warn "If you are using a reverse proxy, re-run with: sudo $0 --reverse-proxy"
        fi
        read -rp "Continue anyway? [y/N] " yn
        [[ "${yn,,}" == "y" ]] || exit 0
    else
        if [[ "$REVERSE_PROXY" == true ]]; then
            ok "Ports ${AIO_ADMIN_PORT} (admin) and ${APACHE_PORT} (Apache) are available."
        else
            ok "Required ports 80/443/8080/8443 are available."
        fi
    fi
}

check_existing_container() {
    if docker inspect "$CONTAINER_NAME" &>/dev/null; then
        warn "Container '$CONTAINER_NAME' already exists."
        echo "  Use 'sudo docker start $CONTAINER_NAME' to restart it."
        echo "  To reinstall, remove it first: sudo docker rm -f $CONTAINER_NAME"
        exit 0
    fi
}

confirm_config() {
    echo ""
    echo -e "${BOLD}── Configuration ─────────────────────────────────────────${RESET}"
    echo "  Mode            : $([ "$REVERSE_PROXY" == true ] && echo 'Reverse proxy' || echo 'Standard')"
    echo "  Data directory  : $NC_DATA_DIR"
    echo "  Upload limit    : $NC_UPLOAD_LIMIT"
    echo "  Max exec time   : ${NC_MAX_TIME}s"
    echo "  PHP memory      : $NC_MEMORY_LIMIT"
    echo "  TURN port       : $TALK_PORT"
    if [[ "$REVERSE_PROXY" == true ]]; then
        echo "  AIO admin port  : $AIO_ADMIN_PORT  (→ https://<host>:${AIO_ADMIN_PORT})"
        echo "  Apache port     : $APACHE_PORT"
        echo "  Apache binding  : $APACHE_IP_BINDING"
    fi
    echo "  Image           : $AIO_IMAGE"
    echo "  Container name  : $CONTAINER_NAME"
    echo -e "${BOLD}──────────────────────────────────────────────────────────${RESET}"
    echo ""
    read -rp "Proceed with these settings? [Y/n] " yn
    [[ "${yn,,}" != "n" ]] || die "Aborted."
}

ensure_data_dir() {
    if [[ ! -d "$NC_DATA_DIR" ]]; then
        info "Creating data directory: $NC_DATA_DIR"
        mkdir -p "$NC_DATA_DIR"
    fi
    ok "Data directory ready: $NC_DATA_DIR"
}

run_aio_standard() {
    docker run \
        --init \
        --sig-proxy=false \
        --name "$CONTAINER_NAME" \
        --restart always \
        --publish 80:80 \
        --publish 8080:8080 \
        --publish 8443:8443 \
        --env NEXTCLOUD_DATADIR="$NC_DATA_DIR" \
        --env NEXTCLOUD_UPLOAD_LIMIT="$NC_UPLOAD_LIMIT" \
        --env NEXTCLOUD_MAX_TIME="$NC_MAX_TIME" \
        --env NEXTCLOUD_MEMORY_LIMIT="$NC_MEMORY_LIMIT" \
        --env TALK_PORT="$TALK_PORT" \
        --volume "${VOLUME_NAME}:/mnt/docker-aio-config" \
        --volume /var/run/docker.sock:/var/run/docker.sock:ro \
        "$AIO_IMAGE"
}

run_aio_reverse_proxy() {
    docker run \
        --init \
        --sig-proxy=false \
        --name "$CONTAINER_NAME" \
        --restart always \
        --publish "${AIO_ADMIN_PORT}:8080" \
        --env APACHE_PORT="$APACHE_PORT" \
        --env APACHE_IP_BINDING="$APACHE_IP_BINDING" \
        --env SKIP_DOMAIN_VALIDATION=true \
        --env NEXTCLOUD_DATADIR="$NC_DATA_DIR" \
        --env NEXTCLOUD_UPLOAD_LIMIT="$NC_UPLOAD_LIMIT" \
        --env NEXTCLOUD_MAX_TIME="$NC_MAX_TIME" \
        --env NEXTCLOUD_MEMORY_LIMIT="$NC_MEMORY_LIMIT" \
        --env TALK_PORT="$TALK_PORT" \
        --volume "${VOLUME_NAME}:/mnt/docker-aio-config" \
        --volume /var/run/docker.sock:/var/run/docker.sock:ro \
        "$AIO_IMAGE"
}

run_aio() {
    info "Pulling image: $AIO_IMAGE"
    docker pull "$AIO_IMAGE"

    info "Starting Nextcloud AIO mastercontainer..."
    if [[ "$REVERSE_PROXY" == true ]]; then
        run_aio_reverse_proxy
    else
        run_aio_standard
    fi
}

post_install_standard() {
    local ip
    ip=$(hostname -I | awk '{print $1}')
    echo -e "  ${BOLD}AIO Admin Interface${RESET} (initial setup):"
    echo -e "    ${CYAN}https://${ip}:${AIO_ADMIN_PORT}${RESET}"
    echo ""
    echo "  Accept the self-signed certificate warning in your browser."
    echo ""
    echo -e "  ${BOLD}For a valid TLS cert (port 8443):${RESET}"
    echo "    1. Point a domain at this server's public IP."
    echo "    2. Forward ports 80 and 8443 to this machine."
    echo "    3. Access https://your-domain.tld:8443"
    echo ""
    echo -e "  ${BOLD}Required firewall ports:${RESET}"
    echo "    443/TCP   — Nextcloud HTTPS"
    echo "    443/UDP   — HTTP/3 (optional)"
    echo "    ${TALK_PORT}/TCP+UDP — Talk TURN server (if enabled)"
}

post_install_reverse_proxy() {
    local ip
    ip=$(hostname -I | awk '{print $1}')
    echo -e "  ${BOLD}AIO Admin Interface${RESET} (initial setup):"
    echo -e "    ${CYAN}https://${ip}:${AIO_ADMIN_PORT}${RESET}"
    echo ""
    echo "  Accept the self-signed certificate warning in your browser."
    echo ""
    echo -e "  ${BOLD}Nginx proxy block${RESET} (add to your server config):"
    cat << NGINX
    location / {
        proxy_pass http://${APACHE_IP_BINDING}:${APACHE_PORT};
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Port \$server_port;
        proxy_set_header X-Forwarded-Scheme \$scheme;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header Accept-Encoding "";
        proxy_set_header Host \$host;
        client_max_body_size ${NC_UPLOAD_LIMIT};
        proxy_read_timeout ${NC_MAX_TIME}s;
        proxy_send_timeout ${NC_MAX_TIME}s;
        proxy_connect_timeout 10s;
    }
NGINX
    echo ""
    echo -e "  ${BOLD}Required firewall ports:${RESET}"
    echo "    443/TCP   — Your reverse proxy (HTTPS)"
    echo "    443/UDP   — HTTP/3 (optional)"
    echo "    ${TALK_PORT}/TCP+UDP — Talk TURN server (if enabled)"
    echo ""
    echo -e "  ${BOLD}After entering your domain in the AIO interface:${RESET}"
    echo "    The domain validation check is skipped (SKIP_DOMAIN_VALIDATION=true)."
    echo "    Ensure your proxy is forwarding HTTPS → ${APACHE_IP_BINDING}:${APACHE_PORT} before starting containers."
}

post_install() {
    echo ""
    echo -e "${GREEN}${BOLD}── Nextcloud AIO is running ──────────────────────────────${RESET}"
    echo ""
    if [[ "$REVERSE_PROXY" == true ]]; then
        post_install_reverse_proxy
    else
        post_install_standard
    fi
    echo ""
    echo -e "  ${BOLD}Useful commands:${RESET}"
    echo "    sudo docker stop $CONTAINER_NAME"
    echo "    sudo docker start $CONTAINER_NAME"
    echo "    sudo docker logs -f $CONTAINER_NAME"
    echo ""
    echo -e "${GREEN}${BOLD}──────────────────────────────────────────────────────────${RESET}"
}

# ── Main ──────────────────────────────────────────────────────────────────────
echo -e "${BOLD}Nextcloud All-in-One — Installer${RESET}"
echo "https://github.com/nextcloud/all-in-one"
echo ""

require_root
check_os
check_arch
check_docker
check_ports
check_existing_container
confirm_config
ensure_data_dir
run_aio &
sleep 3          # give the container a moment to start

post_install
