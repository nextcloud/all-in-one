#!/bin/bash

if [ -z "$ADDITIONAL_APKS" ]; then
    echo "ADDITIONAL_APKS were not specified!"
    exit 1
fi

# TODO: Needs some logic to only run if not already run when the container starts

if ! apk add --update --no-cache "$ADDITIONAL_APKS"; then
    echo "Could not install all specified dependencies '$ADDITIONAL_APKS'"
    exit 1
else
    echo "Added '$ADDITIONAL_APKS' into the container"
fi

# TODO: do something which makes sure that it does not run when the container is not recreated
