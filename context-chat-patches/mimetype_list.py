#
# SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
#
#
# LOCAL PATCH of context_chat_backend 5.4.1's mimetype_list.py, bind-mounted over the
# in-image copy by docker-compose.yml. Verbatim upstream except the LOCAL PATCH block.
#
# Upstream sha256 this was forked from:
#   16c702784568c57874cd4b7d62d213cb9840dbd328be48d4b80b0fd7c63594d3
# Re-diff against the image's copy after bumping the context_chat_backend tag.
#
SUPPORTED_MIMETYPES = [
	'text/plain',
	'text/markdown',
	'application/json',
	'application/pdf',
	'text/csv',
	'application/epub+zip',
	'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	'application/vnd.ms-powerpoint',
	'application/vnd.openxmlformats-officedocument.presentationml.presentation',
	'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
	'application/vnd.oasis.opendocument.spreadsheet',
	'application/vnd.ms-excel.sheet.macroEnabled.12',
	# LOCAL PATCH: legacy binary .xls (BIFF). This list is the hard gate -- types.py's
	# validate_type() raises 'Unsupported file type' for anything not in it, which the
	# backend logs only as the generic 'No valid files or providers found in the current
	# batch'. Adding the mimetype to doc_loader.py's parser map alone is NOT enough;
	# the source is rejected before any loader runs.
	'application/vnd.ms-excel',
	'application/vnd.oasis.opendocument.text',
	'text/rtf',
	'text/x-rst',
	'application/xml',
	'message/rfc822',
	'application/vnd.ms-outlook',
	'text/org',
]
