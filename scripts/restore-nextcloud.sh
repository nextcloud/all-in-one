#!/usr/bin/env bash
# Nextcloud AIO — Restore Script
# Companion to backup-nextcloud.sh
# Ref: https://docs.nextcloud.com/server/stable/admin_manual/maintenance/restore.html
#
# Usage:
#   sudo bash restore-nextcloud.sh <backup-timestamp-dir>
#
#   Examples:
#     sudo bash restore-nextcloud.sh /mnt/backup/nextcloud/20260613_143022
#     sudo bash restore-nextcloud.sh /mnt/backup/nextcloud/20260613_143022 --skip-data
#     sudo bash restore-nextcloud.sh /mnt/backup/nextcloud/20260613_143022 --dry-run
#
# What it restores:
#   1. AIO mastercontainer Docker volume  (aio_mastercontainer_volume_*.tar.gz)
#   2. PostgreSQL database                (nextcloud_db_*.sql.gz)
#   3. Nextcloud data directory           (nextcloud_data_*.tar.gz)  [skippable]
#
# WARNING: This is a DESTRUCTIVE operation. Existing data will be overwritten.
#          The script prompts for confirmation before making any changes.

set -euo pipefail

# ── Colours ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'; YELLOW='\033[1;33m'; GREEN='\033[0;32m'
CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'

info()  { echo -e "${CYAN}[INFO]${RESET}  $*"; }
ok()    { echo -e "${GREEN}[OK]${RESET}    $*"; }
warn()  { echo -e "${YELLOW}[WARN]${RESET}  $*"; }
die()   { echo -e "${RED}[ERROR]${RESET} $*" >&2; exit 1; }
step()  { echo -e "\n${BOLD}──── $* ────${RESET}"; }
dryrun(){ echo -e "${YELLOW}[DRY-RUN]${RESET} $*"; }

# ── Defaults (match backup-nextcloud.sh) ──────────────────────────────────────
: "${NC_DATA_DIR:=/mnt/ncdata}"
: "${NC_MASTERCONTAINER:=nextcloud-aio-mastercontainer}"
: "${NC_CONTAINER:=nextcloud-aio-nextcloud}"
: "${NC_DB_CONTAINER:=nextcloud-aio-database}"
: "${AIO_VOLUME:=nextcloud_aio_mastercontainer}"
: "${NC_DB_NAME:=nextcloud_db}"
: "${NC_DB_USER:=oc_nextcloud}"

# ── Arg parsing ───────────────────────────────────────────────────────────────
BACKUP_PATH=""
SKIP_DATA=false
DRY_RUN=false

usage() {
    echo "Usage: sudo $0 <backup-dir> [--skip-data] [--dry-run]"
    echo ""
    echo "  <backup-dir>   Path to a timestamped backup directory produced by"
    echo "                 backup-nextcloud.sh (e.g. /mnt/backup/nextcloud/20260613_143022)"
    echo "  --skip-data    Do not restore the data directory (faster; preserves current files)"
    echo "  --dry-run      Show what would happen without making any changes"
    exit 0
}

for arg in "$@"; do
    case "$arg" in
        --help|-h)   usage ;;
        --skip-data) SKIP_DATA=true ;;
        --dry-run)   DRY_RUN=true ;;
        -*)          die "Unknown flag: $arg  (try --help)" ;;
        *)
            [[ -z "$BACKUP_PATH" ]] || die "Unexpected argument: $arg"
            BACKUP_PATH="$arg"
            ;;
    esac
done

[[ -n "$BACKUP_PATH" ]] || { usage; }

# ── Guards ────────────────────────────────────────────────────────────────────
require_root() {
    [[ $EUID -eq 0 ]] || die "Run as root:  sudo $0 $*"
}

check_docker() {
    command -v docker &>/dev/null || die "Docker not found"
    docker info &>/dev/null       || die "Docker daemon is not running"
}

check_aio() {
    docker inspect "$NC_MASTERCONTAINER" &>/dev/null \
        || die "AIO mastercontainer '$NC_MASTERCONTAINER' not found — is Nextcloud AIO installed?"
}

