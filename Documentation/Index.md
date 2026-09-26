# Introduction

EXT:nb_pageactions adds *Enable/Disable* and *Delete* buttons for pages to
the language comparison view of the TYPO3 page module.

The buttons are built from the very same permission logic as the core
context menu (`\TYPO3\CMS\Backend\ContextMenu\ItemProviders\PageProvider`):
a user only sees the actions they are allowed to perform on the page —
page permissions, access to the `hidden` field, edit locks, workspace
placeholders, language access and page TSconfig are taken into account.

## Show page action labels

The page module's *View* dropdown (next to the core entry for hidden
content elements) contains the toggle *Show page action labels*. The
state is persisted as module data of the page module for the backend
user, the same mechanism the core uses for its view settings (view mode,
hidden content elements).

With hidden labels the buttons stay accessible: their labels remain
available as `title` and `aria-label`.

## Hide all action labels

The backend user setting *Hide all action labels* (User settings →
Personal data) extends the label hiding of the page module to all action
buttons of the language columns — *View page*, *Edit page properties*,
*Translate* and the page action buttons of this extension.

## Installation

The extension requires TYPO3 14.3+ and PHP 8.2+:

```bash
composer require netzbewegung/nb_pageactions
```
