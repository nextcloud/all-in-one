# Login behavior

- [ ] When opening the AIO interface in a new tab while the apache container is running, it should report on the login page that Nextcloud is running and you should use the automatic login
- [ ] When the apache container is stopped, you should see here an input field that allows you to enter the AIO password which should log you in
- [ ] Starting and stopping the containers multiple times should every time produce a new token that is used in the admin overview in Nextcloud as link in the button to log you into the AIO interface. (see [003-automatic-login.md](./003-automatic-login.md))

You can now continue with [050-optional-addons.md](./050-optional-addons.md)