# ClamAV extended community container

This container is a drop-in replacement for the official AIO ClamAV container with enhanced detection via additional signatures. The [container image](https://github.com/extremeshok/clamav-unofficial-sigs) is built on the official ClamAV image (the published multi-arch image uses clamav/clamav-debian:stable, the only official variant with amd64 and arm64 manifests), so it always carries a current ClamAV for signature integrity testing.
 
## Installation

See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack. Add and start the container in the Nextcloud AIO interface. No configuration needs to be done by you. 

> [!Warning]
> - Only use either the official ClamAV container OR this extended one. Running both at the same time is not possible.
> - Running this container needs about 1GB of additional RAM - like the official ClamAV container as well

After installation, the [files_antivirus app](https://apps.nextcloud.com/apps/files_antivirus) is automatically downloaded and configured to work with this container. This container is not included in AIO's backup solution because there is nothing that needs to be kept anyway. 

## Active Signature Sources (no configuration required)

| Source | Update Interval | Description |
|---|---|---|
| [Sanesecurity](https://sanesecurity.com/usage/signatures/) | Every 2h | Community-driven signatures focusing on spam, phishing, malware and malicious attachments. One of the most comprehensive free ClamAV signature collections. |
| [InterServer](https://sigs.interserver.net) | Every 1h | Signatures provided by the hosting provider InterServer, focusing on known malware, malicious shell scripts and file injections. |
| [Linux Malware Detect](https://www.rfxn.com/projects/linux-malware-detect/) | Every 6h | Specialized in malware targeting Linux servers, particularly web shells, PHP malware and threats commonly found in shared hosting environments. |
| [URLhaus](https://urlhaus.abuse.ch) | Every 1h | Maintained by abuse.ch, focusing exclusively on URLs actively used for malware distribution. Data is crowd-sourced from the security community. |

## Testing your setup

The easiest way to test if this is working at all is to create a text file and paste the [EICAR test string](https://en.wikipedia.org/wiki/EICAR_test_file) into it. The file should not be saved and a nextcloud log entry should be created.

### Repository
https://github.com/extremeshok/clamav-unofficial-sigs

### Maintainer
https://github.com/derStephan
