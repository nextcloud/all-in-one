#!/bin/bash

DOCKER_TAG="$1"

# The logic needs the files in ./helm-chart
mv ./nextcloud-aio-helm-chart ./helm-chart

# Clean
rm -f ./helm-chart/values.yaml
rm -rf ./helm-chart/templates

# Install kompose
curl -L https://github.com/kubernetes/kompose/releases/latest/download/kompose-linux-amd64 -o kompose
chmod +x kompose
sudo mv ./kompose /usr/local/bin/kompose

# Install yq
snap install yq

set -ex

# Conversion of docker-compose
cd manual-install
cp latest.yml latest.yml.backup
cp sample.conf /tmp/
sed -i 's|^|export |' /tmp/sample.conf
# shellcheck disable=SC1091
source /tmp/sample.conf
rm /tmp/sample.conf
sed -i "s|:latest$|:$DOCKER_TAG-latest|" latest.yml
sed -i "s|\${APACHE_IP_BINDING}:||" latest.yml
sed -i '/APACHE_IP_BINDING/d' latest.yml
sed -i "s|\${APACHE_PORT}:\${APACHE_PORT}/|$APACHE_PORT:$APACHE_PORT/|" latest.yml
sed -i "s|\${TALK_PORT}:\${TALK_PORT}/|$TALK_PORT:$TALK_PORT/|g" latest.yml
sed -i "s|- \${APACHE_PORT}|- $APACHE_PORT|" latest.yml
sed -i "s|- \${TALK_PORT}|- $TALK_PORT|" latest.yml
sed -i "s|\${NEXTCLOUD_DATADIR}|$NEXTCLOUD_DATADIR|" latest.yml
sed -i "/name: nextcloud-aio/,$ d" latest.yml
sed -i "/NEXTCLOUD_DATADIR/d" latest.yml
sed -i "/\${NEXTCLOUD_MOUNT}/d" latest.yml
sed -i "/^volumes:/a\ \ nextcloud_aio_nextcloud_trusted_cacerts:\n \ \ \ \ name: nextcloud_aio_nextcloud_trusted_cacerts" latest.yml
sed -i "s|\${NEXTCLOUD_TRUSTED_CACERTS_DIR}:|nextcloud_aio_nextcloud_trusted_cacerts:|g#" latest.yml
sed -i 's|\${|{{ .Values.|g' latest.yml
sed -i 's|}| }}|g' latest.yml
yq -i 'del(.services.[].profiles)' latest.yml
# Delete read_only and tmpfs setting while https://github.com/kubernetes/kubernetes/issues/48912 is not fixed
yq -i 'del(.services.[].read_only)' latest.yml
yq -i 'del(.services.[].tmpfs)' latest.yml
cat latest.yml
kompose convert -c -f latest.yml --namespace nextcloud-aio-namespace
cd latest

if [ -f ./templates/manual-install-nextcloud-aio-networkpolicy.yaml ]; then
    mv ./templates/manual-install-nextcloud-aio-networkpolicy.yaml ./templates/nextcloud-aio-networkpolicy.yaml
fi
# shellcheck disable=SC1083
find ./ -name '*networkpolicy.yaml' -exec sed -i "s|manual-install-nextcloud-aio|nextcloud-aio|" \{} \; 
cat << EOL > /tmp/initcontainers
      initContainers:
        - name: init-volumes
          image: alpine
          command:
            - chmod
            - "777"
          volumeMountsInitContainer:
EOL
cat << EOL > /tmp/initcontainers.database
      initContainers:
        - name: init-subpath
          image: alpine
          command:
            - mkdir
            - "-p"
            - /nextcloud-aio-database/data
          volumeMountsInitContainer:
        - name: init-volumes
          image: alpine
          command:
            - chown
            - 999:999
            - "-R"
          volumeMountsInitContainer:
EOL
# shellcheck disable=SC1083
DEPLOYMENTS="$(find ./ -name '*deployment.yaml')"
mapfile -t DEPLOYMENTS <<< "$DEPLOYMENTS"
for variable in "${DEPLOYMENTS[@]}"; do
    if grep -q volumeMounts "$variable"; then
        if ! echo "$variable" | grep -q database; then
            sed -i "/^    spec:/r /tmp/initcontainers" "$variable"
        else
            sed -i "/^    spec:/r /tmp/initcontainers.database" "$variable"
        fi
        volumeNames="$(grep -A1 mountPath "$variable" | grep -v mountPath | sed 's|.*name: ||' | sed '/^--$/d')"
        mapfile -t volumeNames <<< "$volumeNames"
        for volumeName in "${volumeNames[@]}"; do
            # The Nextcloud container runs as root user and sets the correct permissions automatically for the data-dir if the www-data user cannot write to it
            if [ "$volumeName" != "nextcloud-aio-nextcloud-data" ]; then
                sed -i "/^.*volumeMountsInitContainer:/i\ \ \ \ \ \ \ \ \ \ \ \ - /$volumeName" "$variable"
                sed -i "/volumeMountsInitContainer:/a\ \ \ \ \ \ \ \ \ \ \ \ - name: $volumeName\n\ \ \ \ \ \ \ \ \ \ \ \ \ \ mountPath: /$volumeName" "$variable"
                # Workaround for the database volume
                if [ "$volumeName" = nextcloud-aio-database ]; then
                    sed -i "/mountPath: \/var\/lib\/postgresql\/data/a\ \ \ \ \ \ \ \ \ \ \ \ \ \ subPath: data" "$variable"
                fi
                
            fi
        done
        sed -i "s|volumeMountsInitContainer|volumeMounts|" "$variable"
        if grep -q claimName "$variable"; then
            claimNames="$(grep claimName "$variable")"
            mapfile -t claimNames <<< "$claimNames"
            for claimName in "${claimNames[@]}"; do
                if grep -A1 "^$claimName$" "$variable" | grep -q "readOnly: true"; then
                    sed -i "/^$claimName$/{n;d}" "$variable"
                fi
            done
        fi
    fi