check_backup_dir() {
    [[ -d "$BACKUP_PATH" ]] || die "Backup directory not found: $BACKUP_PATH"

    DB_FILE=$(find "$BACKUP_PATH" -maxdepth 1 -name "nextcloud_db_*.sql.gz" | head -1)
    VOL_FILE=$(find "$BACKUP_PATH" -maxdepth 1 -name "aio_mastercontainer_volume_*.tar.gz" | head -1)
    DATA_FILE=$(find "$BACKUP_PATH" -maxdepth 1 -name "nextcloud_data_*.tar.gz" | head -1)

    [[ -n "$DB_FILE" ]]  || die "No database dump found in $BACKUP_PATH  (nextcloud_db_*.sql.gz)"
    [[ -n "$VOL_FILE" ]] || die "No AIO volume backup found in $BACKUP_PATH  (aio_mastercontainer_volume_*.tar.gz)"

    if [[ "$SKIP_DATA" == false && -z "$DATA_FILE" ]]; then
        warn "No data archive found (nextcloud_data_*.tar.gz) — skipping data restore."
        warn "If this backup was created with SKIP_DATA=true, use --skip-data to suppress this warning."
        SKIP_DATA=true
    fi

    info "Database  : $(basename "$DB_FILE")  ($(du -sh "$DB_FILE" | cut -f1))"
    info "AIO volume: $(basename "$VOL_FILE") ($(du -sh "$VOL_FILE" | cut -f1))"
    if [[ "$SKIP_DATA" == false ]]; then
        info "Data dir  : $(basename "$DATA_FILE") ($(du -sh "$DATA_FILE" | cut -f1))"
    else
        info "Data dir  : (skipped)"
    fi
}

verify_archives() {
    step "Verifying archive integrity"
    local fail=0

    for f in "$DB_FILE" "$VOL_FILE" ${DATA_FILE:+$DATA_FILE}; do
        [[ -f "$f" ]] || continue
        if gzip -t "$f" 2>/dev/null; then
            ok "OK  $(basename "$f")"
        else
            warn "CORRUPT  $(basename "$f")"
            fail=$((fail + 1))
        fi
    done

    [[ $fail -eq 0 ]] || die "Integrity check failed for $fail file(s) — aborting restore"
}

confirm_restore() {
    echo ""
    echo -e "${RED}${BOLD}  !! DESTRUCTIVE OPERATION !!${RESET}"
    echo ""
    echo "  This will OVERWRITE:"
    echo "    • Docker volume  : $AIO_VOLUME"
    echo "    • Database       : $NC_DB_NAME  (in $NC_DB_CONTAINER)"
    if [[ "$SKIP_DATA" == false ]]; then
        echo "    • Data directory : $NC_DATA_DIR"
    fi
    echo ""
    echo "  Restoring from: $BACKUP_PATH"
    echo ""
    read -rp "  Type 'yes' to proceed: " answer
    [[ "$answer" == "yes" ]] || die "Aborted by user"
}

# ── Maintenance mode ──────────────────────────────────────────────────────────
NC_WAS_RUNNING=false

maintenance_on() {
    if docker inspect "$NC_CONTAINER" &>/dev/null 2>&1 \
       && [[ "$(docker inspect -f '{{.State.Running}}' "$NC_CONTAINER" 2>/dev/null)" == "true" ]]; then
        NC_WAS_RUNNING=true
        if [[ "$DRY_RUN" == true ]]; then
            dryrun "docker exec --user www-data $NC_CONTAINER php occ maintenance:mode --on"
        else
            info "Enabling maintenance mode..."
            docker exec --user www-data "$NC_CONTAINER" \
                php occ maintenance:mode --on \
                || warn "Could not enable maintenance mode (continuing)"
            ok "Maintenance mode: ON"
        fi
    else
        info "Nextcloud container not running — skipping maintenance mode toggle"
    fi
}

maintenance_off() {
    if docker inspect "$NC_CONTAINER" &>/dev/null 2>&1 \
       && [[ "$(docker inspect -f '{{.State.Running}}' "$NC_CONTAINER" 2>/dev/null)" == "true" ]]; then
        if [[ "$DRY_RUN" == true ]]; then
            dryrun "docker exec --user www-data $NC_CONTAINER php occ maintenance:mode --off"
        else
            info "Disabling maintenance mode..."
            if ! docker exec --user www-data "$NC_CONTAINER" \
                    php occ maintenance:mode --off 2>/dev/null; then
                warn "Could not auto-disable maintenance mode — run manually:"
                warn "  docker exec --user www-data $NC_CONTAINER php occ maintenance:mode --off"
            else
                ok "Maintenance mode: OFF"
            fi
        fi
    fi
}

