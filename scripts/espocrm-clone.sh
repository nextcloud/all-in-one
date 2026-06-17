#!/usr/bin/env bash
# espocrm-clone.sh — Clone an EspoCRM instance with a clean database.

set -euo pipefail

# ── colours ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'

info()    { echo -e "${CYAN}▶ $*${RESET}"; }
success() { echo -e "${GREEN}✔ $*${RESET}"; }
warn()    { echo -e "${YELLOW}⚠ $*${RESET}"; }
die()     { echo -e "${RED}✘ $*${RESET}" >&2; exit 1; }

# ── defaults (from detected config) ──────────────────────────────────────────
DEFAULT_SRC="/home/coreconduit/espocrm"
DEFAULT_DEST="/home/coreconduit/espocrm-dev"
DEFAULT_NEW_DB="espocrm_dev"
DEFAULT_NEW_URL="http://localhost:8080"
DEFAULT_DB_HOST="localhost"
DEFAULT_DB_PORT=""
DEFAULT_DB_USER="espocrm"
# shellcheck disable=SC2034
MYSQL_ROOT_CMD="mysql -u root -p"

# ── banner ────────────────────────────────────────────────────────────────────
echo -e "\n${BOLD}EspoCRM Clone — Clean Database Setup${RESET}"
echo    "────────────────────────────────────────"

# ── gather inputs ─────────────────────────────────────────────────────────────
prompt() {
  local var="$1" label="$2" default="$3"
  read -rp "$(echo -e "${BOLD}${label}${RESET} [${default}]: ")" input
  printf -v "$var" '%s' "${input:-$default}"
}

prompt SRC      "Source EspoCRM path"   "$DEFAULT_SRC"
prompt DEST     "Destination path"      "$DEFAULT_DEST"
prompt NEW_DB   "New database name"     "$DEFAULT_NEW_DB"
prompt NEW_URL  "New siteUrl"           "$DEFAULT_NEW_URL"
prompt DB_HOST  "DB host"               "$DEFAULT_DB_HOST"
prompt DB_PORT  "DB port (blank=3306)"  "$DEFAULT_DB_PORT"
prompt DB_USER  "DB user"               "$DEFAULT_DB_USER"

read -rsp "$(echo -e "${BOLD}DB password for '${DB_USER}'${RESET}: ")" DB_PASS
echo
read -rsp "$(echo -e "${BOLD}MySQL root password${RESET}: ")" ROOT_PASS
echo

# ── port flag for mysql commands ──────────────────────────────────────────────
PORT_FLAG=""
[[ -n "$DB_PORT" ]] && PORT_FLAG="--port=${DB_PORT}"

MYSQL_ROOT="mysql -u root -p${ROOT_PASS} -h ${DB_HOST} ${PORT_FLAG}"
MYSQL_USER="mysql -u ${DB_USER} -p${DB_PASS} -h ${DB_HOST} ${PORT_FLAG}"

# ── confirm ───────────────────────────────────────────────────────────────────
echo
echo -e "${BOLD}Summary:${RESET}"
echo    "  Source  : $SRC"
echo    "  Dest    : $DEST"
echo    "  New DB  : $NEW_DB"
echo    "  Site URL: $NEW_URL"
echo
read -rp "$(echo -e "${YELLOW}Proceed? [y/N]: ${RESET}")" confirm
[[ "$confirm" =~ ^[Yy]$ ]] || { warn "Aborted."; exit 0; }

# ── preflight checks ──────────────────────────────────────────────────────────
echo
info "Running preflight checks..."

[[ -d "$SRC" ]]                        || die "Source path not found: $SRC"
[[ -f "$SRC/data/config-internal.php" ]] || die "config-internal.php missing in source — is this a valid EspoCRM install?"
command -v php  >/dev/null 2>&1        || die "php not found in PATH"
command -v rsync >/dev/null 2>&1       || die "rsync not found — install with: sudo apt install rsync"
command -v mysql >/dev/null 2>&1       || die "mysql client not found"

[[ -d "$DEST" ]] && die "Destination already exists: $DEST — remove it first or choose a different path"

# Verify DB root access
$MYSQL_ROOT -e "SELECT 1;" >/dev/null 2>&1 || die "MySQL root login failed — check root password"

success "Preflight passed"

