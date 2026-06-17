#!/usr/bin/env bash
#===============================================================================
#  SCRIPT NAME  :  sync-github-mirrors.sh
#  DESCRIPTION  :  Mirror all GitHub repos from a given account into a
#                  self-hosted Gitea instance. Safe to re-run; repos already
#                  present in Gitea are skipped. Designed to run as a systemd
#                  timer so new GitHub repos appear in Gitea automatically.
#  AUTHOR       :  Cory / CoreConduit
#  DATE         :  2026-06-17
#  VERSION      :  1.0.0
#  USAGE        :  sync-github-mirrors.sh [options]
#                  (no sudo needed — reads credentials from env file)
#  DEPENDENCIES :  curl, jq
#  ENV FILE     :  /etc/gitea-mirrors/env  (created by --install)
#                  GITHUB_TOKEN=ghp_...
#                  GITEA_TOKEN=...
#                  GITHUB_USER=bitsandbots
#                  GITEA_URL=http://localhost:3000
#                  GITEA_OWNER=coreconduit
#                  MIRROR_INTERVAL=8h0m0s
#===============================================================================

set -euo pipefail
IFS=$'\n\t'

#---------------------------------------
#  CONSTANTS & DEFAULTS
#---------------------------------------
SCRIPT_NAME="$(basename "$0")"
readonly SCRIPT_NAME
readonly SCRIPT_VERSION="1.0.0"

readonly LOG_FILE="/var/log/gitea-mirrors.log"
readonly ENV_FILE="/etc/gitea-mirrors/env"

VERBOSE=false
DRY_RUN=false
DO_INSTALL=false

# Runtime values (populated from ENV_FILE or flags)
GITHUB_TOKEN=""
GITEA_TOKEN=""
GITHUB_USER=""
GITEA_URL="http://localhost:3000"
GITEA_OWNER=""
MIRROR_INTERVAL="8h0m0s"

#---------------------------------------
#  COLORS
#---------------------------------------
if [[ -t 1 ]]; then
    RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
    BLUE='\033[0;34m'; CYAN='\033[0;36m'; NC='\033[0m'; BOLD='\033[1m'
else
    RED=''; GREEN=''; YELLOW=''; BLUE=''; CYAN=''; NC=''; BOLD=''
fi

#---------------------------------------
#  LOGGING
#---------------------------------------
log() {
    local msg
    msg="$(date '+%Y-%m-%d %H:%M:%S') $*"
    echo -e "$msg"
    { echo -e "$msg" >> "$LOG_FILE"; } 2>/dev/null || true
}
info()   { log "${BLUE}[INFO]${NC}  $*"; }
ok()     { log "${GREEN}[OK]${NC}    $*"; }
warn()   { log "${YELLOW}[WARN]${NC}  $*"; }
error()  { log "${RED}[ERROR]${NC} $*" >&2; }
debug()  { if $VERBOSE; then log "${CYAN}[DEBUG]${NC} $*"; fi; }
header() { echo -e "\n${BOLD}${BLUE}:: $* ::${NC}"; }
die()    { error "$1"; exit "${2:-1}"; }
dryrun() { log "${YELLOW}[DRY-RUN]${NC} $*"; }

#---------------------------------------
#  USAGE
#---------------------------------------
usage() {
    cat <<EOF
${BOLD}${SCRIPT_NAME} v${SCRIPT_VERSION}${NC} — Sync GitHub repos as Gitea pull-mirrors

${BOLD}USAGE${NC}
    ${SCRIPT_NAME} [options]

${BOLD}OPTIONS${NC}
    --install           Create env file + systemd timer and exit
    --github-user USER  GitHub account to mirror (default: from env file)
    --github-token TOK  GitHub PAT (default: from env file or gh CLI)
    --gitea-url URL     Gitea base URL (default: ${GITEA_URL})
    --gitea-token TOK   Gitea API token (default: from env file)
    --gitea-owner USER  Gitea owner for mirrored repos (default: from env file)
    --interval INTERVAL Mirror sync interval, e.g. 8h0m0s (default: ${MIRROR_INTERVAL})
    --dry-run           Show what would be mirrored without creating anything
    -v, --verbose       Verbose output
    -h, --help          Show this help

${BOLD}EXAMPLES${NC}
    # First-time setup (creates env file + systemd timer)
    sudo ${SCRIPT_NAME} --install \\
        --github-user bitsandbots \\
        --github-token ghp_xxx \\
        --gitea-token gitea_xxx \\
        --gitea-owner coreconduit

    # Manual sync run
    ${SCRIPT_NAME}

    # Check what would be added without making changes
    ${SCRIPT_NAME} --dry-run

EOF
    exit 0
}

