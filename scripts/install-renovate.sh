#!/usr/bin/env bash
#===============================================================================
#  SCRIPT NAME  :  install-renovate.sh
#  DESCRIPTION  :  Install Renovate bot as a systemd timer+service pair against
#                  a self-hosted Gitea instance.
#  AUTHOR       :  Cory / CoreConduit
#  DATE         :  2026-06-17
#  VERSION      :  1.0.0
#  USAGE        :  sudo ./install-renovate.sh [options]
#  DEPENDENCIES :  node (>=18), npm
#  NOTES        :  Create a Gitea token at:
#                  Gitea → User Settings → Applications → Generate Token
#                  Required scopes: issue, repository, user (read/write)
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

# Renovate install config
RENOVATE_USER="renovate"
RENOVATE_GROUP="renovate"
RENOVATE_HOME="/var/lib/renovate"
RENOVATE_CONFIG_DIR="/etc/renovate"

# Gitea connection (prompted if empty)
GITEA_ENDPOINT=""          # e.g. https://git.example.com
GITEA_TOKEN=""             # Gitea personal access token
RENOVATE_GIT_AUTHOR="Renovate Bot <renovate@example.com>"

# Repo targeting
AUTODISCOVER=true          # Scan all repos the token can access
REPOSITORIES=""            # Comma-separated list (overrides autodiscover)

# Timer schedule — how often to run a Renovate cycle
# Renovate itself respects the per-repo schedule in renovate.json;
# this controls the polling interval of the service.
TIMER_SCHEDULE="0/4:00"    # Every 4 hours (systemd OnCalendar format)

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
debug()  { if $VERBOSE; then log "${CYAN}[DEBUG]${NC} $*"; fi; }
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
    Install Renovate as a systemd timer+service that runs against a
    self-hosted Gitea instance. The service runs one Renovate cycle per
    timer tick; Renovate's own schedule config governs when PRs open.

${BOLD}USAGE${NC}
    sudo ${SCRIPT_NAME} [OPTIONS]

${BOLD}OPTIONS${NC}
    -h, --help              Show this help message
    -v, --verbose           Enable verbose/debug output
    -V, --version           Print version and exit
    -n, --dry-run           Show what would be done without doing it
        --endpoint URL      Gitea instance URL (prompted if omitted)
        --token TOKEN       Gitea personal access token (prompted if omitted)
        --git-author STR    Git author for Renovate commits
                            (default: "${RENOVATE_GIT_AUTHOR}")
        --repos REPOS       Comma-separated repo list: org/repo,org/repo2
                            (default: autodiscover all accessible repos)
        --schedule SCHED    Systemd OnCalendar schedule (default: ${TIMER_SCHEDULE})

${BOLD}EXAMPLES${NC}
    sudo ${SCRIPT_NAME} --endpoint https://git.example.com --token TOKEN
    sudo ${SCRIPT_NAME} --endpoint https://git.example.com --token TOKEN \\
         --repos "myorg/myrepo,myorg/other"
    sudo ${SCRIPT_NAME} --dry-run
EOF
}

check_dependencies() {
    local deps=("node" "npm")
    local missing=()
    for cmd in "${deps[@]}"; do
        command -v "$cmd" &>/dev/null || missing+=("$cmd")
    done
    [[ ${#missing[@]} -gt 0 ]] && die "Missing dependencies: ${missing[*]}"

    local node_major
    node_major="$(node --version | sed 's/v\([0-9]*\).*/\1/')"
    [[ "$node_major" -ge 18 ]] || die "Node.js >=18 required (found v${node_major})"
    debug "Node $(node --version), npm $(npm --version)"
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

prompt_if_empty() {
    local var_name="$1"
    local prompt_text="$2"
    local secret="${3:-false}"

    if [[ -z "${!var_name}" ]]; then
        if $DRY_RUN; then
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
            -h|--help)        usage; exit 0 ;;
            -v|--verbose)     VERBOSE=true; shift ;;
            -V|--version)     echo "${SCRIPT_NAME} v${SCRIPT_VERSION}"; exit 0 ;;
            -n|--dry-run)     DRY_RUN=true; shift ;;
            --endpoint)
                [[ -z "${2:-}" ]] && die "$1 requires a value"
                GITEA_ENDPOINT="${2%/}"; shift 2 ;;
            --token)
                [[ -z "${2:-}" ]] && die "$1 requires a value"
                GITEA_TOKEN="$2"; shift 2 ;;
            --git-author)
                [[ -z "${2:-}" ]] && die "$1 requires a value"
                RENOVATE_GIT_AUTHOR="$2"; shift 2 ;;
            --repos)
                [[ -z "${2:-}" ]] && die "$1 requires a value"
                REPOSITORIES="$2"
                AUTODISCOVER=false
                shift 2 ;;
            --schedule)
                [[ -z "${2:-}" ]] && die "$1 requires a value"
                TIMER_SCHEDULE="$2"; shift 2 ;;
            --)  shift; break ;;
            -*)  die "Unknown option: $1 (see --help)" ;;
            *)   break ;;
        esac
    done
}

