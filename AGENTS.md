# AGENTS.md - nb_pageactions

## Project Overview

TYPO3 backend extension. Adds *Enable/Disable* and *Delete* buttons for pages to
the language comparison view of the page module, plus a "Show page action
labels" toggle in the page module's view dropdown and a "Hide all action
labels" user setting. The buttons are built from the same permission logic as
the core context menu
(`\TYPO3\CMS\Backend\ContextMenu\ItemProviders\PageProvider`).

## Technical Details

- **Namespace**: `Netzbewegung\NbPageActions\`
- **PHP-Version**: ^8.2
- **TYPO3**: ^14.3 ONLY (no TYPO3 13 support)
- **Dependencies**: `typo3/cms-core` ^14.3, `typo3/cms-backend` ^14.3 — no
  other extension dependencies
- **Version**: 0.1.0 (state alpha) in `composer.json` (`extra.typo3/cms.version`)
  and `ext_emconf.php`; `ext_emconf.php` constraints: typo3 14.3.0-14.9.99 /
  php 8.2.0-8.4.99; `composer.json` mirrors this (`Package.providesPackages`
  empty)

## Directory Structure

```
Classes/
├── EventListener/
│   └── AddPageActionLabelToggleListener.php   # adds the "Show page action labels"
│                                              # toggle to the page module view
│                                              # dropdown (ModifyButtonBarEvent)
├── Service/
│   ├── PageActionService.php                 # builds the enable/disable + delete
│   │                                          # actions per page with the permission
│   │                                          # logic of the core context menu
│   └── PageActionSettings.php                # label visibility state: requested
│                                              # as query parameter, persisted as
│                                              # module data of the page module +
│                                              # the "Hide all action labels"
│                                              # user setting
└── ViewHelpers/
    ├── PageActionButtonsViewHelper.php       # renders the action buttons of a
    │                                          # language column (argument:
    │                                          # languageColumn)
    └── AllLabelsHiddenViewHelper.php         # whether the labels of all action
                                               # buttons are hidden

Configuration/
├── Services.yaml                             # DI setup; PageActionService,
│                                              # PageActionSettings and the
│                                              # ViewHelpers are public (resolved
│                                              # through the container by Fluid /
│                                              # the event listener)
├── Sets/PageActions/
│   ├── config.yaml                           # set definition (netzbewegung/nb-pageactions)
│   └── page.tsconfig                         # registers the template override path
│                                              # for the typo3/cms-backend routes
└── TCA/Overrides/
    └── be_users.php                          # registers the "Hide all action
                                               # labels" user setting

Resources/
├── Private/
│   ├── Language/                             # locallang + locallang_tca, each with
│   │                                          # de translation
│   └── TemplateOverrides/typo3/cms-backend/Partials/PageLayout/
│       └── LanguageColumns.fluid.html         # copy of the EXT:backend core
│                                              # partial plus the additions of
│                                              # this extension
└── Public/Icons/
    └── Extension.svg

