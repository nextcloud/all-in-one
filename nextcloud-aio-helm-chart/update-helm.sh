#!/bin/bash

[ -z "$1" ] && { echo "Error: Docker tag is not specified. Usage: ./nextcloud-aio-helm-chart/update-helm.sh <Docker tag>"; exit 2; }

DOCKER_TAG="$1"

# The logic needs the files in ./helm-chart
cp -r ./nextcloud-aio-helm-chart ./helm-chart

# Clean
rm -f ./helm-chart/values.yaml
rm -rf ./helm-chart/templates

# Install kompose
curl -L https://github.com/kubernetes/kompose/releases/latest/download/kompose-linux-amd64 -o kompose
chmod +x kompose
sudo mv ./kompose /usr/local/bin/kompose

# Install yq
sudo snap install yq

set -ex

# Conversion of docker-compose
cd manual-install
cp latest.yml latest.yml.backup

sed -i -E '/^( *- )(NET_RAW|SYS_NICE|MKNOD|SYS_ADMIN)$/!s/( *- )([A-Z_]+)$/\1\2=${\2}/' latest.yml
cp sample.conf /tmp/
sed -i 's|^|export |' /tmp/sample.conf
# shellcheck disable=SC1091
source /tmp/sample.conf
rm /tmp/sample.conf
sed -i '/OVERWRITEHOST/d' latest.yml
sed -i "s|:latest$|:$DOCKER_TAG|" latest.yml
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
sed -i 's/\${/{{ .Values./g; s/}/ }}/g' latest.yml
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
          image: "{{ .Values.IMAGE_MIRROR_PREFIX }}{{ .Values.ALPINE_IMAGE_ORG }}alpine"
          command:
            - chmod
            - "777"
          volumeMountsInitContainer:
EOL
cat << EOL > /tmp/initcontainers.database
      initContainers:
        - name: init-subpath
          image: "{{ .Values.IMAGE_MIRROR_PREFIX }}{{ .Values.ALPINE_IMAGE_ORG }}alpine"
          command:
            - mkdir
            - "-p"
            - /nextcloud-aio-database/data
          volumeMountsInitContainer:
        - name: init-volumes
          image: "{{ .Values.IMAGE_MIRROR_PREFIX }}{{ .Values.ALPINE_IMAGE_ORG }}alpine"
          command:
            - chown
            - 999:999
            - "-R"
          volumeMountsInitContainer:
EOL
cat << EOL > /tmp/initcontainers.clamav
      initContainers:
        - name: init-subpath
          image: "{{ .Values.IMAGE_MIRROR_PREFIX }}{{ .Values.ALPINE_IMAGE_ORG }}alpine"
          command:
            - mkdir
            - "-p"
            - /nextcloud-aio-clamav/data
          volumeMountsInitContainer:
        - name: init-volumes
          image: "{{ .Values.IMAGE_MIRROR_PREFIX }}{{ .Values.ALPINE_IMAGE_ORG }}alpine"
          command:
            - chown
            - 100:100
            - "-R"
          volumeMountsInitContainer:
EOL
cat << EOL > /tmp/initcontainers.nextcloud
      initContainers:
        - name: "delete-lost-found"
          image: "{{ .Values.IMAGE_MIRROR_PREFIX }}{{ .Values.ALPINE_IMAGE_ORG }}alpine"
          command:
            - rm
            - "-rf"
            - "/nextcloud-aio-nextcloud/lost+found"
          volumeMountsInitRmLostFound:
        - name: init-volumes
          image: "{{ .Values.IMAGE_MIRROR_PREFIX }}{{ .Values.ALPINE_IMAGE_ORG }}alpine"
          command:
            - chmod
            - "777"
          volumeMountsInitContainer:
