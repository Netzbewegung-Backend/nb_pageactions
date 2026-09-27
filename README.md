[![TYPO3 compatibility](https://img.shields.io/badge/TYPO3-14.3-ff8700?maxAge=3600&logo=typo3)](https://get.typo3.org/)

# EXT:nb_pageactions

Adds enable, disable and delete buttons for pages to the language comparison
view of the page module of [TYPO3](https://typo3.org/) 14.3+.

|                    | URL                                                              |
|--------------------|------------------------------------------------------------------|
| **Repository:**    | https://github.com/Netzbewegung-Backend/nb_pageactions           |
| **Documentation:** | https://docs.typo3.org/p/netzbewegung/nb_pageactions/main/en-us/ |
| **TER:**           | https://extensions.typo3.org/extension/nb_pageactions             |
| **Packagist:**     | https://packagist.org/packages/netzbewegung/nb_pageactions       |

## What it does

- Adds *Disable/Enable* and *Delete* buttons for pages below the action
  buttons of each language column in the language comparison view of the
  page module.
- The buttons are built from the same permission logic as the core context
  menu (`PageProvider`): page permissions, access to the `hidden` field,
  edit locks, workspace placeholders, language access and page TSconfig
  decide which actions a user sees.
- "Show page action labels" toggle in the page module's *View* dropdown:
  hides and shows the button labels. The state is persisted as module data
  of the page module for the backend user, the same mechanism the core
  uses for its view settings. With hidden labels the buttons stay
  accessible: the labels remain available as `title` and `aria-label`.
- User setting *Hide all action labels*: extends the label hiding to all
  action buttons of the language columns, not only to the buttons of this
  extension.

## Installation

Requires TYPO3 14.3+ and PHP 8.2+:

```bash
composer require netzbewegung/nb_pageactions
```

## Development

Run the test suites (docker-isolated, like TYPO3 core):

```bash
Build/Scripts/runTests.sh -s composerInstall
Build/Scripts/runTests.sh -s cgl
Build/Scripts/runTests.sh -s phpstan
Build/Scripts/runTests.sh -s functional -d sqlite
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for details.
