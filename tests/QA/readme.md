# QA test plans

In this folder are manual test plans for QA located that allow to manually step through certain features and make sure that everything works as expected.

For a test instance, you should make sure that all potentially breaking changes are merged, build new containers by following https://github.com/nextcloud/all-in-one/blob/main/develop.md#how-to-build-new-containers, stop a potential old instance, remove it and delete all volumes. Afterwards start a new clean test instance by following https://github.com/nextcloud/all-in-one/blob/main/develop.md#developer-channel.

Best is to start testing with [001-initial-setup.md](./001-initial-setup.md).
