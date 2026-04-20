#!/bin/bash

wget -q -O /dev/null "http://127.0.0.1:${PORT}/health" || exit 1