#---------------------------------------
#  ARGUMENT PARSING
#---------------------------------------
parse_args() {
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --install)         DO_INSTALL=true;          shift ;;
            --github-user)     GITHUB_USER="$2";         shift 2 ;;
            --github-token)    GITHUB_TOKEN="$2";        shift 2 ;;
            --gitea-url)       GITEA_URL="${2%/}";       shift 2 ;;
            --gitea-token)     GITEA_TOKEN="$2";         shift 2 ;;
            --gitea-owner)     GITEA_OWNER="$2";         shift 2 ;;
            --interval)        MIRROR_INTERVAL="$2";     shift 2 ;;
            --dry-run)         DRY_RUN=true;             shift ;;
            -v|--verbose)      VERBOSE=true;             shift ;;
            -h|--help)         usage ;;
            --) shift; break ;;
            -*) die "Unknown option: $1 (see --help)" ;;
            *)  break ;;
        esac
    done
}

#---------------------------------------
#  LOAD ENV FILE
#---------------------------------------
load_env() {
    [[ -f "$ENV_FILE" ]] || return 0
    # shellcheck source=/dev/null
    source "$ENV_FILE"
}

#---------------------------------------
#  RESOLVE CREDENTIALS
#---------------------------------------
resolve_credentials() {
    # GitHub token: flag > env file > gh CLI
    if [[ -z "$GITHUB_TOKEN" ]]; then
        GITHUB_TOKEN=$(gh auth token 2>/dev/null || true)
    fi
    [[ -n "$GITHUB_TOKEN" ]] || die "GitHub token not found. Pass --github-token or run 'gh auth login'."

    [[ -n "$GITEA_TOKEN" ]]  || die "Gitea token not set. Pass --gitea-token or add GITEA_TOKEN to ${ENV_FILE}."
    [[ -n "$GITHUB_USER" ]]  || die "GitHub user not set. Pass --github-user or add GITHUB_USER to ${ENV_FILE}."
    [[ -n "$GITEA_OWNER" ]]  || die "Gitea owner not set. Pass --gitea-owner or add GITEA_OWNER to ${ENV_FILE}."

    debug "GitHub user : $GITHUB_USER"
    debug "Gitea owner : $GITEA_OWNER"
    debug "Gitea URL   : $GITEA_URL"
}

#---------------------------------------
#  GITHUB HELPERS
#---------------------------------------

# Fetch all repos for a GitHub user (handles pagination).
# Emits one compact JSON object per line.
fetch_github_repos() {
    local page=1 n count=0
    while true; do
        local batch
        batch=$(curl -sf \
            -H "Authorization: token ${GITHUB_TOKEN}" \
            -H "Accept: application/vnd.github+json" \
            "https://api.github.com/users/${GITHUB_USER}/repos?per_page=100&page=${page}&type=owner" \
            2>/dev/null) || die "GitHub API request failed (page ${page})."

        n=$(echo "$batch" | jq 'length')
        [[ "$n" -eq 0 ]] && break

        echo "$batch" | jq -c '.[]'
        count=$(( count + n ))
        (( page++ )) || true
    done
    debug "Fetched ${count} repos from GitHub"
}

#---------------------------------------
#  GITEA HELPERS
#---------------------------------------
gitea_api() {
    local method="$1" path="$2" data="${3:-}"
    local args=(-sf -X "$method" \
        -H "Authorization: token ${GITEA_TOKEN}" \
        -H "Content-Type: application/json")

    [[ -n "$data" ]] && args+=(-d "$data")
    curl "${args[@]}" "${GITEA_URL}/api/v1${path}"
}

# Return all repo names already in Gitea for the owner
fetch_gitea_repos() {
    local page=1 batch
    while true; do
        batch=$(gitea_api GET \
            "/repos/search?limit=50&page=${page}&uid=$(gitea_api GET /users/${GITEA_OWNER} | jq -r '.id')" \
            2>/dev/null | jq -r '.data[].name' 2>/dev/null || true)
        [[ -z "$batch" ]] && break
        echo "$batch"
        (( page++ ))
    done
}

#---------------------------------------
#  MIRROR A SINGLE REPO
#---------------------------------------
mirror_repo() {
    local name="$1" is_private="$2" description="$3"

    local payload
    payload=$(jq -n \
        --arg clone    "https://github.com/${GITHUB_USER}/${name}" \
        --arg auth     "$GITHUB_TOKEN" \
        --arg owner    "$GITEA_OWNER" \
        --arg rname    "$name" \
        --arg desc     "$description" \
        --argjson priv "$is_private" \
        --arg intv     "$MIRROR_INTERVAL" \
        '{
            clone_addr:      $clone,
            auth_token:      $auth,
            repo_owner:      $owner,
            repo_name:       $rname,
            description:     $desc,
            mirror:          true,
            mirror_interval: $intv,
            private:         $priv,
            service:         "github",
            wiki:            false,
            issues:          false,
            pull_requests:   false,
            releases:        false,
            lfs:             false
        }')

    local result http_name
    result=$(gitea_api POST /repos/migrate "$payload" 2>/dev/null)
    http_name=$(echo "$result" | jq -r '.full_name // empty')

    if [[ -n "$http_name" ]]; then
        ok "Mirrored: ${name} → ${http_name}"
        return 0
    else
        local msg
        msg=$(echo "$result" | jq -r '.message // "unknown error"')
        warn "Failed to mirror ${name}: ${msg}"
        return 1
    fi
}

