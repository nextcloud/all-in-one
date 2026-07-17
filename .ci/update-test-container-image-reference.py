#!/usr/bin/env python3
# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
"""Update container image digests in configured docker compose files and Dockerfiles.
The script will look for matches for `<imagename>:<tag>@sha256:<digest>` in those files and update the digest,
if a different one is found for `<imagename>:<tag>` at the registry.

Requires: skopeo
"""
import re
import subprocess
import sys

# Change this to add more files to check.
FILES = {
    "compose": [
        "php/tests/compose.yaml",
    ],
    "dockerfile": [
        "php/tests/Containers/composer/Dockerfile",
    ],
}

PREFIX = {
    "compose": "image: ",
    "dockerfile": "FROM ",
}

# Matches `<prefix><imagename>:<tag>@sha256:<digest>`
IMAGE_RE = r"{prefix}([^\s@]+:[^\s@]+)@(sha256:[a-f0-9]+)"

def run(cmd):
    """Run a command, capturing stdout/stderr. Exit with the captured stderr on failure if check is True."""
    print(f"Running command: {' '.join(cmd)}")
    result = subprocess.run(cmd, capture_output=True, text=True)
    if result.returncode != 0:
        sys.exit(f"Error: '{' '.join(cmd)}' failed!\n\nStdout: {result.stdout}\n\nStderr: {result.stderr}\n")
    return result


def update_image_digest(content, full_ref, current_digest, file_path):
    result = run([
        "skopeo", "inspect",
        "--override-os", "linux",
        "--no-tags",
        "--format", "{{.Digest}}",
        f"docker://{full_ref}",
    ])
    latest_digest = result.stdout.strip()

    msg_prefix = f"{file_path}: {full_ref}"
    if latest_digest == current_digest:
        print(f"{msg_prefix}: already up to date.")
        return content

    old = f"{full_ref}@{current_digest}"
    count = content.count(old)
    content = content.replace(old, f"{full_ref}@{latest_digest}")
    print(f"{msg_prefix}: updated {count} occurrence(s) to {latest_digest}.")
    return content


def update_file(file_path, prefix):
    with open(file_path) as f:
        content = f.read()

    images = re.findall(IMAGE_RE.format(prefix=re.escape(prefix)), content)
    if not images:
        print(f"{file_path}: no pinned image references found.")
        return

    # Run only once for every pair of file_path and image.
    unique_images = list(dict.fromkeys(images))

    for full_ref, current_digest in unique_images:
        content = update_image_digest(content, full_ref, current_digest, file_path)

    with open(file_path, "w") as f:
        f.write(content)


def main():
    run(["which", "skopeo"])

    for file_type, paths in FILES.items():
        for path in paths:
            update_file(path, PREFIX[file_type])


if __name__ == "__main__":
    main()
