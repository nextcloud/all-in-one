# You can also install the AIO containers on Kubernetes using this Helm Chart

This is currently beta and not ready yet.

## How to use this?

First download this file: https://raw.githubusercontent.com/nextcloud/all-in-one/main/nextcloud-aio-helm-chart/values.yaml and adjust at least all values marked with `# TODO!`

Then run:

```
helm repo add nextcloud-aio https://nextcloud.github.io/all-in-one/
helm install my-release nextcloud-aio/nextcloud-aio-helm-chart -f values.yaml
```