EOL
# shellcheck disable=SC1083
DEPLOYMENTS="$(find ./ -name '*deployment.yaml')"
mapfile -t DEPLOYMENTS <<< "$DEPLOYMENTS"
for variable in "${DEPLOYMENTS[@]}"; do
    if grep -q volumeMounts "$variable"; then
        if echo "$variable" | grep -q database; then
            sed -i "/^    spec:/r /tmp/initcontainers.database" "$variable"
        elif echo "$variable" | grep -q clamav; then
            sed -i "/^    spec:/r /tmp/initcontainers.clamav" "$variable"
        elif echo "$variable" | grep -q "nextcloud-deployment.yaml"; then
            sed -i "/^    spec:/r /tmp/initcontainers.nextcloud" "$variable"
        else
            sed -i "/^    spec:/r /tmp/initcontainers" "$variable"
        fi
        volumeNames="$(grep -A1 mountPath "$variable" | grep -v mountPath | sed 's|.*name: ||' | sed '/^--$/d')"
        mapfile -t volumeNames <<< "$volumeNames"
        for volumeName in "${volumeNames[@]}"; do
            # The Nextcloud container runs as root user and sets the correct permissions automatically for the data-dir if the www-data user cannot write to it
            if [ "$volumeName" != "nextcloud-aio-nextcloud-data" ]; then
                sed -i "/^.*volumeMountsInitContainer:/i\ \ \ \ \ \ \ \ \ \ \ \ - /$volumeName" "$variable"
                sed -i "/volumeMountsInitContainer:/a\ \ \ \ \ \ \ \ \ \ \ \ - name: $volumeName\n\ \ \ \ \ \ \ \ \ \ \ \ \ \ mountPath: /$volumeName" "$variable"
                sed -i "/volumeMountsInitRmLostFound:/a\ \ \ \ \ \ \ \ \ \ \ \ - name: $volumeName\n\ \ \ \ \ \ \ \ \ \ \ \ \ \ mountPath: /$volumeName" "$variable"
                # Workaround for the database volume
                if [ "$volumeName" = nextcloud-aio-database ]; then
                    sed -i "/mountPath: \/var\/lib\/postgresql\/data/a\ \ \ \ \ \ \ \ \ \ \ \ \ \ subPath: data" "$variable"
                elif [ "$volumeName" = nextcloud-aio-clamav ]; then
                    sed -i "/mountPath: \/var\/lib\/clamav/a\ \ \ \ \ \ \ \ \ \ \ \ \ \ subPath: data" "$variable"
                fi
                
            fi
        done
        sed -i "s|volumeMountsInitContainer:|volumeMounts:|" "$variable"
        sed -i "s|volumeMountsInitRmLostFound:|volumeMounts:|" "$variable"
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
find ./ -name '*.yaml' -exec sed -i 's|nextcloud-aio-namespace|"\{\{ .Values.NAMESPACE \}\}"|' \{} \; 
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
find ./ -name 'nextcloud-aio-nextcloud-persistentvolumeclaim.yaml' -exec sed -i "s|ReadWriteOnce|ReadWriteMany|"  \{} \;
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
find ./ -name '*service.yaml' -exec sed -i "/type: LoadBalancer/a\ \ externalTrafficPolicy: Local" \{} \;
# shellcheck disable=SC1083
find ./ -name '*service.yaml' -exec sed -i "/^spec:/a\ \ ipFamilyPolicy: PreferDualStack" \{} \;
# shellcheck disable=SC1083
find ./ -name '*.yaml' -exec sed -i "s|'{{|\"{{|g;s|}}'|}}\"|g" \{} \; 
# shellcheck disable=SC1083
find ./ \( -not -name '*service.yaml' -name '*.yaml' \) -exec sed -i "/^status:/d" \{} \; 
# shellcheck disable=SC1083
find ./ \( -not -name '*persistentvolumeclaim.yaml' -name '*.yaml' \) -exec sed -i "/resources:/d" \{} \; 
# shellcheck disable=SC1083
find ./ -name "*namespace.yaml" -exec sed -i "1i\\{{- if and \(ne .Values.NAMESPACE \"default\"\) \(ne .Values.NAMESPACE_DISABLED \"yes\"\) }}" \{} \; 
# shellcheck disable=SC1083
find ./ -name "*namespace.yaml" -exec sed -i "$ a {{- end }}" \{} \; 
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

# Additional config
cat << EOL > /tmp/additional.config
            - name: SMTP_HOST
              value: "{{ .Values.SMTP_HOST }}"
            - name: SMTP_SECURE
              value: "{{ .Values.SMTP_SECURE }}"
            - name: SMTP_PORT
              value: "{{ .Values.SMTP_PORT }}"
            - name: SMTP_AUTHTYPE
              value: "{{ .Values.SMTP_AUTHTYPE }}"
            - name: SMTP_NAME
              value: "{{ .Values.SMTP_NAME }}"
            - name: SMTP_PASSWORD
              value: "{{ .Values.SMTP_PASSWORD }}"
            - name: MAIL_FROM_ADDRESS
              value: "{{ .Values.MAIL_FROM_ADDRESS }}"
            - name: MAIL_DOMAIN
              value: "{{ .Values.MAIL_DOMAIN }}"
            - name: SUBSCRIPTION_KEY
              value: "{{ .Values.SUBSCRIPTION_KEY }}"
            - name: APPS_ALLOWLIST
              value: "{{ .Values.APPS_ALLOWLIST }}"
            - name: ADDITIONAL_TRUSTED_PROXY
              value: "{{ .Values.ADDITIONAL_TRUSTED_PROXY }}"
            - name: ADDITIONAL_TRUSTED_DOMAIN
              value: "{{ .Values.ADDITIONAL_TRUSTED_DOMAIN }}"
            - name: SERVERINFO_TOKEN
              value: "{{ .Values.SERVERINFO_TOKEN }}"
            - name: NEXTCLOUD_DEFAULT_QUOTA
              value: "{{ .Values.NEXTCLOUD_DEFAULT_QUOTA }}"
            - name: NEXTCLOUD_MAINTENANCE_WINDOW
              value: "{{ .Values.NEXTCLOUD_MAINTENANCE_WINDOW }}"
