# Nextcloud All-in-One Mastercontainer

This folder contains the OCI/Docker container definition of the mastercontainer, which acts as
the central orchestration service for the deployment and management of all other containers in
the Nextcloud All-in-One stack. The mastercontainer is responsible for initial setup, basic
health checks, coordination of image updates, and hosting
[the Nextcloud All-in-One management portal and API](https://github.com/nextcloud/all-in-one/tree/main/php)[^app].

It triggers the initial installation and ensures the smooth operation of the Nextcloud
All-in-One stack.

## Key Responsibilities

- Orchestrates the deployment and lifecycle of all Nextcloud service containers
- Handles initial setup and container configuration
- Manages updates and monitors system health

## Usage

This container should be used as the trigger image when deploying the Nextcloud All-in-One stack
in a Docker or other OCI-compliant container environment. For detailed deployment instructions,
refer to the main [project documentation](https://github.com/nextcloud/all-in-one).

## Related Resources

- [Main Repository](https://github.com/nextcloud/all-in-one)
- [Documentation](https://github.com/nextcloud/all-in-one#readme)

[^app]: The Nextcloud All-in-One management portal allows users to install, configure, and
manage their Nextcloud instance and related containers via a secure web interface and API.
It automates and simplifies the complex tasks of container orchestration, backups, updates,
and service management for users deploying Nextcloud in Docker environments.
