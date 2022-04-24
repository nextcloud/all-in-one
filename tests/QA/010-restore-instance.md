# Restore instance

For the below to work, you need a backup archive of an AIO instance and the location on the test machine and the password for the backup archive. You can get one here: [backup-archive](./assets/backup-archive/)

- [ ] The section that allows to restore the whole AIO instance from backup should show two input fields: one that allows to enter a location where the backup archive is located and one that allows to enter password of the archive. It should also show a short explanation regarding the path requirements
- [ ] Entering an incorrect path and/or password should let you continue and test your settings in the next step
    - [ ] Clicking on the test button should after a reload bring you back to the initial screen where it should say that the test was unsuccessful. Also you should be able to have a look at the backup container logs for investigation what exactly failed.
    - [ ] You should also now see the input boxes again where you can change the path and password, confirm it and bring you again to the screen where you can test your settings.
- [ ] Entering the correct path to the backup archive and the correct password here should:
    - [ ] Should reload and should hide all options except the option to test the path and password
    - [ ] After the test you should see the options to check the integrity of the backup and a list of backup archives that you can choose from to restore your instance
    - [ ] Clicking on either option should show a window prompt that lets you cancel the operation
    - [ ] Clicking on the integrity check option should check the integrity and report that the backup integrity is good after a while which should then only show the option to choose the backup archive that should be restored
    - [ ] Choosing the restore option should finally restore your files. 
    - [ ] After waiting a while it should reload the page and should show the usual container interface again with the state of your containers (stopped) and the option to start and update the containers again.
- [ ] Clicking on `Start and update containers` should show a window prompt that you should create a backup. Canceling should cancel the operation, confirming should reveal the big spinner again.
- [ ] After waiting a bit, all containers should be green and your instance should be fully functional again

You can now continue with [020-backup-and-restore.md](./020-backup-and-restore.md)