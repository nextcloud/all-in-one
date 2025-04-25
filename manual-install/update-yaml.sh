#!/bin/bash -ex

type {jq,sudo} || { echo "Commands not found. Please install them"; exit 127; }

jq -c . ./php/containers.json > /tmp/containers.json
sed -i 's|aio_services_v1|services|g' /tmp/containers.json
sed -i 's|","destination":"|:|g' /tmp/containers.json
sed -i 's|","writeable":false|:ro"|g' /tmp/containers.json
sed -i 's|","writeable":true|:rw"|g' /tmp/containers.json
sed -i 's|","port_number":"|:|g' /tmp/containers.json
sed -i 's|","protocol":"|/|g' /tmp/containers.json
sed -i 's|"ip_binding":":|"ip_binding":"|g' /tmp/containers.json
cat /tmp/containers.json
OUTPUT="$(cat /tmp/containers.json)"
OUTPUT="$(echo "$OUTPUT" | jq 'del(.services[].internal_port)')"
OUTPUT="$(echo "$OUTPUT" | jq 'del(.services[].secrets)')"
OUTPUT="$(echo "$OUTPUT" | jq 'del(.services[].ui_secrets)')"
OUTPUT="$(echo "$OUTPUT" | jq 'del(.services[].devices)')"
OUTPUT="$(echo "$OUTPUT" | jq 'del(.services[].enable_nvidia_gpu)')"
OUTPUT="$(echo "$OUTPUT" | jq 'del(.services[].backup_volumes)')"
OUTPUT="$(echo "$OUTPUT" | jq 'del(.services[].nextcloud_exec_commands)')"
OUTPUT="$(echo "$OUTPUT" | jq 'del(.services[].image_tag)')"
OUTPUT="$(echo "$OUTPUT" | jq 'del(.services[].networks)')"
OUTPUT="$(echo "$OUTPUT" | jq 'del(.services[].documentation)')"
OUTPUT="$(echo "$OUTPUT" | jq 'del(.services[] | select(.container_name == "nextcloud-aio-watchtower"))')"
OUTPUT="$(echo "$OUTPUT" | jq 'del(.services[] | select(.container_name == "nextcloud-aio-domaincheck"))')"
OUTPUT="$(echo "$OUTPUT" | jq 'del(.services[] | select(.container_name == "nextcloud-aio-borgbackup"))')"
OUTPUT="$(echo "$OUTPUT" | jq 'del(.services[] | select(.container_name == "nextcloud-aio-docker-socket-proxy"))')"
OUTPUT="$(echo "$OUTPUT" | jq '.services[] |= if has("depends_on") then .depends_on |= if contains(["nextcloud-aio-docker-socket-proxy"]) then del(.[index("nextcloud-aio-docker-socket-proxy")]) else . end else . end')"
OUTPUT="$(echo "$OUTPUT" | jq '.services[] |= if has("depends_on") then .depends_on |= map({ (.): { "condition": "service_started", "required": false } }) else . end' | jq '.services[] |= if has("depends_on") then .depends_on |= reduce .[] as $item ({}; . + $item) else . end')"

sudo snap install yq
mkdir -p ./manual-install
echo "$OUTPUT" | yq -P > ./manual-install/containers.yml

cd manual-install || exit
sed -i "s|'||g" containers.yml
sed -i '/display_name:/d' containers.yml
sed -i '/THIS_IS_AIO/d' containers.yml
sed -i "s|%COLLABORA_SECCOMP_POLICY% ||g" containers.yml
sed -i '/stop_grace_period:/s/$/s/' containers.yml
sed -i '/: \[\]/d' containers.yml
sed -i 's|- source: |- |' containers.yml
sed -i 's|- ip_binding: |- |' containers.yml
sed -i '/AIO_TOKEN/d' containers.yml
sed -i '/AIO_URL/d' containers.yml
sed -i '/DOCKER_SOCKET_PROXY_ENABLED/d' containers.yml
sed -i '/ADDITIONAL_TRUSTED_PROXY/d' containers.yml

TCP="$(grep -oP '[%A-Z0-9_]+/tcp' containers.yml | sort -u)"
mapfile -t TCP <<< "$TCP"
for port in "${TCP[@]}" 
do
    solve_port="${port%%/tcp}"
    sed -i "s|$solve_port/tcp|$solve_port:$solve_port/tcp|" containers.yml
done

UDP="$(grep -oP '[%A-Z0-9_]+/udp' containers.yml | sort -u)"
mapfile -t UDP <<< "$UDP"
for port in "${UDP[@]}"
do
    solve_port="${port%%/udp}"
    sed -i "s|$solve_port/udp|$solve_port:$solve_port/udp|" containers.yml
