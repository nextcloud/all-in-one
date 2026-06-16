#!/usr/bin/env bash
#===============================================================================
#  SCRIPT NAME  :  install-gitea-runner.sh
#  DESCRIPTION  :  Download, register, and configure a Gitea Act Runner as a
#                  systemd service. Optionally installs Docker for container jobs.
#  AUTHOR       :  Cory / CoreConduit
#  DATE         :  2026-06-16
#  VERSION      :  1.0.0
#  USAGE        :  sudo ./install-gitea-runner.sh [options]
#  DEPENDENCIES :  curl, jq
#  NOTES        :  Obtain a registration token from your Gitea instance at:
#                  Site Admin → Runners → Create Runner  (instance-wide)
#                  Org settings → Runners → Create Runner (org-scoped)
#                  Repo settings → Runners → Create Runner (repo-scoped)
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
readonly SCRIPT_VERSION="1.0.0"

readonly LOG_FILE="${SCRIPT_DIR}/${SCRIPT_NAME%.sh}.log"

VERBOSE=false
DRY_RUN=false

# Runner identity
RUNNER_USER="gitea-runner"
RUNNER_GROUP="gitea-runner"
RUNNER_HOME="/var/lib/gitea-runner"
RUNNER_CONFIG_DIR="/etc/gitea-runner"
RUNNER_BIN="/usr/local/bin/act_runner"
RUNNER_VERSION=""               # Empty = fetch latest from API
RUNNER_NAME="$(hostname -s)"    # Defaults to machine hostname
RUNNER_LABELS="ubuntu-latest,ubuntu-22.04,ubuntu-20.04"

# Gitea connection (prompted if empty at runtime)
GITEA_INSTANCE_URL=""           # e.g. https://git.example.com
GITEA_REG_TOKEN=""              # One-time registration token from Gitea UI

# Execution mode
DOCKER_ENABLED=true             # Use Docker for job containers (recommended)
DOCKER_INSTALL=false            # Install Docker if not present

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
    rm -f /tmp/act_runner-download 2>/dev/null || true
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
    Download the Gitea Act Runner binary, register it with a Gitea instance,
    and install it as a systemd service. Supports Docker and host-exec modes.

${BOLD}USAGE${NC}
    sudo ${SCRIPT_NAME} [OPTIONS]

${BOLD}OPTIONS${NC}
    -h, --help                Show this help message
    -v, --verbose             Enable verbose/debug output
    -V, --version             Print version and exit
    -n, --dry-run             Show what would be done without doing it
        --runner-version X    Pin to a specific act_runner version (e.g. 0.2.11)
        --instance URL        Gitea instance URL (prompted if omitted)
        --token TOKEN         Runner registration token (prompted if omitted)
        --name NAME           Runner display name (default: hostname = ${RUNNER_NAME})
        --labels LABELS       Comma-separated job labels (default: ${RUNNER_LABELS})
        --no-docker           Run jobs directly on host instead of Docker containers
        --install-docker      Install Docker Engine if not already present

${BOLD}EXAMPLES${NC}
    sudo ${SCRIPT_NAME} --instance https://git.example.com --token TOKEN123
    sudo ${SCRIPT_NAME} --instance http://localhost:3000  --token TOKEN123 --no-docker
    sudo ${SCRIPT_NAME} --instance https://git.example.com --token TOKEN123 --install-docker
    sudo ${SCRIPT_NAME} --dry-run
EOF
}

check_dependencies() {
    local deps=("curl" "jq")
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
        x86_64)        echo "linux-amd64" ;;
        aarch64|arm64) echo "linux-arm64" ;;
        armv7l)        echo "linux-arm-7" ;;
        *)             die "Unsupported architecture: $arch" ;;
    esac
}

fetch_latest_runner_version() {
    local ver
    ver="$(curl -fsSL "https://api.github.com/repos/go-gitea/act_runner/releases/latest" \
        | jq -r '.tag_name' | sed 's/^v//')"
    [[ -n "$ver" && "$ver" != "null" ]] || die "Could not fetch latest act_runner version"
    echo "$ver"
}

prompt_if_empty() {
    local var_name="$1"
    local prompt_text="$2"
    local secret="${3:-false}"

    if [[ -z "${!var_name}" ]]; then
        if $DRY_RUN; then
            info "[DRY RUN] Would prompt for ${var_name}"
            printf -v "$var_name" "DRY_RUN_PLACEHOLDER"
            return
        fi
        if $secret; then
            read -rsp "$(echo -e "${YELLOW}${prompt_text}:${NC} ")" "${var_name?}"
            echo
        else
            read -rp "$(echo -e "${YELLOW}${prompt_text}:${NC} ")" "${var_name?}"
        fi
        [[ -n "${!var_name}" ]] || die "${var_name} is required"
    fi
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
            --runner-version)
                [[ -z "${2:-}" ]] && die "$1 requires a value"
                RUNNER_VERSION="$2"; shift 2 ;;
            --instance)
                [[ -z "${2:-}" ]] && die "$1 requires a value"
                GITEA_INSTANCE_URL="${2%/}"; shift 2 ;;   # strip trailing slash
            --token)
                [[ -z "${2:-}" ]] && die "$1 requires a value"
                GITEA_REG_TOKEN="$2"; shift 2 ;;
            --name)
                [[ -z "${2:-}" ]] && die "$1 requires a value"
                RUNNER_NAME="$2"; shift 2 ;;
            --labels)
                [[ -z "${2:-}" ]] && die "$1 requires a value"
                RUNNER_LABELS="$2"; shift 2 ;;
            --no-docker)      DOCKER_ENABLED=false; shift ;;
            --install-docker) DOCKER_INSTALL=true; shift ;;
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
    header "Resolving act_runner version"
    if [[ -z "$RUNNER_VERSION" ]]; then
        info "Fetching latest release from GitHub..."
        RUNNER_VERSION="$(fetch_latest_runner_version)"
    fi
    info "Target version: ${BOLD}${RUNNER_VERSION}${NC}"
}

