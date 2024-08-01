# Contributing and Maintaining

First, thank you for taking the time to contribute!

The following is a set of guidelines for contributors as well as information and instructions around our maintenance process.  The two are closely tied together in terms of how we all work together and set expectations, so while you may not need to know everything in here to submit an issue or pull request, it's best to keep them in the same document.

## Ways to contribute

Contributing isn't just writing code - it's anything that improves the project.  All contributions are managed right here on GitHub.  Here are some ways you can help:

### Reporting bugs

If you're running into an issue, please take a look through [existing issues](https://github.com/scoredetect/timestamps/issues) and [open a new one](https://github.com/scoredetect/timestamps/issues/new) if needed.  If you're able, include steps to reproduce, environment information, and screenshots/screencasts as relevant.

### Suggesting enhancements

New features and enhancements are also managed via [issues](https://github.com/scoredetect/timestamps/issues).

### Pull requests

Pull requests represent a proposed solution to a specified problem.  They should always reference an issue that describes the problem and contains discussion about the problem itself.  Discussion on pull requests should be limited to the pull request itself, i.e. code review.

## Workflow

The `develop` branch is the development branch which means it contains the next version to be released. `main` contains the corresponding stable development version. Always work on the `develop` branch and open up PRs against `develop`.

## Release instructions

Open a [new blank issue](https://github.com/scoredetect/timestamps/issues/new) with `[Release] 1.5.0`, then copy and paste the following items, replacing version numbers and links to the milestone.

- [ ] 1. Branch: Starting from `develop`, cut a release branch named `release/1.5.0` for your changes.
- [ ] 2. Version bump: Bump the version number in `timestamps.php`, `package.json`, `package-lock.json`, `readme.txt`, and any other relevant files if it does not already reflect the version being released. In `timestamps.php` update both the plugin "Version:" property and the plugin `SDCOM_TIMESTAMPS_VERSION` constant.
- [ ] 3. Changelog: Add/update the changelog in `CHANGELOG.md`, ensuring to link the [1.5.0] release reference in the footer of `CHANGELOG.md` (e.g., https://github.com/scoredetect/timestamps/compare/1.5.0-1...1.5.0).
- [ ] 4. Props: Update `CREDITS.md` file with any new contributors, confirm maintainers are accurate.
- [ ] 5. Readme updates: Make any other readme changes as necessary. `README.md` is geared toward GitHub and `readme.txt` contains WordPress.org-specific content. The two are slightly different.
- [ ] 6. New files: Check to be sure any new files/paths that are unnecessary in the production version are included in `.distignore`.
- [ ] 7. POT file: Run `wp i18n make-pot . lang/timestamps.pot` and commit the file. In case of errors, try to disable Xdebug.
- [ ] 8. Release date: Double check the release date in the `CHANGELOG.md` file.
- [ ] 9. Merge: Merge the release branch/PR into `develop`, then make a non-fast-forward merge from `develop` into `main` (`git checkout main && git merge --no-ff develop`). `main` contains the stable development version.
- [ ] 10. Test: While still on the `main` branch, test for functionality locally.
- [ ] 11. Push: Push your `main` branch to GitHub (e.g. `git push origin main`).
- [ ] 12. [Check the _Build and Tag_ action](https://github.com/scoredetect/timestamps/actions/workflows/build-and-tag.yml): a new tag named with the version number should've been created. It should contain all the built assets.
- [ ] 13. Release: Create a [new release](https://github.com/scoredetect/timestamps/releases/new):
  * **Tag**: The tag created in the previous step
  * **Release title**: `Version 1.5.0`
  * **Description**: Release changelog from `CHANGELOG.md` + `See: https://github.com/scoredetect/timestamps/milestone/#?closed=1`
- [ ] 14. SVN: Wait for the [GitHub Action](https://github.com/scoredetect/timestamps/actions/workflows/push-deploy.yml) to finish deploying to the WordPress.org repository. If all goes well, users with SVN commit access for that plugin will receive an emailed diff of changes.
- [ ] 15. Check WordPress.org: Ensure that the changes are live on https://wordpress.org/plugins/timestamps/. This may take a few minutes.

## Hotfix release instructions

There may be cases where we have an urgent/important fix that ideally gets into a release quickly without any other changes (e.g., a "hotfix") so as to reduce (1) the amount or testing before being confident in the release and (2) to reduce the chance of unintended side effects from the extraneous non-urgent/important changes.  In cases where code has previously been merged into `develop` but that ideally is not part of a hotfix, the normal release instructions above will not suffice as they would release all code merged to `develop` alongside the intended urgent/important "hotfix" change(s).  In case of needing to release a "hotfix" the following are the recommended steps to take.

1. Branch: Starting from `main`, cut a hotfix release branch named `hotfix/1.5.0` for your hotfix change(s).
1. Version bump: Bump the version number in `timestamps.php`, `package.json`, `readme.txt`, and any other relevant files if it does not already reflect the version being released.  In `timestamps.php` update both the plugin "Version:" property and the plugin `SDCOM_TIMESTAMPS_VERSION` constant.
1. Changelog: Add/update the changelog in `CHANGELOG.md` and `readme.txt`, ensuring to link the [1.5.0] release reference in the footer of `CHANGELOG.md` (e.g., https://github.com/scoredetect/timestamps/compare/1.5.0-1...1.5.0).
1. Props: Update `CREDITS.md` file with any new contributors, confirm maintainers are accurate.
1. Readme updates: Make any other readme changes as necessary.  `README.md` is geared toward GitHub and `readme.txt` contains WordPress.org-specific content.  The two are slightly different.
1. New files: Check to be sure any new files/paths that are unnecessary in the production version are included in `.distignore`.
1. POT file: Run `wp i18n make-pot . lang/timestamps.pot` and commit the file.
1. Release date: Double check the release date in both changelog files.
1. Merge: Merge the release branch/PR into `main`.  `main` contains the stable development version.
1. Test: While still on the `main` branch, test for functionality locally.
1. Push: Push your `main` branch to GitHub (e.g. `git push origin main`).
1. Release: Create a [new release](https://github.com/scoredetect/timestamps/releases/new), naming the tag and the release with the new version number, and targeting the `main` branch.
1. SVN: Wait for the [GitHub Action](https://github.com/scoredetect/timestamps/actions/workflows/push-deploy.yml) to finish deploying to the WordPress.org repository. If all goes well, users with SVN commit access for that plugin will receive an emailed diff of changes.
1. Check WordPress.org: Ensure that the changes are live on https://wordpress.org/plugins/timestamps/.  This may take a few minutes.
1. Apply hotfix changes to `develop`: Make a non-fast-forward merge from `main` into `develop` (`git checkout develop && git merge --no-ff main`) to ensure your hotfix change(s) are in sync with active development.