done

rm -f sample.conf
VARIABLES="$(grep -oP '%[A-Z_a-z0-6]+%' containers.yml | sort -u)"
mapfile -t VARIABLES <<< "$VARIABLES"
for variable in "${VARIABLES[@]}"
do
    # shellcheck disable=SC2001
    sole_variable="$(echo "$variable" | sed 's|%||g')"
    echo "$sole_variable=" >> sample.conf
    sed -i "s|$variable|\${$sole_variable}|g" containers.yml
done

sed -i 's|_ENABLED=|_ENABLED="no"          # Setting this to "yes" (with quotes) enables the option in Nextcloud automatically.|' sample.conf
sed -i 's|CLAMAV_ENABLED=no.*|CLAMAV_ENABLED="no"          # Setting this to "yes" (with quotes) enables the option in Nextcloud automatically.|' sample.conf
sed -i 's|TALK_ENABLED=no|TALK_ENABLED="yes"|' sample.conf
sed -i 's|COLLABORA_ENABLED=no|COLLABORA_ENABLED="yes"|' sample.conf
sed -i 's|COLLABORA_DICTIONARIES=|COLLABORA_DICTIONARIES="de_DE en_GB en_US es_ES fr_FR it nl pt_BR pt_PT ru"        # You can change this in order to enable other dictionaries for collabora|' sample.conf
sed -i 's|NEXTCLOUD_DATADIR=|NEXTCLOUD_DATADIR=nextcloud_aio_nextcloud_data          # You can change this to e.g. "/mnt/ncdata" to map it to a location on your host. It needs to be adjusted before the first startup and never afterwards!|' sample.conf
sed -i 's|NEXTCLOUD_MOUNT=|NEXTCLOUD_MOUNT=/mnt/          # This allows the Nextcloud container to access directories on the host. It must never be equal to the value of NEXTCLOUD_DATADIR!|' sample.conf
sed -i 's|NEXTCLOUD_UPLOAD_LIMIT=|NEXTCLOUD_UPLOAD_LIMIT=16G          # This allows to change the upload limit of the Nextcloud container|' sample.conf
sed -i 's|NEXTCLOUD_MEMORY_LIMIT=|NEXTCLOUD_MEMORY_LIMIT=512M          # This allows to change the PHP memory limit of the Nextcloud container|' sample.conf
sed -i 's|APACHE_MAX_SIZE=|APACHE_MAX_SIZE=17179869184          # This needs to be an integer and in sync with NEXTCLOUD_UPLOAD_LIMIT|' sample.conf
sed -i 's|NEXTCLOUD_MAX_TIME=|NEXTCLOUD_MAX_TIME=3600          # This allows to change the upload time limit of the Nextcloud container|' sample.conf
sed -i 's|NEXTCLOUD_TRUSTED_CACERTS_DIR=|NEXTCLOUD_TRUSTED_CACERTS_DIR=/usr/local/share/ca-certificates/my-custom-ca          # Nextcloud container will trust all the Certification Authorities, whose certificates are included in the given directory.|' sample.conf
sed -i 's|UPDATE_NEXTCLOUD_APPS=|UPDATE_NEXTCLOUD_APPS="no"          # When setting to "yes" (with quotes), it will automatically update all installed Nextcloud apps upon container startup on saturdays.|' sample.conf
sed -i 's|APACHE_PORT=|APACHE_PORT=443          # Changing this to a different value than 443 will allow you to run it behind a web server or reverse proxy (like Apache, Nginx, Caddy, Cloudflare Tunnel and else).|' sample.conf
sed -i 's|APACHE_IP_BINDING=|APACHE_IP_BINDING=0.0.0.0          # This can be changed to e.g. 127.0.0.1 if you want to run AIO behind a web server or reverse proxy (like Apache, Nginx, Caddy, Cloudflare Tunnel and else) and if that is running on the same host and using localhost to connect|' sample.conf
sed -i 's|TALK_PORT=|TALK_PORT=3478          # This allows to adjust the port that the talk container is using. It should be set to something higher than 1024! Otherwise it might not work!|' sample.conf
sed -i 's|NC_DOMAIN=|NC_DOMAIN=yourdomain.com          # TODO! Needs to be changed to the domain that you want to use for Nextcloud.|' sample.conf
sed -i 's|NEXTCLOUD_PASSWORD=|NEXTCLOUD_PASSWORD=          # TODO! This is the password of the initially created Nextcloud admin with username "admin".|' sample.conf
sed -i 's|TIMEZONE=|TIMEZONE=Europe/Berlin          # TODO! This is the timezone that your containers will use.|' sample.conf
sed -i 's|COLLABORA_SECCOMP_POLICY=|COLLABORA_SECCOMP_POLICY=--o:security.seccomp=true          # Changing the value to false allows to disable the seccomp feature of the Collabora container.|' sample.conf
sed -i 's|FULLTEXTSEARCH_JAVA_OPTIONS=|FULLTEXTSEARCH_JAVA_OPTIONS="-Xms512M -Xmx512M"          # Allows to adjust the fulltextsearch java options.|' sample.conf
sed -i 's|NEXTCLOUD_STARTUP_APPS=|NEXTCLOUD_STARTUP_APPS="deck twofactor_totp tasks calendar contacts notes"        # Allows to modify the Nextcloud apps that are installed on starting AIO the first time|' sample.conf
sed -i 's|NEXTCLOUD_ADDITIONAL_APKS=|NEXTCLOUD_ADDITIONAL_APKS=imagemagick        # This allows to add additional packages to the Nextcloud container permanently. Default is imagemagick but can be overwritten by modifying this value.|' sample.conf
sed -i 's|NEXTCLOUD_ADDITIONAL_PHP_EXTENSIONS=|NEXTCLOUD_ADDITIONAL_PHP_EXTENSIONS=imagick        # This allows to add additional php extensions to the Nextcloud container permanently. Default is imagick but can be overwritten by modifying this value.|' sample.conf
sed -i 's|INSTALL_LATEST_MAJOR=|INSTALL_LATEST_MAJOR=no        # Setting this to yes will install the latest Major Nextcloud version upon the first installation|' sample.conf
sed -i 's|REMOVE_DISABLED_APPS=|REMOVE_DISABLED_APPS=yes        # Setting this to no keep Nextcloud apps that are disabled via their switch and not uninstall them if they should be installed in Nextcloud.|' sample.conf
sed -i 's|=$|=          # TODO! This needs to be a unique and good password!|' sample.conf

