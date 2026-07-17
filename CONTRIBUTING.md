<!--
SPDX-FileCopyrightText: 2021-2024 Nextcloud GmbH and Nextcloud contributors
SPDX-License-Identifier: MIT
-->

## Submitting issues

If you have questions about how to install or use Nextcloud, please direct these to our [forum][forum].

### Guidelines
* Please search the existing issues first, it's likely that your issue was already reported or even fixed.
  - Go to one of the repositories, click "issues" and type any word in the top search/command bar.
  - More info on [search syntax within github](https://help.github.com/articles/searching-issues)
* __SECURITY__: Report any potential security bug to us via [our HackerOne page](https://hackerone.com/nextcloud) following our [security policy](https://nextcloud.com/security/) instead of filing an issue in our bug tracker.
* The issues in other components should be reported in their respective repositories: You will find them in our [GitHub Organization](https://github.com/nextcloud/)
* Report the issue using one of our templates, they include all the information we need to track down the issue.

Help us to maximize the effort we can spend fixing issues and adding new features, by not reporting duplicate issues.

[forum]: https://help.nextcloud.com/

## Contributing to Source Code

Thanks for wanting to contribute source code to Nextcloud. That's great!

Please read the [general contribution guidelines][contributing] that apply to all Nextcloud repositories, and the [AI Contribution Policy][aipolicy] if you are using AI tools.
Please read the [Developer Manuals][devmanual] to learn how to create your first application or how to test the Nextcloud code.

### AI-assisted contributions

Nextcloud allows contributions made with the help of AI tools. You are the author of everything you submit - AI assistance does not change that responsibility.

* **Disclosure:** Declare AI tool use in the PR description and add an `Assisted-by: AGENT_NAME:MODEL_VERSION` git trailer to each affected commit.

* **Accountability:** You must be able to explain, defend, and modify every line you submit. If a reviewer asks why something works a certain way, "the AI wrote it" is not an answer.

* **Communication:** PR descriptions, review comments, and issue reports must be written in your own words. This applies throughout the review process - passing reviewer feedback to an AI and posting whatever comes out is not acceptable.

* **Quality:** AI output must be quality assured by the human, i.e. reviewed, cleaned up, and tested before submission. New features must be tested on a live instance by you, not by an agent. Code that has never been executed, or that shifts debugging work onto maintainers, will not be accepted.

* **Licensing:** Ensure AI-generated code contains no material incompatible with the license of the repository you are contributing to.

For the full policy including autonomous agent rules, security reports, and beginner issues, read the [AI Contribution Policy][aipolicy].

### Tests

We are striving to increase the quality and reliability of our software by improving its test suite, and encourage to add or extend playwright test cases for every relevant code change.

### Sign your work

We use the Developer Certificate of Origin (DCO) as a additional safeguard
for the Nextcloud project. This is a well established and widely used
mechanism to assure contributors have confirmed their right to license
their contribution under the project's license.
Please read [contribute/developer-certificate-of-origin][dcofile].
If you can certify it, then just add a line to every git commit message:

````
  Signed-off-by: Random J Developer <random@developer.example.org>
````

Use your real name (sorry, no pseudonyms or anonymous contributions).
If you set your `user.name` and `user.email` git configs, you can sign your
commit automatically with `git commit -s`. You can also use git [aliases](https://git-scm.com/book/tr/v2/Git-Basics-Git-Aliases)
like `git config --global alias.ci 'commit -s'`. Now you can commit with
`git ci` and the commit will be signed.

### Apply a license

In case you are not sure how to add or update the license header correctly please have a look at [contribute/HowToApplyALicense.md][applyalicense]

[devmanual]: https://github.com/nextcloud/all-in-one/blob/main/develop.md
[dcofile]: https://github.com/nextcloud/server/blob/master/contribute/developer-certificate-of-origin
[applyalicense]: https://github.com/nextcloud/server/blob/master/contribute/HowToApplyALicense.md
[contributing]: https://github.com/nextcloud/.github/blob/master/CONTRIBUTING.md
[aipolicy]: https://github.com/nextcloud/.github/blob/master/AI_POLICY.md