Documentation/
├── guides.xml                                # render-guides config (docs.typo3.org)
└── Index.md                                  # documentation index (Markdown sources)
```

## Documentation Rules

- **Docs change with the code in the same PR/commit** — a behavior change
  without a docs change is incomplete.
- **One page = one topic type** (tutorial / how-to / reference / concept),
  with a first-line purpose statement. The docs are Markdown sources: the
  entry point is `Documentation/Index.md`, the render config lives in
  `Documentation/guides.xml`.
- **Rendered docs**: `Documentation/` renders via the official render-guides
  toolchain to docs.typo3.org (renderdocs webhook / TER upload) at
  https://docs.typo3.org/p/netzbewegung/nb_pageactions/main/en-us/.
  Local: `make docs` renders (into `Documentation-GENERATED-temp/`),
  `make test-docs` fails on warnings (CI runs the same via
  `.github/workflows/test-documentation.yml` with the render-guides docker
  image `ghcr.io/typo3-documentation/render-guides`).
- All shipped content is **English** (American spelling).
- Before committing docs changes: run `Build/Scripts/checkDocs.sh` (link
  checker for relative Markdown links and anchors).

## Core Components

### Services

| Class | Purpose |
|---|---|
| `PageActionService` | enable/disable + delete actions per page/language column, only for actions the user is allowed to perform (page permissions, access to the `hidden` field, edit locks, workspace placeholders, language access, page TSconfig); plain links to the DataHandler gateway (tce_db) |
| `PageActionSettings` | label visibility (hidden by default): requested state (query parameter of the page module) takes priority, then module data of the page module, then the `nbPageActionsHideAllActionLabels` user setting |

### EventListener

`AddPageActionLabelToggleListener` — listens to `ModifyButtonBarEvent`, adds
the "Show page action labels" entry to the page module's *View* dropdown (next
to the core entry for hidden content elements) and persists the requested
state as module data.

### ViewHelpers

`PageActionButtonsViewHelper` and `AllLabelsHiddenViewHelper` — used by the
overridden core partial `LanguageColumns.fluid.html`.

### Dependencies (Constructor Injection)

**PageActionService:** `TcaSchemaFactory`, `UriBuilder`, `LanguageServiceFactory`

**AddPageActionLabelToggleListener:** `PageActionSettings`, `ComponentFactory`, `UriBuilder`, `IconFactory`, `LanguageServiceFactory`

**PageActionButtonsViewHelper:** `PageActionService`, `PageActionSettings`, `IconFactory`

**AllLabelsHiddenViewHelper:** `PageActionSettings`

## Important Notes

### Git Workflow

- Documentation renders to docs.typo3.org on every master push via the
  repo webhook (`https://docs-hook.typo3.org`, push events, no secret —
  the legacy `renderdocs.typo3.org/hook` endpoint is dead). The repo
  needed a one-time approval by the TYPO3 Documentation Team (extension
  published on TER with matching key + repo linked on the TER detail
  page); first pushes may answer `412` until approved. Render status:
  https://intercept.typo3.com/admin/docs/deployments
- docs.typo3.org renders only supported branches: `master` (published as
  `main`), `documentation-draft` (unindexed draft URL for testing) and
  numeric `X.Y` version branches. Feature branches are ignored by the
  server; versioned docs come from TER releases automatically.
- Before every commit: Run CGL and PHPStan (`Build/Scripts/runTests.sh -s cgl` / `-s phpstan`)
- New releases/tags: `Build/Scripts/tag-version.sh <x.y.z> [message]` — sets the version in
  `composer.json` (`extra.typo3/cms.version`) and `ext_emconf.php`, commits both
  and creates the annotated git tag (requires a clean working tree).
- Push master and the tag separately: `git push origin master` first, wait for
  the Tests workflow to pass, then `git push origin <tag>`. The TER publish on
  tag push does NOT wait for the test results — the split keeps a red build
  from reaching the TER.
- Pushing a tag triggers `.github/workflows/publish.yml`, which publishes the
  version to TER via typo3/tailor (requires the `TYPO3_API_TOKEN` repo secret and
  a registered TER extension key). The workflow can also be started manually
  ("Run workflow") with an existing tag and an optional release comment.
- TER zip content is controlled by `Build/tailor/ExcludeFromPackaging.php`
  (tailor ignores .gitattributes export-ignore and uses the file via the
  `TYPO3_EXCLUDE_FROM_PACKAGING` env var). The file REPLACES tailor's default
  exclusion list — it replicates the defaults plus the `.gitattributes`
  export-ignores — keep both in sync. `Documentation/` ships on purpose.
- Before releasing a new version: update `CHANGELOG.md` — turn the
  `[Unreleased]` section into the new version with the release date
  (Keep a Changelog format, including the compare link definitions at
  the bottom of the file and the `[Unreleased]` link) and commit it
  **before** running `tag-version.sh`.

