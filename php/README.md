# PHP Docker Controller

This is the code for the PHP Docker controller.

## How to run

Running this locally requires :

### 1. Install the development environment

This project uses Composer as dependency management software. It is very similar to NPM.
The command to install all dependencies is:

```bash
composer install
```

### 2. Access to docker socket

The `root` user has all privileges including access to the Docker socket. 
But **it is not recommended to launch the local instance with full privileges**, consider the docker group for docker access without being `root`.
See https://docs.docker.com/engine/install/linux-postinstall/#manage-docker-as-a-non-root-user

### 3. Run a `nextcloud-aio-mastercontainer` container

This application manages containers, including its own container.
So you need to run a `nextcloud-aio-mastercontainer` container for the application to work properly.

Here is a command to quickly launch a container :

```bash
docker run \
--rm \
--name nextcloud-aio-mastercontainer \
--volume nextcloud_aio_mastercontainer:/mnt/docker-aio-config \
--volume /var/run/docker.sock:/var/run/docker.sock \
nextcloud/all-in-one:latest
```

### 4. Start your server

With this command you will launch the server:

```bash
# Make sure to launch this command with a user having access to the docker socket.
SKIP_DOMAIN_VALIDATION=true composer run dev
```

You can then access the web interface at http://localhost:8080.

Note: You can restart the server by preceding the command with other environment variables.

## Composer routine

| Command                                 | Description                            |
|-----------------------------------------|----------------------------------------|
| `composer run dev`                      | Starts the development server          |
| `composer run psalm`                    | Run Psalm static analysis              |
| `composer run psalm:strict`             | Run Psalm static analysis strict       |
| `composer run psalm:update-baseline`    | Run Psalm with `--update-baseline` arg |
| `composer run lint`                     | Run PHP Syntax check                   |
| `composer run lint:twig`                | Run Twig Syntax check                  |
| `composer run php-deprecation-detector` | Run PHP Deprecation Detector           |


