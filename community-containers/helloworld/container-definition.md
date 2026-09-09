# AIO Containers Definition Schema

## Required Properties

| Field            | Description                                                                                                                                               |
|------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------|
| `image`          | This is the image name of the container. You can use 2 repositories: GitHub Container Registry: `ghcr.io/user/repo` (preferred); Docker Hub: `user/repo`; |
| `container_name` | This is the name of the container. It must be unique and follow the pattern `nextcloud-aio-<service_name>`.                                               |
| `image_tag`      | This is the tag of the image. We recommend using the `vX` tag corresponding to major versions of the image.                                               |
| `display_name`   | The name of the container to be displayed in the UI.                                                                                                      |
| `documentation`  | Link to the documentation of the container.                                                                                                               |

## Optional Properties

| Field                     | Description                                                                                                                                                           |
|---------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `expose`                  | TODO                                                                                                                                                                  |
| `cap_add`                 | See [Docker Capabilities](https://docs.docker.com/engine/reference/run/#runtime-privilege-and-linux-capabilities)                                                     |
| `cap_drop`                | See [Docker Capabilities](https://docs.docker.com/engine/reference/run/#runtime-privilege-and-linux-capabilities)                                                     |
| `depends_on`              | List containers should be started before starting this container.                                                                                                     |
| `environment`             | List of environment variables to be set in the container. See [Docker Environment Variables](https://docs.docker.com/engine/reference/run/#env-environment-variables) |
| `internal_port`           | TODO                                                                                                                                                                  |
| `stop_grace_period`       | TODO                                                                                                                                                                  |
| `user`                    | The user to run the container as. See [Docker User](https://docs.docker.com/engine/reference/run/#user)                                                               |
| `ports`                   | A list of ports to expose on the container. See [port section](#ports)                                                                                                |
| `healthcheck`             | The healthcheck configuration for the container. See [healthcheck section](#healthcheck)                                                                              |
| `aio_variables`           | TODO                                                                                                                                                                  |
| `restart`                 | The restart policy for the container. See [Docker Restart Policy](https://docs.docker.com/engine/reference/run/#restart-policies---restart)                           |
| `shm_size`                | TODO                                                                                                                                                                  |
| `secrets`                 | TODO                                                                                                                                                                  |
| `ui_secret`               | TODO                                                                                                                                                                  |
| `devices`                 | TODO                                                                                                                                                                  |
| `enable_nvidia_gpu`       | TODO                                                                                                                                                                  |
| `apparmor_unconfined`     | TODO                                                                                                                                                                  |
| `backup_volumes`          | List of volumes should be included in the AIO backup.                                                                                                                 |
| `nextcloud_exec_commands` | TODO                                                                                                                                                                  |
| `profiles`                | TODO                                                                                                                                                                  |
| `read_only`               | TODO                                                                                                                                                                  |
| `init`                    | TODO                                                                                                                                                                  |
| `tmpfs`                   | TODO                                                                                                                                                                  |
| `volumes`                 | List of volumes to mount in the container. See [volumes section](#volumes)                                                                                            |

### Ports

| Field         | Description                         |
|---------------|-------------------------------------|
| `ip_binding`  | The IP address to bind the port to. |
| `port_number` | The port number to expose.          |
| `protocol`    | The protocol to use.                |

### Healthcheck

| Field            | Description                                                                  |
|------------------|------------------------------------------------------------------------------|
| `interval`       | The time between running the healthcheck.                                    |
| `timeout`        | The time to wait for the healthcheck to complete.                            |
| `retries`        | The number of retries to attempt before considering the container unhealthy. |
| `start_period`   | The time to wait before starting the healthcheck.                            |
| `start_interval` | The time to wait between retries.                                            |
| `test`           | The command to run to check the health of the container.                     |

### Volumes

| Field         | Description                                    |
|---------------|------------------------------------------------|
| `destination` | The path to mount the volume in the container. |
| `source`      | The source of the volume.                      |
| `writeable`   | Whether the volume is writeable.               |
