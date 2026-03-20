# Community Containers

- [ ] Below the Optional Addons section there should be a Community Containers section
- [ ] The section should show a details element that allows to reveal the list of available community containers
- [ ] When containers are running, the checkboxes should be disabled and a notice should inform the user that changes can only be made when containers are stopped
- [ ] When containers are stopped, checkboxes should be enabled
    - [ ] Enabling a community container and clicking `Save changes` should show a confirmation dialog
    - [ ] Canceling the confirmation dialog should not save the changes
    - [ ] Confirming should save the changes and reload the page
    - [ ] After saving, the enabled community container should appear in the containers section and start along with the other containers when `Start containers` is clicked
    - [ ] Disabling a previously enabled community container and saving should remove it from the containers section after stopping and starting containers

You can now continue with [060-environmental-variables.md](./060-environmental-variables.md)