step_check_docker() {
    header "Checking Docker"
    if ! $DOCKER_ENABLED; then
        warn "Docker disabled — jobs will run directly on the host (less isolated)"
        return
    fi

    if command -v docker &>/dev/null; then
        local docker_ver
        docker_ver="$(docker --version | awk '{print $3}' | tr -d ',')"
        info "Docker ${docker_ver} found"
        return
    fi

    if $DOCKER_INSTALL; then
        info "Installing Docker Engine..."
        if $DRY_RUN; then
            info "[DRY RUN] Would install Docker via get.docker.com convenience script"
            return
        fi
        curl -fsSL https://get.docker.com | sh
        systemctl enable docker
        systemctl start docker
        info "Docker installed"
    else
        warn "Docker not found. Jobs requiring containers will fail."
        warn "Re-run with --install-docker to install it, or use --no-docker for host execution."
    fi
}

step_download_binary() {
    header "Downloading act_runner binary"
    local arch
    arch="$(detect_arch)"
    local url="https://dl.gitea.com/act_runner/${RUNNER_VERSION}/act_runner-${RUNNER_VERSION}-${arch}"

    info "Architecture : ${arch}"
    info "Download URL : ${url}"

    if $DRY_RUN; then
        info "[DRY RUN] Would download ${url} → ${RUNNER_BIN}"
        return
    fi

    curl -fsSL "$url" -o /tmp/act_runner-download &
    spinner $! "Downloading act_runner-${RUNNER_VERSION}-${arch}..."
    wait $!

    install -o root -g root -m 0755 /tmp/act_runner-download "$RUNNER_BIN"
    rm -f /tmp/act_runner-download

    local installed_ver
    installed_ver="$("$RUNNER_BIN" --version 2>&1 | awk '{print $3}' || true)"
    info "Binary installed to ${RUNNER_BIN} (${installed_ver})"
}

step_create_user() {
    header "Creating system user"
    if id "$RUNNER_USER" &>/dev/null; then
        info "User '${RUNNER_USER}' already exists — skipping"
    else
        run_cmd groupadd --system "$RUNNER_GROUP"
        run_cmd useradd \
            --system \
            --gid "$RUNNER_GROUP" \
            --home-dir "$RUNNER_HOME" \
            --shell /bin/bash \
            --comment "Gitea Act Runner service account" \
            "$RUNNER_USER"
        info "Created system user '${RUNNER_USER}'"
    fi

    # Add runner user to docker group so it can launch containers
    if $DOCKER_ENABLED && getent group docker &>/dev/null; then
        run_cmd usermod -aG docker "$RUNNER_USER"
        info "Added ${RUNNER_USER} to docker group"
    fi
}

step_create_directories() {
    header "Creating directory structure"
    local dirs=("$RUNNER_HOME" "$RUNNER_CONFIG_DIR")
    for dir in "${dirs[@]}"; do
        run_cmd mkdir -p "$dir"
        debug "Created ${dir}"
    done
    run_cmd chown -R "${RUNNER_USER}:${RUNNER_GROUP}" "$RUNNER_HOME"
    run_cmd chown -R "root:${RUNNER_GROUP}" "$RUNNER_CONFIG_DIR"
    run_cmd chmod 0750 "$RUNNER_CONFIG_DIR"
    info "Directories configured"
}

step_generate_config() {
    header "Generating runner config"
    local config_file="${RUNNER_CONFIG_DIR}/config.yml"

    if [[ -f "$config_file" ]] && ! $DRY_RUN; then
        info "Config already exists at ${config_file} — skipping generation"
        return
    fi

    if $DRY_RUN; then
        info "[DRY RUN] Would generate ${config_file} via act_runner generate-config"
        return
    fi

    "$RUNNER_BIN" generate-config > "$config_file"
    chown "root:${RUNNER_GROUP}" "$config_file"
    chmod 0640 "$config_file"

    # Patch key settings in the generated config
    if ! $DOCKER_ENABLED; then
        # Host execution: disable Docker-based job containers
        sed -i 's/^  # valid_volumes:/  valid_volumes:/' "$config_file" || true
    fi

    # Point runner's working/cache dir at RUNNER_HOME
    sed -i "s|^  work_dir:.*|  work_dir: ${RUNNER_HOME}/.work|" "$config_file" || true

    info "Config written to ${config_file}"
}

