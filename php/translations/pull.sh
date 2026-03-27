#!/usr/bin/env bash
# pull.sh — Fetch all translations from Transifex API v3 and write them to
#            php/translations/{lang}.json.
#
# Usage:
#   TRANSIFEX_TOKEN=your_token ./pull.sh
#
# Optional env vars:
#   TRANSIFEX_ORG     — Transifex organisation slug  (default: nextcloud)
#   TRANSIFEX_PROJECT — Transifex project slug        (default: nextcloud-all-in-one)
#
# Requirements: bash, curl, jq
#
# Never called at runtime — run manually or from CI before a release.

set -euo pipefail

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------
TOKEN="${TRANSIFEX_TOKEN:?'TRANSIFEX_TOKEN env var must be set'}"
ORG="${TRANSIFEX_ORG:-nextcloud}"
PROJECT="${TRANSIFEX_PROJECT:-nextcloud-all-in-one}"

API="https://rest.api.transifex.com"
AUTH_HEADER="Authorization: Bearer ${TOKEN}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
require_cmd() {
    if ! command -v "$1" &>/dev/null; then
        echo "ERROR: required command '$1' not found." >&2
        exit 1
    fi
}

require_cmd curl
require_cmd jq

log() { echo "[pull.sh] $*"; }

# ---------------------------------------------------------------------------
# 1. Fetch the list of languages for the project
# ---------------------------------------------------------------------------
log "Fetching language list for ${ORG}/${PROJECT} …"

languages_response=$(curl --silent --fail --show-error \
    -H "${AUTH_HEADER}" \
    -H "Content-Type: application/vnd.api+json" \
    "${API}/projects/o:${ORG}:p:${PROJECT}/languages")

mapfile -t lang_codes < <(echo "${languages_response}" \
    | jq -r '.data[].attributes.code')

if [[ ${#lang_codes[@]} -eq 0 ]]; then
    log "No languages found — nothing to do."
    exit 0
fi

log "Found ${#lang_codes[@]} language(s): ${lang_codes[*]}"

# ---------------------------------------------------------------------------
# 2. Fetch the list of resources for the project (we need the resource slug)
# ---------------------------------------------------------------------------
log "Fetching resource list …"

resources_response=$(curl --silent --fail --show-error \
    -H "${AUTH_HEADER}" \
    -H "Content-Type: application/vnd.api+json" \
    "${API}/resources?filter[project]=o:${ORG}:p:${PROJECT}")

mapfile -t resource_slugs < <(echo "${resources_response}" \
    | jq -r '.data[].attributes.slug')

if [[ ${#resource_slugs[@]} -eq 0 ]]; then
    log "No resources found — nothing to do."
    exit 0
fi

log "Found ${#resource_slugs[@]} resource(s): ${resource_slugs[*]}"

# ---------------------------------------------------------------------------
# 3. For each language, merge translations from all resources and write JSON
# ---------------------------------------------------------------------------

# poll_until_ready <url> — keeps polling an async download URL until the
# Transifex job finishes, then prints the redirect/content URL.
poll_until_ready() {
    local url="$1"
    local max_attempts=30
    local attempt=0
    local delay=2

    while (( attempt < max_attempts )); do
        response=$(curl --silent --fail --show-error \
            -H "${AUTH_HEADER}" \
            -H "Content-Type: application/vnd.api+json" \
            "${url}")

        status=$(echo "${response}" | jq -r '.data.attributes.status // empty')

        case "${status}" in
            succeeded)
                echo "${response}" | jq -r '.data.attributes.download_url'
                return 0
                ;;
            failed)
                echo "ERROR: Transifex async job failed: $(echo "${response}" | jq -r '.data.attributes.errors // empty')" >&2
                return 1
                ;;
            *)
                # pending / processing — wait and retry
                sleep "${delay}"
                (( attempt++ )) || true
                ;;
        esac
    done

    echo "ERROR: Timed out waiting for Transifex download." >&2
    return 1
}

for lang in "${lang_codes[@]}"; do
    # Skip English — the key itself IS the English string.
    if [[ "${lang}" == "en" ]]; then
        log "Skipping English (source language)."
        continue
    fi

    log "Processing language: ${lang}"

    # Collect merged translations from all resources into one flat map.
    declare -A merged_translations=()

    for resource in "${resource_slugs[@]}"; do
        log "  Requesting download for resource '${resource}' / language '${lang}' …"

        # Request an async resource translation download (KEYVALUEJSON format).
        job_response=$(curl --silent --fail --show-error \
            -X POST \
            -H "${AUTH_HEADER}" \
            -H "Content-Type: application/vnd.api+json" \
            -d "{
                \"data\": {
                    \"attributes\": {
                        \"callback_url\": null,
                        \"content_encoding\": \"text\",
                        \"file_type\": \"default\",
                        \"language\": \"l:${lang}\",
                        \"mode\": \"translator\"
                    },
                    \"relationships\": {
                        \"resource\": {
                            \"data\": {
                                \"id\": \"o:${ORG}:p:${PROJECT}:r:${resource}\",
                                \"type\": \"resources\"
                            }
                        }
                    },
                    \"type\": \"resource_translations_async_downloads\"
                }
            }" \
            "${API}/resource_translations_async_downloads")

        job_id=$(echo "${job_response}" | jq -r '.data.id')
        if [[ -z "${job_id}" || "${job_id}" == "null" ]]; then
            log "  WARNING: Could not start async download for ${resource}/${lang} — skipping."
            continue
        fi

        # Poll until ready and get the download URL.
        download_url=$(poll_until_ready "${API}/resource_translations_async_downloads/${job_id}")

        # Download the raw file content (KEYVALUEJSON = flat JSON object).
        raw=$(curl --silent --fail --show-error -L \
            -H "${AUTH_HEADER}" \
            "${download_url}")

        # Merge the flat key-value pairs from this resource.
        while IFS= read -r line; do
            key=$(echo "${line}" | jq -r '.key')
            value=$(echo "${line}" | jq -r '.value')
            if [[ -n "${key}" && -n "${value}" && "${value}" != "null" ]]; then
                merged_translations["${key}"]="${value}"
            fi
        done < <(echo "${raw}" | jq -c 'to_entries[] | {key: .key, value: .value}')
    done

    # Build the output JSON object from the merged map.
    output_file="${SCRIPT_DIR}/${lang}.json"
    tmp_file="${output_file}.tmp"

    {
        echo "{"
        first=true
        for key in "${!merged_translations[@]}"; do
            value="${merged_translations[${key}]}"
            if [[ "${first}" == true ]]; then
                first=false
            else
                echo ","
            fi
            # Use jq to safely encode both key and value as JSON strings.
            printf '%s: %s' \
                "$(echo -n "${key}" | jq -Rs '.')" \
                "$(echo -n "${value}" | jq -Rs '.')"
        done
        echo ""
        echo "}"
    } > "${tmp_file}"

    # Validate & pretty-print the JSON before writing it out.
    jq '.' "${tmp_file}" > "${output_file}"
    rm -f "${tmp_file}"

    log "  Written ${output_file}"
    unset merged_translations
done

log "Done."