done
# shellcheck disable=SC1083
find ./ -name '*.yaml' -exec sed -i "s|nextcloud-aio-namespace|\{\{ .Values.NAMESPACE \}\}|" \{} \; 
# shellcheck disable=SC1083
find ./ -name '*service.yaml' -exec sed -i "/^status:/,$ d" \{} \; 
# shellcheck disable=SC1083
find ./ -name '*deployment.yaml' -exec sed -i "s|manual-install-nextcloud-aio|nextcloud-aio|" \{} \; 
# shellcheck disable=SC1083
find ./ -name '*deployment.yaml' -exec sed -i "/medium: Memory/d" \{} \;
# shellcheck disable=SC1083
find ./ -name '*deployment.yaml' -exec sed -i "s|emptyDir:|emptyDir: \{\}|" \{} \; 
# shellcheck disable=SC1083
find ./ -name '*deployment.yaml' -exec sed -i "/hostPort:/d" \{} \; 
# shellcheck disable=SC1083
find ./ -name '*persistentvolumeclaim.yaml' -exec sed -i "s|ReadOnlyMany|ReadWriteOnce|" \{} \;   
# shellcheck disable=SC1083
find ./ -name '*persistentvolumeclaim.yaml' -exec sed -i "/accessModes:/i\ \ {{- if .Values.STORAGE_CLASS }}" \{} \;  
# shellcheck disable=SC1083
find ./ -name '*persistentvolumeclaim.yaml' -exec sed -i "/accessModes:/i\ \ storageClassName: {{ .Values.STORAGE_CLASS }}" \{} \; 
# shellcheck disable=SC1083
find ./ -name '*persistentvolumeclaim.yaml' -exec sed -i "/accessModes:/i\ \ {{- end }}" \{} \; 
# shellcheck disable=SC1083
find ./ -name '*deployment.yaml' -exec sed -i "/restartPolicy:/d" \{} \;  
# shellcheck disable=SC1083
find ./ -name '*apache*' -exec sed -i "s|$APACHE_PORT|{{ .Values.APACHE_PORT }}|" \{} \;
# shellcheck disable=SC1083
find ./ -name '*talk*' -exec sed -i "s|$TALK_PORT|{{ .Values.TALK_PORT }}|" \{} \;
# shellcheck disable=SC1083
find ./ -name '*apache-service.yaml' -exec sed -i "/^spec:/a\ \ type: LoadBalancer" \{} \;
# shellcheck disable=SC1083
find ./ -name '*talk-service.yaml' -exec sed -i "/^spec:/a\ \ type: LoadBalancer" \{} \;
echo '---' > /tmp/talk-service.copy
# shellcheck disable=SC1083
find ./ -name '*talk-service.yaml' -exec cat \{} \; >> /tmp/talk-service.copy
sed -i 's|name: nextcloud-aio-talk|name: nextcloud-aio-talk-public|' /tmp/talk-service.copy
# shellcheck disable=SC1083
INTERNAL_TALK_PORTS="$(find ./ -name '*talk-deployment.yaml' -exec grep -oP 'containerPort: [0-9]+' \{} \;)"
mapfile -t INTERNAL_TALK_PORTS <<< "$INTERNAL_TALK_PORTS"
for port in "${INTERNAL_TALK_PORTS[@]}"; do
    port="$(echo "$port" | grep -oP '[0-9]+')"
    sed -i "/$port/d" /tmp/talk-service.copy
