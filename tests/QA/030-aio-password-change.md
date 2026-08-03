# AIO passphrase change

- [ ] In the AIO passphrase change section you should see two input fields. And below the requirements for a new passphrase
- [ ] When entering nothing it should report that you need to enter your current AIO passphrase
- [ ] When entering a false passphrase, it should report that to you
- [ ] After entering your current passphrase and leaving the new passphrase empty it should report that you need to enter a new passphrase
- [ ] After entering a new passphrase shorter than 24 characters or not allowed characters, it should report that the passphrase requirements are not met.
- [ ] `sdfjlksj` should not be accepted
- [ ] `jdsfklöjiroewoäsadjkfölk` should not be accepted
- [ ] `sdjlfj SDJFLK 32489 sdjklf` should be accepted, which should reload the page

You can now continue with [040-login-behavior.md](./040-login-behavior.md)