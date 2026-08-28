# Nextcloud AIO Helm-chart

> [!NOTE]
> For an enterprise-ready and scalable deployment method based on Helm Charts (also available for Podman and OpenShift), please [contact Nextcloud GmbH](https://nextcloud.com/enterprise/).

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

## HaRP / AppAPI (ExApps) configuration

When `HARP_ENABLED` is set to `"yes"`, the chart deploys the [HaRP](https://github.com/nextcloud/HaRP) container that AppAPI uses to run external apps (ExApps). Unlike the docker-based AIO installation, HaRP cannot use the docker backend inside Kubernetes, so the chart automatically enables HaRP's Kubernetes backend (`HP_K8S_ENABLED=true`) and lets HaRP create the ExApp deployments via the Kubernetes API.

> [!NOTE]
> HaRP's Kubernetes backend is only available with Nextcloud 34 and later. On earlier Nextcloud versions you cannot use ExApps with this Helm chart.

HaRP deploys the ExApps into the same namespace in which it runs itself, i.e. the namespace that is configured via `NAMESPACE`.

> [!IMPORTANT]
> HaRP needs permission to manage resources (deployments, services, persistent volume claims, …) in the namespace that is configured via `NAMESPACE`. The chart does **not** create this RBAC for you. You need to:
> 1. Create a `ServiceAccount` **in the same namespace in which the HaRP container runs** (the namespace configured via `NAMESPACE`) and set its name in `HARP_SERVICE_ACCOUNT_NAME` so that it is mounted into the HaRP pod. A service account from a different namespace cannot be used.
> 2. Grant that service account permission to manage resources in the namespace via a `Role`/`RoleBinding`.
>
> See the [HaRP repository](https://github.com/nextcloud/HaRP) for the required RBAC setup. If `HARP_SERVICE_ACCOUNT_NAME` is left empty, the namespace's `default` service account is used, which usually does not have the required permissions.

The following values in `values.yaml` allow you to adjust the Kubernetes backend of HaRP:

| Value | Default | Description |
| --- | --- | --- |
| `HARP_K8S_STORAGE_CLASS` | _(empty)_ | The storage class used for ExApp persistent volume claims. Leave empty to use the cluster's default storage class. |
| `HARP_K8S_DEFAULT_STORAGE_SIZE` | `10Gi` | The default size of the persistent volume claims that HaRP creates for ExApps. |
| `HARP_K8S_HOST_ALIASES` | _(empty)_ | Optional host aliases that HaRP sets on the ExApp pods so that they can resolve the configured hostnames, e.g. when your Nextcloud domain is not resolvable by the cluster's DNS. Use a comma-separated list of `hostname:ip` pairs, e.g. `nextcloud.example.com:10.0.0.5,collabora.example.com:10.0.0.6`. Leave empty to not set any host aliases. |
| `HARP_SERVICE_ACCOUNT_NAME` | _(empty)_ | The service account that is mounted into the HaRP pod and used to authenticate against the Kubernetes API. It must exist in the same namespace in which HaRP runs (see `NAMESPACE`). You must create it yourself and grant it the RBAC permissions described above. Leave empty to use the namespace's `default` service account. |

### Registering the HaRP daemon in Nextcloud

The chart does **not** register the HaRP daemon in Nextcloud for you, so you need to do that yourself once all pods are running. `nextcloud-aio-harp:8780` is the HaRP service that this chart creates. Adjust `https://your-nc-domain.com` to your Nextcloud domain (`NC_DOMAIN`) and `<HP_SHARED_KEY>` to the value that you configured in `HP_SHARED_KEY` in your `values.yaml`:

```
kubectl exec deployment/nextcloud-aio-nextcloud -- php occ app_api:daemon:register k8s_harp "Kubernetes HaRP" kubernetes-install http nextcloud-aio-harp:8780 https://your-nc-domain.com --harp --harp_shared_key "<HP_SHARED_KEY>" --k8s --set-default
```

## How to update?
Since the values of this helm chart may change in the future, it is highly recommended to strictly follow the following procedure whenever you want to upgrade it.
1. Stop all running pods
1. Back up all volumes that got created by the Helm chart and the values.yaml file
1. Run `helm repo update nextcloud-aio` in order to get the updated yaml files from the repository
1. Now download the updated values.yaml file from https://raw.githubusercontent.com/nextcloud/all-in-one/main/nextcloud-aio-helm-chart/values.yaml and compare that with the one that you currently have locally. Look for variables that changed or got added. You can use the diff command to compare them.
1. After the file update was successful, simply run `helm install my-release nextcloud-aio/nextcloud-aio-helm-chart -f values.yaml` to update to the new version.
