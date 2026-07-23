#!/bin/bash

VARIANT="develop"
CONTAINERS=()

# Parse flags first
while [[ $# -gt 0 && $1 == --* ]]; do
  case $1 in
    --variant)
      VARIANT="$2"
      shift 2
      ;;
    *)
      echo "Unknown option: $1"
      exit 1
      ;;
  esac
done

# Remaining arguments are containers
CONTAINERS=("$@")

if [ ${#CONTAINERS[@]} -eq 0 ]; then
    echo "Usage: $0 [--variant develop|beta] <container1> [container2] [container3] ..."
    echo "Example: $0 --variant beta apache mastercontainer"
    exit 1
fi

# Change to project root
cd "$(dirname "$0")/.." || exit 1

for container in "${CONTAINERS[@]}"; do
  if [[ $container == "mastercontainer" ]]; then
    TAG="all-in-one"
  else
    TAG="aio-$container"
  fi

  if [[ $container == "mastercontainer" || $container == "nextcloud" ]]; then
      CONTEXT="."
  else
      CONTEXT="Containers/$container"
  fi

  docker buildx build --file Containers/$container/Dockerfile --tag ghcr.io/nextcloud-releases/"$TAG":"$VARIANT" --load $CONTEXT
done