done
echo '---' >>  /tmp/talk-service.copy
# shellcheck disable=SC1083
find ./ -name '*talk-service.yaml' -exec grep -v '{{ .Values.TALK.*}}\|protocol: UDP\|type: LoadBalancer' \{} \; >> /tmp/talk-service.copy
# shellcheck disable=SC1083
find ./ -name '*talk-service.yaml' -exec mv /tmp/talk-service.copy \{} \;
# shellcheck disable=SC1083
find ./ -name '*.yaml' -exec sed -i "s|'{{|\"{{|g;s|}}'|}}\"|g" \{} \; 
# shellcheck disable=SC1083
find ./ -name '*.yaml' -exec sed -i "/type: Recreate/d" \{} \; 
# shellcheck disable=SC1083
find ./ -name '*.yaml' -exec sed -i "/strategy:/d" \{} \; 
# shellcheck disable=SC1083
find ./ \( -not -name '*service.yaml' -name '*.yaml' \) -exec sed -i "/^status:/d" \{} \; 
# shellcheck disable=SC1083
find ./ \( -not -name '*persistentvolumeclaim.yaml' -name '*.yaml' \) -exec sed -i "/resources:/d" \{} \; 
# shellcheck disable=SC1083
find ./ -name '*.yaml' -exec sed -i "/creationTimestamp: null/d" \{} \; 
VOLUMES="$(find ./ -name '*persistentvolumeclaim.yaml' | sed 's|-persistentvolumeclaim.yaml||g;s|.*nextcloud-aio-||g' | sort)"
mapfile -t VOLUMES <<< "$VOLUMES"
for variable in "${VOLUMES[@]}"; do
    name="$(echo "$variable" | sed 's|-|_|g' | tr '[:lower:]' '[:upper:]')_STORAGE_SIZE"
    VOLUME_VARIABLE+=("$name")
    # shellcheck disable=SC1083
    find ./ -name "*nextcloud-aio-$variable-persistentvolumeclaim.yaml" -exec sed -i "s|storage: 100Mi|storage: {{ .Values.$name }}|" \{} \; 
done

cd ../
mkdir -p ../helm-chart/
rm latest/Chart.yaml
rm latest/README.md
mv latest/* ../helm-chart/
rm -r latest
rm latest.yml
mv latest.yml.backup latest.yml

# Get version of AIO
AIO_VERSION="$(grep 'Nextcloud AIO ' ../php/templates/containers.twig | grep -oP '[0-9]+.[0-9]+.[0-9]+')"
sed -i "s|^version:.*|version: $AIO_VERSION|" ../helm-chart/Chart.yaml

# Conversion of sample.conf
cp sample.conf /tmp/
sed -i 's|"||g' /tmp/sample.conf
sed -i 's|=|: |' /tmp/sample.conf
sed -i 's|= |: |' /tmp/sample.conf
sed -i '/^NEXTCLOUD_DATADIR/d' /tmp/sample.conf
sed -i '/^APACHE_IP_BINDING/d' /tmp/sample.conf
sed -i '/^NEXTCLOUD_MOUNT/d' /tmp/sample.conf
sed -i '/^IPV6_NETWORK/d' /tmp/sample.conf
sed -i '/_ENABLED.*/s/ yes / "yes" /' /tmp/sample.conf
sed -i '/_ENABLED.*/s/ no / "no" /' /tmp/sample.conf
sed -i 's|^NEXTCLOUD_TRUSTED_CACERTS_DIR: .*|NEXTCLOUD_TRUSTED_CACERTS_DIR:        # Setting this to any value allows to automatically import root certificates into the Nextcloud container|' /tmp/sample.conf
sed -i 's|10737418240|"10737418240"|' /tmp/sample.conf
# shellcheck disable=SC2129
echo "NAMESPACE: default        # By changing this, you can adjust the namespace of the installation which allows to install multiple instances on one kubernetes cluster" >> /tmp/sample.conf
# shellcheck disable=SC2129
echo "" >> /tmp/sample.conf
# shellcheck disable=SC2129
echo 'STORAGE_CLASS:        # By setting this, you can adjust the storage class for your volumes' >> /tmp/sample.conf
for variable in "${VOLUME_VARIABLE[@]}"; do
    echo "$variable: 1Gi       # You can change the size of the $(echo "$variable" | sed 's|_STORAGE_SIZE||;s|_|-|g' | tr '[:upper:]' '[:lower:]') volume that default to 1Gi with this value" >> /tmp/sample.conf
done
mv /tmp/sample.conf ../helm-chart/values.yaml

ENABLED_VARIABLES="$(grep -oP '^[A-Z_]+_ENABLED' ../helm-chart/values.yaml)"
mapfile -t ENABLED_VARIABLES <<< "$ENABLED_VARIABLES"

cd ../helm-chart/
for variable in "${ENABLED_VARIABLES[@]}"; do
    name="$(echo "$variable" | sed 's|_ENABLED||g;s|_|-|g' | tr '[:upper:]' '[:lower:]')"
    # shellcheck disable=SC1083
    find ./ -name "*nextcloud-aio-$name-deployment.yaml" -exec sed -i "1i\\{{- if eq .Values.$variable \"yes\" }}" \{} \; 
    # shellcheck disable=SC1083
    find ./ -name "*nextcloud-aio-$name-deployment.yaml" -exec sed -i "$ a {{- end }}" \{} \; 
    # shellcheck disable=SC1083
    find ./ -name "*nextcloud-aio-$name-service.yaml" -exec sed -i "1i\\{{- if eq .Values.$variable \"yes\" }}" \{} \; 
    # shellcheck disable=SC1083
    find ./ -name "*nextcloud-aio-$name-service.yaml" -exec sed -i "$ a {{- end }}" \{} \; 
done

chmod 777 -R ./

# Seems like the dir needs to match the name of the chart
cd ../
rm -rf ./nextcloud-aio-helm-chart
mv ./helm-chart ./nextcloud-aio-helm-chart

set +ex
