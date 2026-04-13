#!/bin/bash

# Print out clamav version for compliance reasons
clamscan --version

# Apply AIO_LOG_LEVEL to ClamAV by copying the read-only config files to /tmp
# and applying the appropriate LogVerbose / Debug settings there.
# supervisord.conf is also copied so its loglevel can be adjusted.
cp /etc/clamav/clamd.conf /tmp/clamd.conf
cp /etc/clamav/freshclam.conf /tmp/freshclam.conf
cp /supervisord.conf /tmp/supervisord.conf

# Point supervisord to the /tmp copies of the ClamAV configs
sed -i "s|/etc/clamav/clamd.conf|/tmp/clamd.conf|g" /tmp/supervisord.conf

case "${AIO_LOG_LEVEL:-warning}" in
    debug)
        sed -i "s|#\?LogVerbose.*|LogVerbose yes|" /tmp/clamd.conf
        sed -i "s|#\?Debug.*|Debug yes|" /tmp/clamd.conf
        sed -i "s|#\?LogVerbose.*|LogVerbose yes|" /tmp/freshclam.conf
        SUPERVISORD_LOG_LEVEL="debug"
        ;;
    info)
        sed -i "s|#\?LogVerbose.*|LogVerbose yes|" /tmp/clamd.conf
        sed -i "s|#\?Debug.*|Debug no|" /tmp/clamd.conf
        sed -i "s|#\?LogVerbose.*|LogVerbose yes|" /tmp/freshclam.conf
        SUPERVISORD_LOG_LEVEL="info"
        ;;
    warning|error|*)
        sed -i "s|#\?LogVerbose.*|LogVerbose no|" /tmp/clamd.conf
        sed -i "s|#\?Debug.*|Debug no|" /tmp/clamd.conf
        sed -i "s|#\?LogVerbose.*|LogVerbose no|" /tmp/freshclam.conf
        SUPERVISORD_LOG_LEVEL="${AIO_LOG_LEVEL:-warn}"
        # supervisord uses 'warn' not 'warning'
        [ "$SUPERVISORD_LOG_LEVEL" = "warning" ] && SUPERVISORD_LOG_LEVEL="warn"
        ;;
esac
sed -i "s|loglevel=.*|loglevel=$SUPERVISORD_LOG_LEVEL|" /tmp/supervisord.conf

echo "Clamav started"

exec /usr/bin/supervisord -c /tmp/supervisord.conf
