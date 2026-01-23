#!/usr/bin/env bash

awk '/^    public [^f][^u][^n]/ { sub(/\$/, "", $3); print $3 }' php/src/Data/ConfigurationManager.php | sort
