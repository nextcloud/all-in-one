# Optional addons

- [ ] Close to the bottom of the page in the AIO interface, you should see the optional addons section
- [ ] You should be able to change optional addons when containers are stopped and not change them when containers are running
- [ ] Enabling either of the options should start a new container with the same or comparable name and should also list them in the containers section
- [ ] After all containers are started with the new config active, you should verify that the options were automatically activated/deactivated.
    - [ ] ClamAV by trying to upload a testvirus to Nextcloud https://www.eicar.org/?page_id=3950
    - [ ] Collabora by trying to open a .docx or .odt file in Nextcloud
    - [ ] Nextcloud Talk by opening the Talk app in Nextcloud, creating a new chat and trying to join a call in this chat. Also verifying in the settings that the HPB and turn server work.
    - [ ] Imaginary by having a look if when uploading a new picture in Nextcloud, it adds some log entries to the container
    - [ ] Fulltextsearch by trying to search for a heading inside a file in Nextcloud
- [ ] When Collabora is enabled, it should show below the Optional Addons section a section where you can change the dictionaries for collabora. `de_DE en_GB en_US es_ES fr_FR it nl pt_BR pt_PT ru` should be a valid setting. E.g. `de.De` not. If already set, it should show a button that allows to remove the setting again.

You can now continue with [060-environmental-variables.md](./060-environmental-variables.md)