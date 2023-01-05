#!/bin/bash

# Clean
rm -f ./helm-chart/Chart.yaml
rm -f ./helm-chart/values.yaml
rm -rf ./helm-chart/templates

# Install kompose
LATEST_KOMPOSE="$(git ls-remote --tags https://github.com/kubernetes/kompose.git | cut -d/ -f3 | grep -viE -- 'rc|b' | sort -V | tail -1)"
curl -L https://github.com/kubernetes/kompose/releases/download/"$LATEST_KOMPOSE"/kompose-linux-amd64 -o kompose
chmod +x kompose
sudo mv ./kompose /usr/local/bin/kompose

set -ex

# Conversion of docker-compose
cd manual-install
cp latest.yml latest.yml.backup
cp sample.conf /tmp/
sed -i 's|^|export |' /tmp/sample.conf
source /tmp/sample.conf
rm /tmp/sample.conf
sed -i "s|\${APACHE_IP_BINDING}|$APACHE_IP_BINDING|" latest.yml
sed -i "s|\${APACHE_PORT}:\${APACHE_PORT}/|$APACHE_PORT:$APACHE_PORT/|" latest.yml
sed -i "s|\${TALK_PORT}:\${TALK_PORT}/|$TALK_PORT:$TALK_PORT/|g" latest.yml
sed -i "s|\${NEXTCLOUD_DATADIR}|$NEXTCLOUD_DATADIR|" latest.yml
sed -i "s|\${NEXTCLOUD_MOUNT}:\${NEXTCLOUD_MOUNT}:|nextcloud_aio_nextcloud_mount:$NEXTCLOUD_MOUNT:|" latest.yml
sed -i "s|\${NEXTCLOUD_TRUSTED_CACERTS_DIR}|nextcloud_aio_nextcloud_trusted_cacerts|g#" latest.yml
sed -i 's|\${|{{ .Values.|g' latest.yml
sed -i 's|}| }}|g' latest.yml
sed -i '/profiles: /d' latest.yml
cat latest.yml
kompose convert -c -f latest.yml
cd latest
find ./ -name '*apache*' -exec sed -i "s|$APACHE_PORT|{{ .Values.APACHE_PORT }}|" \{} \;  
find ./ -name '*talk*' -exec sed -i "s|$TALK_PORT|{{ .Values.TALK_PORT }}|" \{} \; 
find ./ -name '*.yaml' -exec sed -i "s|'{{|\"{{|g;s|}}'|}}\"|g" \{} \; 
cd ../
mkdir -p ../helm-chart/
rm latest/README.md
mv latest/* ../helm-chart/
rm -r latest
rm latest.yml
mv latest.yml.backup latest.yml

# Conversion of sample.conf
cp sample.conf /tmp/
sed -i 's|"||g' /tmp/sample.conf
sed -i 's|= |: "" |' /tmp/sample.conf
sed -i 's|=|: |' /tmp/sample.conf
sed -i '/^\$NEXTCLOUD_DATADIR/d' /tmp/sample.conf
sed -i '/^\$NEXTCLOUD_MOUNT/d' /tmp/sample.conf
sed -i '/^\$NEXTCLOUD_TRUSTED_CACERTS_DIR/d' /tmp/sample.conf
mv /tmp/sample.conf ../helm-chart/values.yaml

chmod 777 -R ../helm-chart/

set +ex