# ── Container lifecycle ───────────────────────────────────────────────────────
CONTAINERS_FILE="/tmp/nc-restore-containers-$$.txt"

stop_aio_containers() {
    step "Stopping AIO containers"

    local containers
    containers=$(docker ps \
        --filter "name=nextcloud-aio" \
        --filter "status=running" \
        --format "{{.Names}}" \
        | grep -v "^${NC_MASTERCONTAINER}$" || true)

    if [[ -z "$containers" ]]; then
        warn "No AIO containers are running (besides mastercontainer)"
        touch "$CONTAINERS_FILE"
        return
    fi

    echo "$containers" > "$CONTAINERS_FILE"

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
            if [[ "$DRY_RUN" == true ]]; then
                dryrun "docker stop $c"
            elif docker stop "$c" &>/dev/null; then
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
        warn "No containers to restart"
        rm -f "$CONTAINERS_FILE"
        return
    fi

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
            if [[ "$DRY_RUN" == true ]]; then
                dryrun "docker start $c"
            elif docker start "$c" &>/dev/null; then
                ok "Started: $c"
            else
                warn "Could not start: $c — check with: docker start $c"
            fi
        fi
    done

    rm -f "$CONTAINERS_FILE"

    if [[ "$DRY_RUN" == false ]]; then
        info "Waiting 15 s for containers to initialise..."
        sleep 15
    fi
}

# ── Restore steps ─────────────────────────────────────────────────────────────
restore_aio_volume() {
    step "Restoring AIO mastercontainer volume"

    if [[ "$DRY_RUN" == true ]]; then
        dryrun "Wipe and repopulate Docker volume: $AIO_VOLUME  ← $(basename "$VOL_FILE")"
        return
    fi

    # Clear the existing volume contents, then extract the archive into it
    docker run --rm \
        --volume "${AIO_VOLUME}:/data" \
        --volume "${BACKUP_PATH}:/backup:ro" \
        alpine \
        sh -c "rm -rf /data/* /data/..?* /data/.[!.]* 2>/dev/null; \
               tar -xzf /backup/$(basename "$VOL_FILE") -C /data" \
        || die "AIO volume restore failed"

    ok "AIO volume restored from $(basename "$VOL_FILE")"
}

restore_database() {
    step "Restoring PostgreSQL database"

    # The database container must be running to accept connections.
    # If it was stopped (part of AIO shutdown), start it alone first.
    local db_was_stopped=false
    if ! docker inspect "$NC_DB_CONTAINER" &>/dev/null 2>&1 \
       || [[ "$(docker inspect -f '{{.State.Running}}' "$NC_DB_CONTAINER" 2>/dev/null)" != "true" ]]; then
        db_was_stopped=true
        if [[ "$DRY_RUN" == true ]]; then
            dryrun "docker start $NC_DB_CONTAINER  (temporary, for restore)"
        else
            info "Starting database container temporarily..."
            docker start "$NC_DB_CONTAINER" || die "Could not start database container"
            sleep 5  # let PostgreSQL initialise
        fi
    fi

    if [[ "$DRY_RUN" == true ]]; then
        dryrun "Drop + recreate $NC_DB_NAME, then psql restore from $(basename "$DB_FILE")"
    else
        # Drop and recreate the database for a clean restore
        docker exec "$NC_DB_CONTAINER" \
            psql -U "$NC_DB_USER" -d postgres \
            -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '${NC_DB_NAME}' AND pid <> pg_backend_pid();" \
            &>/dev/null || true

        docker exec "$NC_DB_CONTAINER" \
            psql -U "$NC_DB_USER" -d postgres \
            -c "DROP DATABASE IF EXISTS \"${NC_DB_NAME}\";" \
            || die "Could not drop existing database"

        docker exec "$NC_DB_CONTAINER" \
            psql -U "$NC_DB_USER" -d postgres \
            -c "CREATE DATABASE \"${NC_DB_NAME}\" OWNER \"${NC_DB_USER}\";" \
            || die "Could not recreate database"

        # Stream the dump through gunzip into psql inside the container
        gunzip -c "$DB_FILE" \
            | docker exec -i "$NC_DB_CONTAINER" \
                psql -U "$NC_DB_USER" -d "$NC_DB_NAME" -q \
            || die "Database restore failed"

        ok "Database restored from $(basename "$DB_FILE")"
    fi

    # Stop the db container again if we temporarily started it —
    # start_aio_containers will bring it back in the right order
    if [[ "$db_was_stopped" == true && "$DRY_RUN" == false ]]; then
        docker stop "$NC_DB_CONTAINER" &>/dev/null || true
    fi
}