#---------------------------------------
#  INSTALL STEPS
#---------------------------------------
step_install_renovate() {
    header "Installing Renovate"

    local current_ver=""
    if command -v renovate &>/dev/null; then
        current_ver="$(renovate --version 2>/dev/null || true)"
        info "Renovate ${current_ver} already installed — upgrading..."
    fi

    if $DRY_RUN; then
        info "[DRY RUN] Would: npm install -g renovate"
        return
    fi

    npm install -g renovate
    local installed_ver
    installed_ver="$(renovate --version 2>/dev/null || true)"
    info "Renovate ${installed_ver} installed"
}

step_create_user() {
    header "Creating system user"
    if id "$RENOVATE_USER" &>/dev/null; then
        info "User '${RENOVATE_USER}' already exists — skipping"
        return
    fi
    run_cmd groupadd --system "$RENOVATE_GROUP"
    run_cmd useradd \
        --system \
        --gid "$RENOVATE_GROUP" \
        --home-dir "$RENOVATE_HOME" \
        --shell /sbin/nologin \
        --comment "Renovate bot service account" \
        "$RENOVATE_USER"
    info "Created system user '${RENOVATE_USER}'"
}

step_create_directories() {
    header "Creating directory structure"
    run_cmd mkdir -p "$RENOVATE_HOME" "$RENOVATE_CONFIG_DIR"
    run_cmd chown -R "${RENOVATE_USER}:${RENOVATE_GROUP}" "$RENOVATE_HOME"
    run_cmd chown -R "root:${RENOVATE_GROUP}" "$RENOVATE_CONFIG_DIR"
    run_cmd chmod 0750 "$RENOVATE_CONFIG_DIR"
    info "Directories configured"
}

step_write_env_file() {
    header "Writing environment file"

    prompt_if_empty GITEA_ENDPOINT "Gitea instance URL (e.g. https://git.example.com)"
    prompt_if_empty GITEA_TOKEN    "Gitea personal access token" true

    local env_file="${RENOVATE_CONFIG_DIR}/env"

    if [[ -f "$env_file" ]] && ! $DRY_RUN; then
        warn "Env file already exists at ${env_file} — overwriting"
    fi

    if $DRY_RUN; then
        info "[DRY RUN] Would write ${env_file} (token redacted)"
        return
    fi

    # Secrets file — tight permissions before writing
    install -o root -g "$RENOVATE_GROUP" -m 0640 /dev/null "$env_file"
    cat > "$env_file" <<EOF
RENOVATE_TOKEN=${GITEA_TOKEN}
LOG_LEVEL=info
EOF
    info "Env file written to ${env_file}"
}

step_write_config() {
    header "Writing Renovate config"
    local config_file="${RENOVATE_CONFIG_DIR}/config.js"

    if [[ -f "$config_file" ]] && ! $DRY_RUN; then
        warn "Config already exists at ${config_file} — skipping (delete to regenerate)"
        return
    fi

    if $DRY_RUN; then
        info "[DRY RUN] Would write ${config_file}"
        return
    fi

    # Build the repositories list for non-autodiscover mode
    local repos_block=""
    if ! $AUTODISCOVER && [[ -n "$REPOSITORIES" ]]; then
        local repos_json
        repos_json="$(echo "$REPOSITORIES" | tr ',' '\n' | sed 's/^[[:space:]]*//' | \
            awk '{printf "  \"%s\",\n", $0}' | sed '$ s/,$//')"
        repos_block="
  repositories: [
${repos_json}
  ],"
    fi

    cat > "$config_file" <<EOF
module.exports = {
  platform: 'gitea',
  endpoint: '${GITEA_ENDPOINT}',
  gitAuthor: '${RENOVATE_GIT_AUTHOR}',
  autodiscover: ${AUTODISCOVER},${repos_block}

  // Onboarding creates a renovate.json PR for repos that don't have one yet
  onboarding: true,
  onboardingConfig: {
    extends: ['config:recommended'],
  },

  // Persist Renovate's repo cache between runs for faster cycles
  repositoryCache: 'enabled',
  cacheDir: '${RENOVATE_HOME}/cache',

  // Use the repo's renovate.json schedule; don't override it here
  requireConfig: 'optional',
};
EOF

    chown "root:${RENOVATE_GROUP}" "$config_file"
    chmod 0640 "$config_file"
    info "Config written to ${config_file}"
}

