#!/usr/bin/env bash
# Nextcloud AIO — Backup Script
# Ref: https://docs.nextcloud.com/server/stable/admin_manual/maintenance/backup.html
#
# Usage:
#   sudo bash backup-nextcloud.sh                          # default settings
#   sudo BACKUP_DIR=/mnt/external/nc bash backup-nextcloud.sh
#   sudo SKIP_DATA=true bash backup-nextcloud.sh           # skip large data dir (faster)
#
# What it backs up:
#   1. PostgreSQL database (pg_dump, live — safe because Nextcloud is in maintenance mode)
#   2. AIO mastercontainer Docker volume (AIO config, certificates, etc.)
#   3. Nextcloud data directory (user files)
#
# Backup layout:
#   $BACKUP_DIR/
#     20260613_143022/
#       nextcloud_db_20260613_143022.sql.gz
#       aio_mastercontainer_volume_20260613_143022.tar.gz
#       nextcloud_data_20260613_143022.tar.gz   (if SKIP_DATA != true)

set -euo pipefail

# ── Colours ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'; YELLOW='\033[1;33m'; GREEN='\033[0;32m'
CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'

info()  { echo -e "${CYAN}[INFO]${RESET}  $*"; }
ok()    { echo -e "${GREEN}[OK]${RESET}    $*"; }
warn()  { echo -e "${YELLOW}[WARN]${RESET}  $*"; }
die()   { echo -e "${RED}[ERROR]${RESET} $*" >&2; exit 1; }
step()  { echo -e "\n${BOLD}──── $* ────${RESET}"; }

# ── Configuration ─────────────────────────────────────────────────────────────
: "${NC_DATA_DIR:=/mnt/ncdata}"
: "${BACKUP_DIR:=/mnt/backup/nextcloud}"
: "${BACKUP_RETENTION_DAYS:=7}"
: "${SKIP_DATA:=false}"

# AIO container / volume names (defaults from install-nextcloud-aio.sh)
: "${NC_MASTERCONTAINER:=nextcloud-aio-mastercontainer}"
: "${NC_CONTAINER:=nextcloud-aio-nextcloud}"
: "${NC_DB_CONTAINER:=nextcloud-aio-database}"
: "${AIO_VOLUME:=nextcloud_aio_mastercontainer}"

# PostgreSQL credentials (AIO defaults)
: "${NC_DB_NAME:=nextcloud_db}"
: "${NC_DB_USER:=oc_nextcloud}"

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_PATH="${BACKUP_DIR}/${TIMESTAMP}"
CONTAINERS_FILE="/tmp/nc-backup-containers-${TIMESTAMP}.txt"

# ── Guards ────────────────────────────────────────────────────────────────────
require_root() {
    [[ $EUID -eq 0 ]] || die "Run as root:  sudo $0"
}

check_docker() {
    command -v docker &>/dev/null || die "Docker not found"
    docker info &>/dev/null       || die "Docker daemon is not running"
}

check_aio() {
    docker inspect "$NC_MASTERCONTAINER" &>/dev/null \
        || die "AIO mastercontainer '$NC_MASTERCONTAINER' not found — is Nextcloud AIO installed?"
    docker inspect "$NC_CONTAINER" &>/dev/null \
        || die "Nextcloud container '$NC_CONTAINER' not found — start Nextcloud from the AIO admin interface first."
    docker inspect "$NC_DB_CONTAINER" &>/dev/null \
        || die "Database container '$NC_DB_CONTAINER' not found."
}

# ── Maintenance mode ──────────────────────────────────────────────────────────
maintenance_on() {
    info "Enabling maintenance mode..."
    docker exec --user www-data "$NC_CONTAINER" \
        php occ maintenance:mode --on \
        || die "Could not enable maintenance mode"
    ok "Maintenance mode: ON"
}

