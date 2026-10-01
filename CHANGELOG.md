# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Added

- Initial release: *Enable/Disable* and *Delete* buttons for pages below
  the action buttons of each language column in the language comparison
  view of the page module, built from the same permission logic as the
  core context menu.
- "Show page action labels" toggle in the page module's view dropdown; the
  label visibility is persisted as module data of the page module, like
  the core view settings.
- User setting "Hide all action labels" that applies the label hiding of
  the page module to all action buttons of the language columns.
- User TSconfig presets `options.pageActions.hideLabels` and
  `options.pageActions.hideAllLabels` to configure both switches per
  backend group; a user's own choice always wins over the preset.
- Functional test suite covering the permission matrix, the label
  visibility settings, the user setting registration and the button bar
  listener, with CSV fixtures.
- CI: CGL, PHPStan (level 5) and functional tests (TYPO3 14.3, PHP 8.2
  and 8.4, SQLite), a documentation render check and a TER publish
  workflow via tailor, triggered by pushing a version tag.
