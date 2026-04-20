#!/bin/bash

nc -z 127.0.0.1 "$PORT" || exit 1