step_write_systemd_units() {
    header "Installing systemd units"

    local service_file="/etc/systemd/system/renovate.service"
    local timer_file="/etc/systemd/system/renovate.timer"

    local renovate_bin
    renovate_bin="$(command -v renovate 2>/dev/null || echo "/usr/local/bin/renovate")"

    # Derive the node prefix so the service can find npm globals
    local node_path
    node_path="$(dirname "$(command -v node)")"

    if $DRY_RUN; then
        info "[DRY RUN] Would write ${service_file} and ${timer_file}"
        return
    fi

    cat > "$service_file" <<EOF
[Unit]
Description=Renovate bot (one cycle)
Documentation=https://docs.renovatebot.com
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
User=${RENOVATE_USER}
Group=${RENOVATE_GROUP}
WorkingDirectory=${RENOVATE_HOME}

EnvironmentFile=${RENOVATE_CONFIG_DIR}/env
Environment=PATH=${node_path}:/usr/local/bin:/usr/bin:/bin
Environment=RENOVATE_CONFIG_FILE=${RENOVATE_CONFIG_DIR}/config.js
Environment=HOME=${RENOVATE_HOME}

ExecStart=${renovate_bin}

# Allow up to 1 hour for a full cycle across many repos
TimeoutStartSec=3600

StandardOutput=journal
StandardError=journal
SyslogIdentifier=renovate

[Install]
WantedBy=multi-user.target
EOF

    cat > "$timer_file" <<EOF
[Unit]
Description=Renovate bot timer
Documentation=https://docs.renovatebot.com

[Timer]
# Run on this schedule; also fire once shortly after boot in case
# a cycle was missed while the machine was off.
OnCalendar=${TIMER_SCHEDULE}
OnBootSec=5min
Persistent=true
Unit=renovate.service

[Install]
WantedBy=timers.target
EOF

    systemctl daemon-reload
    # Enable the timer, not the service (service is triggered by the timer)
    systemctl enable renovate.timer
    info "Timer enabled (${TIMER_SCHEDULE})"
}

step_start_timer() {
    header "Starting timer"
    if $DRY_RUN; then
        info "[DRY RUN] Would start renovate.timer"
        return
    fi

    systemctl start renovate.timer

    if systemctl is-active --quiet renovate.timer; then
        local next_run
        next_run="$(systemctl show renovate.timer --property=NextElapseUSecRealtime \
            | cut -d= -f2 | xargs -I{} date -d @{} '+%Y-%m-%d %H:%M %Z' 2>/dev/null || echo "unknown")"
        info "Timer active — next run: ${next_run}"
    else
        warn "Timer did not start — check: journalctl -u renovate.timer"
    fi
}

print_summary() {
    header "Installation complete"
    cat <<EOF

  ${BOLD}Renovate${NC} installed and scheduled.

  ${BOLD}Platform${NC}     Gitea
  ${BOLD}Endpoint${NC}     ${GITEA_ENDPOINT:-"(set at prompt)"}
  ${BOLD}Autodiscover${NC} ${AUTODISCOVER}
  ${BOLD}Schedule${NC}     ${TIMER_SCHEDULE} (systemd OnCalendar)
  ${BOLD}Config${NC}       ${RENOVATE_CONFIG_DIR}/config.js
  ${BOLD}Env / token${NC}  ${RENOVATE_CONFIG_DIR}/env  (mode 0640, root:renovate)
  ${BOLD}Cache${NC}        ${RENOVATE_HOME}/cache/

  ${YELLOW}Next steps:${NC}
    1. Check the token has the right Gitea scopes:
         issue, repository, user (read + write)
    2. Trigger a manual run to confirm connectivity:
         sudo systemctl start renovate.service
         sudo journalctl -u renovate -f
    3. Watch for an onboarding PR in each repo that lacks renovate.json.
    4. To update Renovate itself:
         sudo npm install -g renovate

  ${BOLD}Useful commands:${NC}
    sudo systemctl status renovate.timer
    sudo systemctl list-timers renovate.timer
    sudo journalctl -u renovate -n 100
    sudo journalctl -u renovate --since today

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

    step_install_renovate
    step_create_user
    step_create_directories
    step_write_env_file
    step_write_config
    step_write_systemd_units
    step_start_timer
    print_summary
}

main "$@"
