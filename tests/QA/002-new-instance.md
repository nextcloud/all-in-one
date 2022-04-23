# New instance

For the below to work, it is important that you have a domain that you point onto your testserver and open port 443 in your router/firewall.

- [ ] The `New AIO instance` section should show an input field that allows to enter a domain that will be used for Nextcloud later on as well as a short explanation regarding dynamic DNS
- [ ] Now test a few examples in the input box:
    - [ ] Entering `djfslkklk` should report that DNS config is not set or the domain is not in a valid format
    - [ ] Entering `https://sdjflkjk.cpm` should report that this is not a valid domain
    - [ ] Entering `10.0.0.1` should report that ip-addresses are not supported
    - [ ] Entering `nextcloud.com` should report that the domain does not point to this server
    - [ ] Entering the domain that does point to your server e.g. `yourdomain.com` should finally redirect you to the next screen (if you did not configure your domain yet or did not open port 443, it should report that to you)
- [ ] Now you should see a button `Start containers` and an explanation which points out that clicking on the button will start the containers and that this can take a long time.
- [ ] Below that you should see a section `Optional addons` which shows a checkbox list with addons that can be enabled or disabled.
    - [ ] Collabora and Nextcloud Talk should be enabled, the rest disabled
    - [ ] Unchecking/Checking any of these should insert a button that allows to save the set config
    - [ ] Checking OnlyOffice and Collabora at the same time should show a warning that this is not supported and should  not saving the new config
    - [ ] Recommended is to uncheck all options now
    - [ ] Clicking on the save button should reload the page and activate the new config
- [ ] Clickig on the `Start containers` button should finally reveal a big spinning wheel that should block all elements on the side of being clicked.
- [ ] After waiting a few minutes, it should reload and show a new page
    - [ ] On top of the page should be shown which channel you are running
    - [ ] Below that, it should show that containers are currently starting
    - [ ] Below that it should show a section with Containers: Apache, Database, Nextcloud and Redis and that your containers are up-to-date
    - [ ] On the bottom should be the Optional addons section shown but with disabled checkboxes (not clickable)
    - [ ] A automatic reload every 5s should happen until all Containers are started (as long as this window is focused)
- [ ] After waiting a bit longer it should instead of the advice that your containers are currently running show the initial Nextcloud credentials (username, password) and below that a button that allows to open the Nextcloud interface in a new tab
- [ ] Clicking on that button should open the Nextcloud interface in a new tab and you should be able to log in using the provided credentials
- [ ] Below the Containers section it should show a `Stop containers` button
- [ ] Below the Containers section and above the Optional Addons section, you should see a Backup and restore section and an AIO password change section

You can now continue with [003-automatic-login.md](./003-automatic-login.md).