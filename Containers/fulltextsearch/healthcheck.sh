#!/bin/bash

curl -fs "http://127.0.0.1:9200/_cluster/health" | grep -qv '"status":"red"' || exit 1
