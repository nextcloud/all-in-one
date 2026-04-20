#!/bin/bash

curl -fs "http://127.0.0.1:9200/_cluster/health?filter_path=status" | grep -qE '"status":"(green|yellow)"' || exit 1