EOL
# shellcheck disable=SC1083
find ./ -name '*nextcloud-deployment.yaml' -exec sed -i "/^.*\- env:/r /tmp/additional.config"  \{} \;

# Additional config
cat << EOL > /tmp/additional-apache.config
            - name: ADDITIONAL_TRUSTED_DOMAIN
              value: "{{ .Values.ADDITIONAL_TRUSTED_DOMAIN }}"
EOL
# shellcheck disable=SC1083
find ./ -name '*apache-deployment.yaml' -exec sed -i "/^.*\- env:/r /tmp/additional-apache.config"  \{} \;

# Additional config
cat << EOL > /tmp/additional-talk.config
            - name: TALK_MAX_STREAM_BITRATE
              value: "{{ .Values.TALK_MAX_STREAM_BITRATE }}"
            - name: TALK_MAX_SCREEN_BITRATE
              value: "{{ .Values.TALK_MAX_SCREEN_BITRATE }}"
EOL
# shellcheck disable=SC1083
find ./ -name '*talk-deployment.yaml' -exec sed -i "/^.*\- env:/r /tmp/additional-talk.config"  \{} \;

# shellcheck disable=SC1083
find ./ -name '*deployment.yaml' -exec sed -i '/image: nextcloud/s/$/"/;s|image: nextcloud/|image: "{{ .Values.IMAGE_MIRROR_PREFIX }}{{ .Values.NEXTCLOUD_IMAGE_ORG }}/|;' \{} \;

cat << EOL > templates/nextcloud-aio-networkpolicy.yaml
{{- if eq .Values.NETWORK_POLICY_ENABLED "yes" }}
# https://github.com/ahmetb/kubernetes-network-policy-recipes/blob/master/04-deny-traffic-from-other-namespaces.md
kind: NetworkPolicy
apiVersion: networking.k8s.io/v1
metadata:
  namespace: "{{ .Values.NAMESPACE }}"
  name: nextcloud-aio-deny-from-other-namespaces
spec:
  podSelector:
    matchLabels:
  policyTypes:
    - Ingress
    - Egress
  ingress:
  - from:
    - podSelector: {}
  egress:
  - to:
    - podSelector: {}
{{- end }}
EOL

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
sed -i '/_ENABLED.*/s/ yes / "yes" /' /tmp/sample.conf
sed -i '/_ENABLED.*/s/ no / "no" /' /tmp/sample.conf
sed -i 's|^NEXTCLOUD_TRUSTED_CACERTS_DIR: .*|NEXTCLOUD_TRUSTED_CACERTS_DIR:        # Setting this to any value allows to automatically import root certificates into the Nextcloud container|' /tmp/sample.conf
sed -i 's|10737418240|"10737418240"|' /tmp/sample.conf
# shellcheck disable=SC2129
echo "" >> /tmp/sample.conf
# shellcheck disable=SC2129
echo 'STORAGE_CLASS:        # By setting this, you can adjust the storage class for your volumes' >> /tmp/sample.conf
for variable in "${VOLUME_VARIABLE[@]}"; do
    echo "$variable: 1Gi       # You can change the size of the $(echo "$variable" | sed 's|_STORAGE_SIZE||;s|_|-|g' | tr '[:upper:]' '[:lower:]') volume that default to 1Gi with this value" >> /tmp/sample.conf
done
sed -i "s|NEXTCLOUD_STORAGE_SIZE: 1Gi|NEXTCLOUD_STORAGE_SIZE: 5Gi|" /tmp/sample.conf
sed -i "s|NEXTCLOUD_DATA_STORAGE_SIZE: 1Gi|NEXTCLOUD_DATA_STORAGE_SIZE: 5Gi|" /tmp/sample.conf

# Additional config
cat << ADDITIONAL_CONFIG >> /tmp/sample.conf

