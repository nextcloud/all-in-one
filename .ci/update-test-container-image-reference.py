#!/usr/bin/env python3
# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
"""Update container image digests in php/tests/compose.yaml.

Requires: skopeo
"""
import re
import subprocess
import sys

COMPOSE_FILE = "php/tests/compose.yaml"
IMAGES = [
    "docker.io/library/node",
    "mcr.microsoft.com/playwright",
]

if subprocess.run(["which", "skopeo"], capture_output=True).returncode != 0:
    sys.exit("Error: skopeo is not installed. Install it from https://github.com/containers/skopeo#installation")


def update_image_digest(content, image_name):
    m = re.search(fr"image: ({re.escape(image_name)}:[^\s@]+)(?:@(sha256:[a-f0-9]+))?", content)
    if not m:
        sys.exit(f"Image {image_name} not found in {COMPOSE_FILE}")

    full_ref, current_digest = m.group(1), m.group(2)

    result = subprocess.run(
        [
            "skopeo", "inspect",
            "--override-os", "linux",
            "--no-tags",
            "--format", "{{.Digest}}",
            f"docker://{full_ref}",
        ],
        capture_output=True, text=True, check=True,
    )
    latest_digest = result.stdout.strip()

    if latest_digest == current_digest:
        print(f"{full_ref}: already up to date.")
        return content

    old = f"{full_ref}@{current_digest}" if current_digest else full_ref
    count = content.count(old)
    content = content.replace(old, f"{full_ref}@{latest_digest}")
    print(f"{full_ref}: updated {count} occurrence(s) to {latest_digest}.")
    return content


def main():
    with open(COMPOSE_FILE) as f:
        content = f.read()

    for image in IMAGES:
        content = update_image_digest(content, image)

    with open(COMPOSE_FILE, "w") as f:
        f.write(content)


if __name__ == "__main__":
    main()
