# Initial setup

- [ ] Verify that after starting the test container, you can access the AIO interface using https://internal.ip.address:8080
- [ ] After clicking the self-signed-certificate warning away, it should show the setup page with an explanation what AIO is and the initial password and a button that contains a link to the AIO login page
- [ ] After copying the password and clicking on this button, it should open a new tab with the login page
- [ ] The login page should show an input field that allows to enter the AIO password and a `Log in` button
- [ ] After pasting the new password into the input field and clicking on this button button, you should be logged in
- [ ] You should now see the containers page and you should see three sections: one general section which explains what AIO is, one `New AIO instance` section and one section that allows to restore the whole AIO instance from backup.

You can now continue with [002-new-instance.md](./002-new-instance.md) or [010-restore-instance.md](./010-restore-instance.md).
