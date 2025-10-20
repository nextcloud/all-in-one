# Nextcloud All-in-One `mastercontainer`

This folder contains the OCI/Docker container definition, along with associated resources and
configuration files, for building the `mastercontainer` as part of the Nextcloud All-in-One
project. This container hosts [the Nextcloud AIO interface](
https://github.com/nextcloud/all-in-one/tree/main/php)[^app], and a dedicated PHP environment
for it (which is completely independent of the Nextcloud Server).

## Overview

The mastercontainer acts as the central orchestration service for the deployment and management
of all other containers in the Nextcloud All-in-One stack. It hosts:

- A dedicated PHP SAPI/backend (php-fpm) for AIO itself (not Nextcloud Server)
- An Apache service for accessing the AIO interface via a self-signed HTTPS VirtualHost on 8080/tcp
- A Caddy reverse proxy service enabling HTTPS access to the AIO frontend on port 8443/tcp.
  - Caddy will automatically issue a Let's Encrypt issued certificate if port 80 and 8443
    is open/forwarded and a domain pointer is in place; then, simply open the Nextcloud AIO interface using the
    domain (`https://your-domain-that-points-to-this-server.tld:8443`). The Let's Encrypt certificate request will
    use an [ACME HTTP-01](https://letsencrypt.org/docs/challenge-types/#http-01-challenge) challenge.
- Miscellaneous support services specific to AIO (backup management, health checks, etc.)

## Key Responsibilities

- Orchestrates the deployment and lifecycle of all Nextcloud service containers
- Handles initial setup and container configuration
- Coordinates image updates
- Monitors general system health

It triggers the initial installation and ensures the smooth operation of the Nextcloud
All-in-One stack.

## Contents

- **Dockerfile**: Instructions for building the mastercontainer image.
- **Entrypoint script**: The `start.sh` script is used for container initialization and runtime
  configuration before starting supervisord.
- [**Nextcloud All-in-One Controller App**](https://github.com/nextcloud/all-in-one/tree/main/php): The
  core AIO orchestrator that handles configuration and settings for the containers.
- **Supervisor**: The `supervisord.conf` file defines the long-running services hosted within
  the container (php-fpm, cron, etc.)

## Usage

This container should be used as the trigger image when deploying the Nextcloud All-in-One
stack in a Docker or other OCI-compliant container environment. For detailed deployment
instructions, refer to the [project documentation](
https://github.com/nextcloud/all-in-one).

## Related Resources

- [Main Repository](https://github.com/nextcloud/all-in-one)
- [Documentation](https://github.com/nextcloud/all-in-one#readme)

## Contributing

Contributions are welcome! Please follow the Nextcloud project's guidelines and submit pull
requests or issues via the main repository.

## License

This folder and its contents are licensed under the
[GNU AGPLv3](https://www.gnu.org/licenses/agpl-3.0.html), in line with the rest of Nextcloud
All-in-One.

[^app]: The Nextcloud All-in-One interface allows users to install, configure, and
manage their Nextcloud instance and related containers via a secure web interface and API.
It automates and simplifies complex tasks such as container orchestration, backups, updates,
and service management for users deploying Nextcloud in Docker environments.