maintenance_off() {
    info "Disabling maintenance mode..."
    if ! docker exec --user www-data "$NC_CONTAINER" php occ maintenance:mode --off 2>/dev/null; then
        warn "Automatic disable failed — run manually:"
        warn "  docker exec --user www-data $NC_CONTAINER php occ maintenance:mode --off"
        return 1
    fi
    ok "Maintenance mode: OFF"
}

# ── Container lifecycle ───────────────────────────────────────────────────────
stop_aio_containers() {
    step "Stopping AIO containers"

    # Collect running AIO containers excluding the mastercontainer
    local containers
    containers=$(docker ps \
        --filter "name=nextcloud-aio" \
        --filter "status=running" \
        --format "{{.Names}}" \
        | grep -v "^${NC_MASTERCONTAINER}$" || true)

    if [[ -z "$containers" ]]; then
        warn "No additional AIO containers are running"
        touch "$CONTAINERS_FILE"
        return
    fi

    echo "$containers" > "$CONTAINERS_FILE"

    # Stop in reverse dependency order (app → cache → db)
    local stop_order=(
        nextcloud-aio-talk
        nextcloud-aio-collabora
        nextcloud-aio-imaginary
        nextcloud-aio-clamav
        nextcloud-aio-fulltextsearch
        nextcloud-aio-whiteboard
        nextcloud-aio-nextcloud
        nextcloud-aio-redis
        nextcloud-aio-database
    )

    for c in "${stop_order[@]}"; do
        if grep -qx "$c" "$CONTAINERS_FILE" 2>/dev/null; then
            if docker stop "$c" &>/dev/null; then
                ok "Stopped: $c"
            else
                warn "Could not stop: $c (continuing)"
            fi
        fi
    done
}

start_aio_containers() {
    step "Restarting AIO containers"

    if [[ ! -s "$CONTAINERS_FILE" ]]; then
        warn "No containers to restart (nothing was stopped)"
        rm -f "$CONTAINERS_FILE"
        return
    fi

    # Start in dependency order (db → cache → app)
    local start_order=(
        nextcloud-aio-database
        nextcloud-aio-redis
        nextcloud-aio-nextcloud
        nextcloud-aio-collabora
        nextcloud-aio-talk
        nextcloud-aio-imaginary
        nextcloud-aio-clamav
        nextcloud-aio-fulltextsearch
        nextcloud-aio-whiteboard
    )

    for c in "${start_order[@]}"; do
        if grep -qx "$c" "$CONTAINERS_FILE" 2>/dev/null; then
            if docker start "$c" &>/dev/null; then
                ok "Started: $c"
            else
                warn "Could not start: $c — check with: docker start $c"
            fi
        fi
    done

    rm -f "$CONTAINERS_FILE"

    info "Waiting 10 s for containers to initialise..."
    sleep 10
}

# ── Backup steps ──────────────────────────────────────────────────────────────
backup_database() {
    step "Backing up PostgreSQL database"
    local out="${BACKUP_PATH}/nextcloud_db_${TIMESTAMP}.sql.gz"

    # pg_dump runs while the db container is still up (before stop_aio_containers)
    docker exec "$NC_DB_CONTAINER" \
        pg_dump -U "$NC_DB_USER" --no-password "$NC_DB_NAME" \
        | gzip > "$out" \
        || die "Database dump failed"

    ok "Database → $(du -sh "$out" | cut -f1)  $out"
}

backup_aio_volume() {
    step "Backing up AIO mastercontainer volume"
    local out="${BACKUP_PATH}/aio_mastercontainer_volume_${TIMESTAMP}.tar.gz"
    local out_name
    out_name=$(basename "$out")

    docker run --rm \
        --volume "${AIO_VOLUME}:/data:ro" \
        --volume "${BACKUP_PATH}:/backup" \
        alpine \
        tar -czf "/backup/${out_name}" -C /data . \
        || die "AIO volume backup failed"

    ok "AIO volume → $(du -sh "$out" | cut -f1)  $out"
}

