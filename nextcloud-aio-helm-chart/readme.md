# Nextcloud AIO Helm-chart

> [!NOTE]
> For an enterprise-ready and scalable deployment method based on Helm Charts (also available for Podman), please [contact Nextcloud GmbH](https://nextcloud.com/enterprise/).

> [!IMPORTANT]
> This Helm-Chart is not intended to be used with Ingress as it handles TLS itself via the built-in apache container and exposes a Loadbalancer port itself on the Cluster. See the [apache service](https://github.com/nextcloud/all-in-one/blob/main/nextcloud-aio-helm-chart/templates/nextcloud-aio-apache-service.yaml). However if the Cluster is used behind NAT, you can adjust `APACHE_PORT` to a different one than 443 and do the TLS offloading on an external Reverse Proxy that forwards the traffic to the configured port via http. If you really need the Ingress feature, please [contact Nextcloud GmbH](https://nextcloud.com/enterprise/) as we offer an enterprise-ready and scalable deployment method based on Helm Charts that also allows Ingress to be used.

You can run the containers that are build for AIO with Kubernetes using this Helm chart. This comes with a few downsides, that are discussed below.

### Advantages
- You can run it without a container having access to the docker socket
- You can run the containers with Kubernetes

### Disadvantages
- You lose the AIO interface
- You lose update notifications and automatic updates
- You lose all AIO backup and restore features
- You lose all community containers: https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers
- **You need to know what you are doing**
- For updating, you need to strictly follow the at the bottom described update routine
- You need to monitor yourself if the volumes have enough free space and increase them if they don't by adjusting their size in values.yaml
- Probably more

## How to use this?

First download this file: https://raw.githubusercontent.com/nextcloud/all-in-one/main/nextcloud-aio-helm-chart/values.yaml and adjust at least all values marked with `# TODO!`<br>
⚠️ **Warning**: Do not use the symbols `@` and `:` in your passwords. These symbols are used to build database connection strings. You will experience issues when using these symbols!

Then run:

```
helm repo add nextcloud-aio https://nextcloud.github.io/all-in-one/
helm install nextcloud-aio nextcloud-aio/nextcloud-aio-helm-chart -f values.yaml
```

And after a while, everything should be set up.

## How to update?
Since the values of this helm chart may change in the future, it is highly recommended to strictly follow the following procedure whenever you want to upgrade it.
1. Stop all running pods
1. Back up all volumes that got created by the Helm chart and the values.yaml file
1. Run `helm repo update nextcloud-aio` in order to get the updated yaml files from the repository
1. Now download the updated values.yaml file from https://raw.githubusercontent.com/nextcloud/all-in-one/main/nextcloud-aio-helm-chart/values.yaml and compare that with the one that you currently have locally. Look for variables that changed or got added. You can use the diff command to compare them.
1. After the file update was successful, simply run `helm install my-release nextcloud-aio/nextcloud-aio-helm-chart -f values.yaml` to update to the new version.
