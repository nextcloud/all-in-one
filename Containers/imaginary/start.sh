#!/bin/bash
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

echo "Imaginary has started"
if [ -z "$IMAGINARY_SECRET" ]; then
    imaginary -return-size -max-allowed-resolution 222.2 "$@"
else
    imaginary -return-size -max-allowed-resolution 222.2 -key "$IMAGINARY_SECRET" "$@"
fi