### Language

- All documentation and comments in this project are written in **English**.

### Code Changes

- `final` classes with `readonly` promoted constructor properties
- No `GeneralUtility::makeInstance()` in `Classes/` — dependencies go through
  DI. Where the backend context populates them, the classes access
  `$GLOBALS['BE_USER']`, `$GLOBALS['LANG']` and `$GLOBALS['TYPO3_REQUEST']`
  directly (with `null` fallbacks).
- `Services.yaml` uses `autowire: true` / `autoconfigure: true`; the event
  listener is registered via the `#[AsEventListener]` attribute.

### External Dependencies

- None beyond TYPO3 core (`typo3/cms-core`, `typo3/cms-backend`).
- `Resources/Private/TemplateOverrides/typo3/cms-backend/Partials/PageLayout/LanguageColumns.fluid.html`
  is a **copy of the EXT:backend core partial plus the additions of this
  extension** (the override path is registered for the typo3/cms-backend
  routes in `Configuration/Sets/PageActions/page.tsconfig`) — when the core
  partial changes, the copy must be updated manually.

## Testing Framework

### Tools

| Tool | Version | Purpose |
|---|---|---|
| PHPUnit | 13.x | Test execution |
| TYPO3 Testing Framework | ^9.5 | Bootstrap, FunctionalTestCase base class |
| PHPStan | ^2.1 (Level 5) | Static analysis |
| PHP-CS-Fixer | ^3.22 | Coding standards |
| runTests.sh | Docker (or podman) | Test runner |

Tests extend `TYPO3\TestingFramework\Core\Functional\FunctionalTestCase`
directly — `netzbewegung/nb-testing-framework` is NOT used here. There are no
unit tests and no acceptance/e2e tests yet.

### Directory Structure

```
Build/
├── phpunit/
│   ├── UnitTests.xml                          # wired, but no tests yet
│   ├── UnitTestsBootstrap.php
│   ├── FunctionalTests.xml
│   └── FunctionalTestsBootstrap.php
├── phpstan/
│   ├── phpstan.neon
│   ├── phpstan.local.neon
│   ├── phpstan.ci.neon
│   └── phpstan-constants.php
├── php-cs-fixer/
│   └── config.php
├── tailor/
│   └── ExcludeFromPackaging.php
└── Scripts/
    ├── runTests.sh
    ├── checkDocs.sh
    └── tag-version.sh

Tests/
└── Functional/
    └── PageActions/
        ├── PageActionServiceTest.php          # permission matrix of the action buttons
        ├── PageActionSettingsTest.php         # label visibility settings + user setting
        └── Fixtures/                          # CSV fixtures: BeGroups.csv, BeUsers.csv, Pages.csv
```

There is no test fixture extension (no Tests/Fixtures/Extensions) — the
extension under test is loaded via `$testExtensionsToLoad`.

### Commands

```bash
# All tests (composer.json "tests" script: cgl + phpstan + functional)
Build/Scripts/runTests.sh -s cgl && Build/Scripts/runTests.sh -s phpstan && Build/Scripts/runTests.sh -s functional

# Functional tests only (sqlite is the default DBMS)
Build/Scripts/runTests.sh -s functional

# Functional tests on mariadb
Build/Scripts/runTests.sh -s functional -d mariadb

# CGL check
Build/Scripts/runTests.sh -s cgl

# PHPStan
Build/Scripts/runTests.sh -s phpstan

# Unit suite — currently has NO tests (the suite is wired in runTests.sh,
# Tests/Unit/ does not exist)
Build/Scripts/runTests.sh -s unit

# Specify PHP version
Build/Scripts/runTests.sh -s functional -p 8.4

# Functional tests on sqlite explicitly (e.g. in non-interactive environments)
Build/Scripts/runTests.sh -s functional -d sqlite -p 8.4

# Code coverage (functional, HTML report, Clover XML and raw .cov data in .Build/coverage/)
Build/Scripts/runTests.sh -s functional -d sqlite -k

# Merge coverage into combined HTML report (.Build/coverage/merged/),
# merged Clover XML and AI-friendly text summary (.Build/coverage/merged.txt).
# Use the same PHP version (-p) as for the coverage runs.
Build/Scripts/runTests.sh -s mergeCoverage
```