backup_data_dir() {
    if [[ "$SKIP_DATA" == "true" ]]; then
        warn "Skipping data directory backup (SKIP_DATA=true)"
        return
    fi

    step "Backing up data directory: $NC_DATA_DIR"
    [[ -d "$NC_DATA_DIR" ]] || die "Data directory not found: $NC_DATA_DIR"

    local out="${BACKUP_PATH}/nextcloud_data_${TIMESTAMP}.tar.gz"

    tar -czf "$out" \
        --exclude="${NC_DATA_DIR}/*/cache" \
        --exclude="${NC_DATA_DIR}/appdata_*/preview" \
        --exclude="${NC_DATA_DIR}/updater-*/backups" \
        -C "$(dirname "$NC_DATA_DIR")" \
        "$(basename "$NC_DATA_DIR")" \
        || die "Data directory backup failed"

    ok "Data → $(du -sh "$out" | cut -f1)  $out"
}

# ── Post-backup ───────────────────────────────────────────────────────────────
verify_backup() {
    step "Verifying backup integrity"
    local pass=0 fail=0

    for f in "${BACKUP_PATH}"/*.gz; do
        [[ -f "$f" ]] || continue
        if gzip -t "$f" 2>/dev/null; then
            ok "OK  $(basename "$f")"
            pass=$((pass + 1))
        else
            warn "FAIL  $(basename "$f")"
            fail=$((fail + 1))
        fi
    done

    [[ $fail -eq 0 ]] || die "Integrity check failed for $fail file(s)"
    ok "$pass file(s) verified"
}

rotate_old_backups() {
    [[ "$BACKUP_RETENTION_DAYS" -le 0 ]] && return

    step "Rotating backups older than ${BACKUP_RETENTION_DAYS} days"
    local removed=0
    while IFS= read -r -d '' dir; do
        rm -rf "$dir"
        ok "Removed: $dir"
        removed=$((removed + 1))
    done < <(find "$BACKUP_DIR" -maxdepth 1 -mindepth 1 -type d \
        -mtime "+${BACKUP_RETENTION_DAYS}" -print0 2>/dev/null)

    [[ $removed -eq 0 ]] && info "Nothing to rotate"
}

# ── Emergency cleanup on unexpected exit ──────────────────────────────────────
MAINTENANCE_ACTIVE=false

_emergency_restore() {
    local code=$?
    [[ $code -eq 0 ]] && return
    if [[ "$MAINTENANCE_ACTIVE" == true ]]; then
        echo -e "\n${RED}Script interrupted (exit $code) — attempting service restore...${RESET}" >&2
        start_aio_containers || true
        maintenance_off      || true
    fi
    rm -f "$CONTAINERS_FILE"
}
trap _emergency_restore EXIT

# ── Main ──────────────────────────────────────────────────────────────────────
echo -e "${BOLD}Nextcloud AIO — Backup${RESET}"
echo "Timestamp : $TIMESTAMP"
echo "Dest      : $BACKUP_PATH"
echo ""

require_root
check_docker
check_aio

mkdir -p "$BACKUP_PATH"

# 1. Dump DB while containers are still running
maintenance_on
MAINTENANCE_ACTIVE=true
backup_database

# 2. Bring containers down for a consistent file snapshot
stop_aio_containers
backup_aio_volume
backup_data_dir

# 3. Restore service
start_aio_containers
maintenance_off
MAINTENANCE_ACTIVE=false

# 4. Verify and rotate
verify_backup
rotate_old_backups

echo ""
echo -e "${GREEN}${BOLD}── Backup complete ──────────────────────────────────────${RESET}"
du -sh "${BACKUP_PATH}" | awk '{print "  Total size : " $1}'
echo -e "  Location   : ${BACKUP_PATH}"
echo -e "${GREEN}${BOLD}─────────────────────────────────────────────────────────${RESET}"
echo ""