step_register_runner() {
    header "Registering runner with Gitea"

    local state_file="${RUNNER_HOME}/.runner"
    if [[ -f "$state_file" ]]; then
        info "Runner already registered (${state_file} exists) — skipping"
        info "To re-register: sudo rm ${state_file} and re-run this script"
        return
    fi

    prompt_if_empty GITEA_INSTANCE_URL "Gitea instance URL (e.g. https://git.example.com)"
    prompt_if_empty GITEA_REG_TOKEN    "Runner registration token (from Gitea Admin → Runners)" true

    if $DRY_RUN; then
        info "[DRY RUN] Would register runner '${RUNNER_NAME}' with ${GITEA_INSTANCE_URL}"
        return
    fi

    # Registration writes the .runner credential file into the working directory,
    # so we run it as the runner user from RUNNER_HOME.
    sudo -u "$RUNNER_USER" "$RUNNER_BIN" register \
        --config "${RUNNER_CONFIG_DIR}/config.yml" \
        --instance "$GITEA_INSTANCE_URL" \
        --token    "$GITEA_REG_TOKEN" \
        --name     "$RUNNER_NAME" \
        --labels   "$RUNNER_LABELS" \
        --no-interactive

    # The .runner file lands in $PWD of the process; move it to RUNNER_HOME if needed
    if [[ -f ".runner" && ! -f "${RUNNER_HOME}/.runner" ]]; then
        mv ".runner" "${RUNNER_HOME}/.runner"
        chown "${RUNNER_USER}:${RUNNER_GROUP}" "${RUNNER_HOME}/.runner"
        chmod 0600 "${RUNNER_HOME}/.runner"
    fi

    info "Runner '${RUNNER_NAME}' registered successfully"
}

step_write_systemd_unit() {
    header "Installing systemd service"
    local unit_file="/etc/systemd/system/gitea-runner.service"

    if $DRY_RUN; then
        info "[DRY RUN] Would write ${unit_file}"
        return
    fi

    cat > "$unit_file" <<EOF
[Unit]
Description=Gitea Act Runner
Documentation=https://gitea.com/gitea/act_runner
After=network.target gitea.service
Wants=network.target

[Service]
Type=simple
User=${RUNNER_USER}
Group=${RUNNER_GROUP}
WorkingDirectory=${RUNNER_HOME}
ExecStart=${RUNNER_BIN} daemon --config ${RUNNER_CONFIG_DIR}/config.yml
Restart=always
RestartSec=5
Environment=HOME=${RUNNER_HOME}

# Raise open-file limit for concurrent builds
LimitNOFILE=65536

[Install]
WantedBy=multi-user.target
EOF

    systemctl daemon-reload
    systemctl enable gitea-runner
    info "Service enabled"
}

step_start_service() {
    header "Starting gitea-runner"
    if $DRY_RUN; then
        info "[DRY RUN] Would start gitea-runner.service"
        return
    fi

    systemctl start gitea-runner
    sleep 2
    if systemctl is-active --quiet gitea-runner; then
        info "gitea-runner is ${GREEN}running${NC}"
    else
        warn "Service did not start cleanly — check: journalctl -u gitea-runner -n 50"
    fi
}

print_summary() {
    header "Installation complete"

    local exec_mode="Docker containers"
    $DOCKER_ENABLED || exec_mode="host (no Docker)"

    cat <<EOF

  ${BOLD}Gitea Act Runner ${RUNNER_VERSION}${NC} installed.

  ${BOLD}Instance${NC}     ${GITEA_INSTANCE_URL:-"(set at registration)"}
  ${BOLD}Runner name${NC}  ${RUNNER_NAME}
  ${BOLD}Labels${NC}       ${RUNNER_LABELS}
  ${BOLD}Exec mode${NC}    ${exec_mode}
  ${BOLD}Config${NC}       ${RUNNER_CONFIG_DIR}/config.yml
  ${BOLD}State${NC}        ${RUNNER_HOME}/.runner
  ${BOLD}Work dir${NC}     ${RUNNER_HOME}/.work/

  ${YELLOW}Next steps:${NC}
    1. Verify the runner appears online in Gitea:
         ${GITEA_INSTANCE_URL:-<your-gitea-url>}/-/admin/runners
    2. Add a .gitea/workflows/ci.yml to a repo to trigger a job.
    3. To add more runners on other machines, re-run this script
       with a new --name and a fresh --token.

  ${BOLD}Service commands:${NC}
    sudo systemctl status gitea-runner
    sudo systemctl restart gitea-runner
    sudo journalctl -u gitea-runner -f

  ${BOLD}Example workflow (${BOLD}.gitea/workflows/ci.yml${NC}):${NC}
    on: [push]
    jobs:
      test:
        runs-on: ubuntu-latest
        steps:
          - uses: actions/checkout@v4
          - run: echo "Hello from Gitea CI"

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
    step_check_docker
    step_download_binary
    step_create_user
    step_create_directories
    step_generate_config
    step_register_runner
    step_write_systemd_unit
    step_start_service
    print_summary
}

main "$@"