# ── step 1: copy files ────────────────────────────────────────────────────────
info "Copying files (this may take a moment)..."
rsync -a --exclude='data/cache/*' --exclude='data/logs/*' --exclude='data/tmp/*' \
  "${SRC}/" "${DEST}/"
success "Files copied to $DEST"

# ── step 2: create database ───────────────────────────────────────────────────
info "Creating database '$NEW_DB'..."
$MYSQL_ROOT -e "
  CREATE DATABASE IF NOT EXISTS \`${NEW_DB}\`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
  GRANT ALL PRIVILEGES ON \`${NEW_DB}\`.* TO '${DB_USER}'@'${DB_HOST}' IDENTIFIED BY '${DB_PASS}';
  FLUSH PRIVILEGES;
" 2>&1 || die "Failed to create database"
success "Database '$NEW_DB' created and privileges granted"

# ── step 3: update config-internal.php ───────────────────────────────────────
info "Updating config-internal.php..."
INTERNAL="$DEST/data/config-internal.php"

# Read current dbname from source
CURRENT_DB=$(php -r "
  \$c = include '${SRC}/data/config-internal.php';
  echo \$c['database']['dbname'];
")

# Replace dbname, user, password, host, port in one sed pass
sed -i \
  -e "s|'dbname' => '${CURRENT_DB}'|'dbname' => '${NEW_DB}'|g" \
  -e "s|'host' => '[^']*'|'host' => '${DB_HOST}'|g" \
  "$INTERNAL"

# Update DB user and password (handles any existing value)
php -r "
  \$c = include '${INTERNAL}';
  \$c['database']['user']     = '${DB_USER}';
  \$c['database']['password'] = '${DB_PASS}';
  \$c['database']['dbname']   = '${NEW_DB}';
  \$c['database']['host']     = '${DB_HOST}';
  \$c['database']['port']     = '${DB_PORT}';
  \$out = '<?php' . PHP_EOL . 'return ' . var_export(\$c, true) . ';' . PHP_EOL;
  file_put_contents('${INTERNAL}', \$out);
"
success "config-internal.php updated"

# ── step 4: update siteUrl in config.php ─────────────────────────────────────
info "Updating siteUrl in config.php..."
CURRENT_URL=$(php -r "
  \$c = include '${SRC}/data/config.php';
  echo \$c['siteUrl'] ?? '';
")

if [[ -n "$CURRENT_URL" ]]; then
  # Escape slashes for sed
  ESCAPED_OLD=$(printf '%s\n' "$CURRENT_URL" | sed 's/[\/&]/\\&/g')
  ESCAPED_NEW=$(printf '%s\n' "$NEW_URL"     | sed 's/[\/&]/\\&/g')
  sed -i "s|${ESCAPED_OLD}|${ESCAPED_NEW}|g" "$DEST/data/config.php"
  success "siteUrl updated: $NEW_URL"
else
  warn "siteUrl not found in config.php — add it manually if needed"
fi

# ── step 5: clear cache ───────────────────────────────────────────────────────
info "Clearing cache..."
rm -rf "${DEST}/data/cache/"*
success "Cache cleared"

# ── step 6: rebuild schema ────────────────────────────────────────────────────
info "Rebuilding schema in '$NEW_DB' (this takes ~30 seconds)..."
php "${DEST}/command.php" rebuild 2>&1 | tail -5
success "Schema rebuilt"

# ── step 7: verify ────────────────────────────────────────────────────────────
info "Verifying table count..."
TABLE_COUNT=$($MYSQL_USER "$NEW_DB" -e "SHOW TABLES;" 2>/dev/null | wc -l)

if (( TABLE_COUNT > 50 )); then
  success "Verification passed — $TABLE_COUNT tables found in '$NEW_DB'"
else
  warn "Only $TABLE_COUNT tables found — rebuild may have had issues. Check: php ${DEST}/command.php rebuild"
fi

# ── done ──────────────────────────────────────────────────────────────────────
echo
echo -e "${BOLD}${GREEN}Clone complete!${RESET}"
echo    "  Install : $DEST"
echo    "  Database: $NEW_DB"
echo    "  URL     : $NEW_URL"
echo
echo -e "${CYAN}Next steps:${RESET}"
echo    "  1. Point your web server at $DEST"
echo    "  2. Ensure file permissions: sudo chown -R www-data:www-data $DEST"
echo    "  3. Visit $NEW_URL and log in with your existing admin credentials"
echo