restore_data_dir() {
    if [[ "$SKIP_DATA" == true ]]; then
        warn "Skipping data directory restore (--skip-data)"
        return
    fi

    step "Restoring data directory: $NC_DATA_DIR"

    if [[ "$DRY_RUN" == true ]]; then
        dryrun "rm -rf $NC_DATA_DIR/*  then  tar -xzf $(basename "$DATA_FILE") → $NC_DATA_DIR"
        return
    fi

    local parent
    parent=$(dirname "$NC_DATA_DIR")
    local base
    base=$(basename "$NC_DATA_DIR")

    # Wipe existing data dir, then extract backup in its place
    rm -rf "${NC_DATA_DIR:?}/"
    mkdir -p "$NC_DATA_DIR"

    tar -xzf "$DATA_FILE" -C "$parent" \
        || die "Data directory restore failed"

    # Re-apply expected ownership (AIO runs Nextcloud as www-data)
    chown -R www-data:www-data "$NC_DATA_DIR" \
        || warn "Could not chown $NC_DATA_DIR to www-data — check ownership manually"

    ok "Data directory restored from $(basename "$DATA_FILE")"
}

post_restore_repair() {
    step "Running post-restore repair"

    if [[ "$DRY_RUN" == true ]]; then
        dryrun "php occ maintenance:data-fingerprint"
        dryrun "php occ files:scan --all"
        return
    fi

    # data-fingerprint tells clients the server state changed (prevents sync conflicts)
    if docker exec --user www-data "$NC_CONTAINER" \
            php occ maintenance:data-fingerprint &>/dev/null; then
        ok "data-fingerprint updated"
    else
        warn "data-fingerprint failed — run manually: docker exec --user www-data $NC_CONTAINER php occ maintenance:data-fingerprint"
    fi

    # Rescan files so Nextcloud's DB matches what's on disk
    info "Scanning files (this may take a while for large instances)..."
    if docker exec --user www-data "$NC_CONTAINER" \
            php occ files:scan --all -q &>/dev/null; then
        ok "File scan complete"
    else
        warn "File scan failed — run manually: docker exec --user www-data $NC_CONTAINER php occ files:scan --all"
    fi
}

# ── Emergency: attempt to re-enable service on unexpected exit ─────────────────
SERVICE_DOWN=false

_emergency_recover() {
    local code=$?
    [[ $code -eq 0 ]] && return
    if [[ "$SERVICE_DOWN" == true && "$DRY_RUN" == false ]]; then
        echo -e "\n${RED}Script interrupted (exit $code) — attempting to restore service...${RESET}" >&2
        start_aio_containers || true
        maintenance_off      || true
    fi
    rm -f "$CONTAINERS_FILE"
}
trap _emergency_recover EXIT

# ── Main ──────────────────────────────────────────────────────────────────────
echo -e "${BOLD}Nextcloud AIO — Restore${RESET}"
[[ "$DRY_RUN" == true ]] && echo -e "${YELLOW}  DRY-RUN mode — no changes will be made${RESET}"
echo ""

require_root
check_docker
check_aio
check_backup_dir
verify_archives

[[ "$DRY_RUN" == false ]] && confirm_restore

# 1. Engage maintenance mode while Nextcloud is still up (if it is)
maintenance_on

# 2. Stop everything so we get a clean slate
stop_aio_containers
SERVICE_DOWN=true

# 3. Restore in dependency order: volume → db → data
restore_aio_volume
restore_database
restore_data_dir

# 4. Bring service back up
start_aio_containers
SERVICE_DOWN=false

# 5. Post-restore repair (occ commands)
maintenance_off
post_restore_repair

rm -f "$CONTAINERS_FILE"

echo ""
if [[ "$DRY_RUN" == true ]]; then
    echo -e "${YELLOW}${BOLD}── Dry run complete — no changes were made ──────────────${RESET}"
else
    echo -e "${GREEN}${BOLD}── Restore complete ─────────────────────────────────────${RESET}"
    echo -e "  Restored from : $BACKUP_PATH"
    echo -e "  Verify at     : https://<your-domain>/login"
    echo -e "${GREEN}${BOLD}─────────────────────────────────────────────────────────${RESET}"
fi
echo ""
