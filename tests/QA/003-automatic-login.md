# Indirect login

- [ ] After you log in to Nextcloud using the provided initial credentials, open https://yourdomain.com/settings/admin/overview
- [ ] There you should see a Nextcloud AIO section and a button that allows to log into the AIO interface.
- [ ] Clicking on this button should open the AIO login page in a new tab with the direct login unblocked (the passphrase input field is shown) and a notice that it's unblocked for 5 minutes
- [ ] Reloading the login page should still show the passphrase input field and the notice
- [ ] Entering the AIO passphrase should log you in
- [ ] After logging out, the direct login should be blocked again
- [ ] After using the indirect login and waiting 5 minutes, submitting the passphrase should reload the page, which then reports that the direct login is blocked since Nextcloud is running
- [ ] After using the indirect login and entering a wrong passphrase 5 times, it should report that the direct login is blocked since Nextcloud is running
- [ ] After logging in, all sessions in other tabs that are currently open should be closed (you can verify by reloading all other AIO tabs)

You can now continue with [004-initial-backup.md](./004-initial-backup.md).