<?php

declare(strict_types=1);

namespace Netzbewegung\NbPageActions\EventListener;

use Netzbewegung\NbPageActions\Service\PageActionSettings;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Module\ModuleInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\AbstractDropDownItem;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownToggle;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDownButton;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * Adds the "hide page action labels" entry to the "view" dropdown of the page
 * module, next to the core entry for hidden content elements.
 *
 * The state is requested as query parameter and persisted as module data of the
 * page module (like viewMode or showHidden do). The page module renders its
 * content before this event is dispatched, so the requested state is already
 * applied to the buttons of the current request by PageActionSettings.
 */
#[AsEventListener]
final class AddPageActionLabelToggleListener
{
    private const MODULE_IDENTIFIER = PageActionSettings::MODULE_IDENTIFIER;
    private const QUERY_PARAMETER = PageActionSettings::QUERY_PARAMETER;
    private const ITEM_MARKER = 'data-nb-page-action-label-toggle';
    private const CORE_VIEW_SETTINGS_ITEM_TAG = 'typo3-backend-page-layout-toggle-hidden';
    private const LABEL = 'LLL:EXT:nb_pageactions/Resources/Private/Language/locallang.xlf:showPageActionLabels';

    public function __construct(
        private readonly PageActionSettings $pageActionSettings,
        private readonly ComponentFactory $componentFactory,
        private readonly UriBuilder $uriBuilder,
        private readonly IconFactory $iconFactory,
        private readonly LanguageServiceFactory $languageServiceFactory,
    ) {}

    public function __invoke(ModifyButtonBarEvent $event): void
    {
        $request = $event->getRequest();
        if (!$this->isPageModuleRequest($request)) {
            return;
        }

        $this->applyRequestedState($request);

        // @extensionScannerIgnoreLine (ModifyButtonBarEvent::getButtons)
        $buttons = $event->getButtons();
        foreach ($buttons as $position => $buttonGroups) {
            foreach ($buttonGroups as $group => $buttonsOfGroup) {
                foreach ($buttonsOfGroup as $button) {
                    if ($button instanceof DropDownButton && $this->isViewSettingsDropdown($button)) {
                        $button->addItem($this->createToggleItem($request));
                    }
                }
            }
        }
        $event->setButtons($buttons);
    }

    private function isPageModuleRequest(ServerRequestInterface $request): bool
    {
        $module = $request->getAttribute('module');

        return $module instanceof ModuleInterface
            && $module->getIdentifier() === self::MODULE_IDENTIFIER;
    }

    private function applyRequestedState(ServerRequestInterface $request): void
    {
        $requestedValue = $request->getQueryParams()[self::QUERY_PARAMETER] ?? null;
        if ($requestedValue === '0' || $requestedValue === '1') {
            $this->pageActionSettings->hideLabels($requestedValue === '1');
        }
    }

    /**
     * The "view" dropdown is identified by the core entry for hidden content
     * elements, so no translated label comparison is needed. The check for an
     * already existing toggle keeps the listener idempotent, the event is
     * dispatched on every ButtonBar::getButtons() call.
     */
    private function isViewSettingsDropdown(DropDownButton $button): bool
    {
        $viewSettingsFound = false;
        foreach ($button->getItems() as $item) {
            if (!$item instanceof AbstractDropDownItem) {
                continue;
            }
            if ($item->getTag() === self::CORE_VIEW_SETTINGS_ITEM_TAG) {
                $viewSettingsFound = true;
            }
            if (isset($item->getAttributes()[self::ITEM_MARKER])) {
                return false;
            }
        }

        return $viewSettingsFound;
    }

    private function createToggleItem(ServerRequestInterface $request): DropDownToggle
    {
        $labelsHidden = $this->pageActionSettings->areLabelsHidden();
        $queryParams = $request->getQueryParams();
        $queryParams[self::QUERY_PARAMETER] = $labelsHidden ? '0' : '1';
        $label = $this->getLanguageService()->sL(self::LABEL);

        // the entry is the "show labels" option, so it is active while the labels are shown
        return $this->componentFactory->createDropDownToggle()
            ->setHref((string)$this->uriBuilder->buildUriFromRoute(self::MODULE_IDENTIFIER, $queryParams))
            ->setLabel($label)
            ->setTitle($label)
            ->setIcon($this->iconFactory->getIcon('actions-eye', IconSize::SMALL))
            ->setActive(!$labelsHidden)
            ->setAttributes([self::ITEM_MARKER => '1']);
    }

    private function getLanguageService(): LanguageService
    {
        $languageService = $GLOBALS['LANG'] ?? null;
        if (!$languageService instanceof LanguageService) {
            return $this->languageServiceFactory->create('en_US');
        }

        return $languageService;
    }
}