grep  '# TODO!' sample.conf > todo.conf
grep -v '# TODO!\|_ENABLED' sample.conf > temp.conf
grep '_ENABLED' sample.conf > enabled.conf
cat todo.conf > sample.conf
# shellcheck disable=SC2129
echo '' >> sample.conf
cat enabled.conf >> sample.conf
echo '' >> sample.conf
cat temp.conf >> sample.conf
rm todo.conf temp.conf enabled.conf
cat sample.conf

OUTPUT="$(cat containers.yml)"
NAMES="$(grep -oP "container_name:.*" containers.yml | grep -oP 'nextcloud-aio.*')"
mapfile -t NAMES <<< "$NAMES"
for name in "${NAMES[@]}"
do
    OUTPUT="$(echo "$OUTPUT" | sed "/container_name.*$name$/i\ \ $name:")"
    if [ "$name" != "nextcloud-aio-apache" ]; then
        OUTPUT="$(echo "$OUTPUT" | sed "/^  $name:/i\ ")"
    fi
done

echo "$OUTPUT" > containers.yml

sed -i '/container_name/d' containers.yml
sed -i 's|^ $||' containers.yml

# Additional config for collabora
cat << EOL > /tmp/additional-collabora.config
    command: \${ADDITIONAL_COLLABORA_OPTIONS}
EOL
sed -i "/^  nextcloud-aio-collabora:/r /tmp/additional-collabora.config" containers.yml
sed -i "/^COLLABORA_DICTIONARIES.*/i ADDITIONAL_COLLABORA_OPTIONS=['--o:security.seccomp=true']          # You can add additional collabora options here by using the array syntax." sample.conf

VOLUMES="$(grep -oP 'nextcloud_aio_[a-z_]+' containers.yml | sort -u)"
mapfile -t VOLUMES <<< "$VOLUMES"
echo "" >> containers.yml
echo "volumes:" >> containers.yml
for volume in "${VOLUMES[@]}" "nextcloud_aio_nextcloud_data"
do
    cat << VOLUMES >> containers.yml
  $volume:
    name: $volume
VOLUMES
done

cat << NETWORK >> containers.yml

networks:
  default:
    driver: bridge
NETWORK

mv containers.yml latest.yml
sed -i "/image:/s/$/:latest/" latest.yml
sed -i 's/\( *- \(\w*\)\)=\${\2\}/\1/' latest.yml

set +ex