NAMESPACE: default        # By changing this, you can adjust the namespace of the installation which allows to install multiple instances on one kubernetes cluster
NAMESPACE_DISABLED: "no"        # By setting this to "yes", you can disabled the creation of the namespace so that you can use a pre-created one
NETWORK_POLICY_ENABLED: "no"        # By setting this to "yes", you can enable a network policy that limits network access to the same namespace. ⚠️ Attention: this breaks if you use an ingress!!! So it should be disabled if you do so!
SUBSCRIPTION_KEY:        # This allows to set the Nextcloud Enterprise key via ENV
SERVERINFO_TOKEN:        # This allows to set the serverinfo app token for monitoring your Nextcloud via the serverinfo app
APPS_ALLOWLIST:        # This allows to configure allowed apps that will be shown in Nextcloud's Appstore. You need to enter the app-IDs of the apps here and separate them with spaces. E.g. 'files richdocuments'
ADDITIONAL_TRUSTED_PROXY:        # Allows to add one additional ip-address to Nextcloud's trusted proxies and to the Office WOPI-allowlist automatically. Set it e.g. like this: 'your.public.ip-address'. You can also use an ip-range here.
ADDITIONAL_TRUSTED_DOMAIN:        # Allows to add one domain to Nextcloud's trusted domains and also generates a certificate automatically for it
NEXTCLOUD_DEFAULT_QUOTA: "10 GB"       # Allows to adjust the default quota that will be taken into account in Nextcloud for new users. Setting it to "unlimited" will set it to unlimited
NEXTCLOUD_MAINTENANCE_WINDOW:        # Allows to define the maintenance window for Nextcloud. See https://docs.nextcloud.com/server/stable/admin_manual/configuration_server/background_jobs_configuration.html#parameters for possible values
SMTP_HOST:        # (empty by default): The hostname of the SMTP server.
SMTP_SECURE:         # (empty by default): Set to 'ssl' to use SSL, or 'tls' to use STARTTLS.
SMTP_PORT:         # (default: '465' for SSL and '25' for non-secure connections): Optional port for the SMTP connection. Use '587' for an alternative port for STARTTLS.
SMTP_AUTHTYPE:         # (default: 'LOGIN'): The method used for authentication. Use 'PLAIN' if no authentication or STARTLS is required.
SMTP_NAME:         # (empty by default): The username for the authentication.
SMTP_PASSWORD:         # (empty by default): The password for the authentication.
MAIL_FROM_ADDRESS:         # (not set by default): Set the local-part for the 'from' field in the emails sent by Nextcloud.
MAIL_DOMAIN:         # (not set by default): Set a different domain for the emails than the domain where Nextcloud is installed.
TALK_MAX_STREAM_BITRATE: "1048576"         # This allows to adjust the max stream bitrate of the talk hpb
TALK_MAX_SCREEN_BITRATE: "2097152"         # This allows to adjust the max stream bitrate of the talk hpb

IMAGE_MIRROR_PREFIX:          # Setting this allows you to pull Nextcloud images through a mirror registry. It needs a trailing slash!
NEXTCLOUD_IMAGE_ORG: nextcloud          # Setting this allows you to change the image's org name in case a different image needs to be used e.g. for compliance reasons.
ALPINE_IMAGE_ORG:          # Setting this allows you to change the image's org name in case a different image needs to be used e.g. for compliance reasons. It needs a trailing slash!
ADDITIONAL_CONFIG

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
    # shellcheck disable=SC1083
    find ./ -name "*nextcloud-aio-$name-persistentvolumeclaim.yaml" -exec sed -i "1i\\{{- if eq .Values.$variable \"yes\" }}" \{} \; 
    # shellcheck disable=SC1083
    find ./ -name "*nextcloud-aio-$name-persistentvolumeclaim.yaml" -exec sed -i "$ a {{- end }}" \{} \; 
done

# Additional case for FTS volume
# shellcheck disable=SC1083
find ./ -name "*nextcloud-aio-elasticsearch-persistentvolumeclaim.yaml" -exec sed -i "1i\\{{- if eq .Values.FULLTEXTSEARCH_ENABLED \"yes\" }}" \{} \; 
# shellcheck disable=SC1083
find ./ -name "*nextcloud-aio-elasticsearch-persistentvolumeclaim.yaml" -exec sed -i "$ a {{- end }}" \{} \; 

chmod 777 -R ./

# Seems like the dir needs to match the name of the chart
cd ../
rm -rf ./nextcloud-aio-helm-chart
mv ./helm-chart ./nextcloud-aio-helm-chart

set +ex