**Note:** `runTests.sh` detects non-interactive shells automatically and drops the
`-it` flag. `CI=true` is only required when using the CI PHPStan config
(`phpstan.ci.neon`).

**Running PHP directly:** There is no PHP on the host, but DDEV is running. Use
`ddev exec php ...` (also for composer, phpstan, php-cs-fixer, etc.). Composer
binaries are in `.Build/bin/`, e.g. `ddev exec .Build/bin/phpunit --version`.

### Testing Gotchas

- **act basics** (installation, available jobs, matrix invocation):
  `CONTRIBUTING.md` → "Running the GitHub Actions workflows locally (act)".
- **act: run one matrix entry at a time** — `act -j functional_tests` runs all
  matrix entries in parallel. Each job starts 4 docker containers (redis, memcached,
  DB, phpunit) on the shared daemon; `runTests.sh`'s `waitFor()` aborts after ~10s,
  so parallel runs fail with `Can not connect ... Aborting`. Use
  `act -j functional_tests --matrix typo3:^14.3 --matrix php:8.2` and
  `--matrix typo3:^14.3 --matrix php:8.4` separately.
- **act: composer cache is evicted after 7 days unused** — so workflows that haven't
  run in a week re-download dependencies. The cache key is `hashFiles('**/composer.json')`,
  so switching TYPO3 versions also invalidates it.
- **Root-owned test folders after `act`** — `act` runs its job containers as root, leaving
  root-owned `.Build/public/typo3temp/var/tests/functional-*` folders. Local
  `runTests.sh -s functional` then fails with
  `TYPO3\TestingFramework\Core\Exception: Can not remove folder`. Detect with
  `find .Build/public/typo3temp/var/tests -maxdepth 1 -user root`. Do NOT run `sudo`
  yourself — tell the USER (who has root) to run `sudo rm -rf <folders>`.
- **Stale DI container / cache issues** — if services resolve wrongly or newly added
  DI configuration (Services.yaml) is not picked up:
  `ddev typo3 cache:flush`, `ddev composer dump-autoload`, or `rm -rf var/cache/`.

### Test Strategy

- **Functional Tests** (`Tests/Functional/PageActions/`): the permission matrix
  of the action buttons for a non-admin user group matrix, the label
  visibility settings (query parameter / module data), the user setting
  registration and the button bar listener. Runs on sqlite (mariadb optional);
  CSV fixtures provide be_groups/be_users/pages.

## Development

### Setup

```bash
# Start DDEV
ddev start

# Install dependencies (the pre-autoload-dump hook copies Build/config.example
# to config/ on the first install)
ddev composer install
```

### DDEV

- The DDEV project name derives from the directory name — there is no `name:`
  override in `.ddev/config.yaml` (a copy of the project in another directory
  runs under its own name).
- Docroot: `.Build/public`; PHP 8.2 in the DDEV container.

### Directories

- `.Build/vendor` - Composer Vendor Directory
- `.Build/bin` - Composer Binaries
- `.Build/public` - Web Root (TYPO3)

### Workflow

1. Run `ddev composer install`
2. Run tests with `Build/Scripts/runTests.sh`
3. Before commits: Check CGL and PHPStan

## TYPO3 Version Compatibility

**TYPO3 ^14.3 ONLY** — there is no TYPO3 13 support and no version switching.
All tests (functional) and PHPStan must pass with `typo3/cms-core:^14.3`. CI
(`.github/workflows/tests.yml`) runs the functional suite on TYPO3 ^14.3 with
PHP 8.2 and 8.4 (sqlite) and PHPStan on PHP 8.4.