#---------------------------------------
#  SYNC LOOP
#---------------------------------------
run_sync() {
    header "GitHub → Gitea mirror sync"
    info "GitHub user : ${GITHUB_USER}"
    info "Gitea owner : ${GITEA_OWNER} @ ${GITEA_URL}"

    # Build set of repos already in Gitea
    info "Fetching existing Gitea repos..."
    declare -A existing
    while IFS= read -r name; do
        [[ -n "$name" ]] && existing["$name"]=1
    done < <(fetch_gitea_repos)
    info "Found ${#existing[@]} repos already in Gitea"

    # Fetch GitHub repos and mirror any that are missing
    info "Fetching GitHub repos..."
    local added=0 skipped=0 failed=0

    while IFS= read -r repo_json; do
        local name is_private desc
        name=$(echo "$repo_json" | jq -r '.name')
        is_private=$(echo "$repo_json" | jq -r '.private')
        desc=$(echo "$repo_json" | jq -r '.description // ""')

        if [[ -n "${existing[$name]+_}" ]]; then
            debug "Skip: ${name} (already mirrored)"
            (( skipped++ )) || true
            continue
        fi

        if $DRY_RUN; then
            dryrun "Would mirror: ${name} (private=${is_private})"
            (( added++ )) || true
            continue
        fi

        if mirror_repo "$name" "$is_private" "$desc"; then
            (( added++ )) || true
        else
            (( failed++ )) || true
        fi
    done < <(fetch_github_repos)

    header "Sync complete"
    info "Mirrored : ${added}"
    info "Skipped  : ${skipped} (already existed)"
    [[ "$failed" -gt 0 ]] && warn "Failed   : ${failed}"
    [[ "$failed" -gt 0 ]] && return 1
    return 0
}

#---------------------------------------
#  INSTALL (systemd timer + env file)
#---------------------------------------
do_install() {
    [[ $EUID -eq 0 ]] || die "Run with sudo for --install."

    resolve_credentials

    header "Creating env file"
    mkdir -p /etc/gitea-mirrors
    cat > "$ENV_FILE" <<ENVEOF
GITHUB_TOKEN=${GITHUB_TOKEN}
GITEA_TOKEN=${GITEA_TOKEN}
GITHUB_USER=${GITHUB_USER}
GITEA_URL=${GITEA_URL}
GITEA_OWNER=${GITEA_OWNER}
MIRROR_INTERVAL=${MIRROR_INTERVAL}
ENVEOF
    chmod 640 "$ENV_FILE"
    ok "Env file written to ${ENV_FILE}"

    # Install script to a stable path
    local script_dest="/usr/local/bin/sync-github-mirrors"
    cp "$(realpath "$0")" "$script_dest"
    chmod 755 "$script_dest"
    ok "Script installed to ${script_dest}"

    header "Installing systemd units"
    cat > /etc/systemd/system/gitea-mirror-sync.service <<SVCEOF
[Unit]
Description=Sync new GitHub repos as Gitea pull-mirrors
After=network-online.target

[Service]
Type=oneshot
EnvironmentFile=${ENV_FILE}
ExecStart=${script_dest}
StandardOutput=journal
StandardError=journal
SVCEOF

    cat > /etc/systemd/system/gitea-mirror-sync.timer <<TMREOF
[Unit]
Description=Run GitHub → Gitea mirror sync every 6 hours
After=network-online.target

[Timer]
OnBootSec=10min
OnCalendar=0/6:00
Persistent=true

[Install]
WantedBy=timers.target
TMREOF

    systemctl daemon-reload
    systemctl enable --now gitea-mirror-sync.timer
    ok "Timer enabled — runs every 6 hours"

    local next
    next=$(systemctl list-timers gitea-mirror-sync.timer --no-pager 2>/dev/null \
           | awk 'NR==2{print $1,$2}')
    info "Next run: ${next}"

    header "Installation complete"
    echo ""
    echo "  Env file  : ${ENV_FILE}"
    echo "  Log file  : ${LOG_FILE}"
    echo ""
    echo "  Manual run:"
    echo "    systemctl start gitea-mirror-sync.service"
    echo "    journalctl -u gitea-mirror-sync -f"
    echo ""
}

#---------------------------------------
#  MAIN
#---------------------------------------
parse_args "$@"

if $DO_INSTALL; then
    do_install
    exit 0
fi

load_env
resolve_credentials
run_sync
