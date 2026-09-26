<?php

declare(strict_types=1);

namespace Netzbewegung\NbPageActions\Tests\Functional\PageActions;

use Netzbewegung\NbPageActions\EventListener\AddPageActionLabelToggleListener;
use Netzbewegung\NbPageActions\Service\PageActionSettings;
use Netzbewegung\NbPageActions\ViewHelpers\AllLabelsHiddenViewHelper;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Module\ModuleInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownToggle;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDownButton;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The label visibility is a setting of the page module ("view" dropdown next to
 * the core entry for hidden content elements) and is persisted as module data of
 * that module, the same mechanism the core uses for viewMode and showHidden.
 */
final class PageActionSettingsTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'netzbewegung/nb_pageactions',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/BeGroups.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/BeUsers.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Pages.csv');
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences(
            $GLOBALS['BE_USER']
        );
    }

    #[Test]
    public function labelsAreHiddenByDefault(): void
    {
        self::assertTrue($this->getSettings()->areLabelsHidden());
    }

    #[Test]
    public function labelsCanBeShownAgain(): void
    {
        $this->getSettings()->hideLabels(false);

        self::assertFalse($this->getSettings()->areLabelsHidden());
    }

    #[Test]
    public function allActionLabelsAreNotHiddenWithoutTheUserSetting(): void
    {
        self::assertFalse($this->getSettings()->hidesAllActionLabels());
        self::assertFalse($this->getSettings()->areAllLabelsHidden());
    }

    #[Test]
    public function userSettingIsRegisteredInTheUserProfile(): void
    {
        $userSettings = $GLOBALS['TCA']['be_users']['columns']['user_settings'] ?? [];

        self::assertArrayHasKey(
            PageActionSettings::USER_SETTING_HIDE_ALL_LABELS,
            $userSettings['columns'] ?? [],
        );
        self::assertSame(
            'check',
            $userSettings['columns'][PageActionSettings::USER_SETTING_HIDE_ALL_LABELS]['config']['type'] ?? null,
        );
        self::assertStringContainsString(
            PageActionSettings::USER_SETTING_HIDE_ALL_LABELS,
            (string)($userSettings['showitem'] ?? ''),
        );
    }

    #[Test]
    public function viewHelperOnlyHidesAllLabelsWithTheUserSetting(): void
    {
        $viewHelper = new AllLabelsHiddenViewHelper($this->getSettings());

        self::assertFalse($viewHelper->render());

        $this->setUpBackendUser(7);
        $viewHelper = new AllLabelsHiddenViewHelper($this->getSettings());

        self::assertTrue($viewHelper->render());
    }

    #[Test]
    public function allActionLabelsAreHiddenWithTheUserSetting(): void
    {
        $this->setUpBackendUser(7);

        self::assertTrue($this->getSettings()->hidesAllActionLabels());
        self::assertTrue($this->getSettings()->areAllLabelsHidden());
    }

    #[Test]
    public function allActionLabelsAreShownAgainWithTheUserSettingAndShownLabels(): void
    {
        $this->setUpBackendUser(7);
        $this->getSettings()->hideLabels(false);

        self::assertTrue($this->getSettings()->hidesAllActionLabels());
        self::assertFalse($this->getSettings()->areAllLabelsHidden());
    }

    #[Test]
    public function labelVisibilityIsPersistedAsModuleDataOfThePageModule(): void
    {
        $this->getSettings()->hideLabels(true);

        $moduleData = $GLOBALS['BE_USER']->getModuleData('web_layout');
        self::assertTrue($this->getSettings()->areLabelsHidden());
        self::assertTrue((bool)($moduleData['hidePageActionLabels'] ?? false));
    }

    #[Test]
    public function unknownLabelVisibilityFallsBackToHiddenLabels(): void
    {
        $GLOBALS['BE_USER']->pushModuleData('web_layout', ['hidePageActionLabels' => 'garbage']);

        self::assertTrue($this->getSettings()->areLabelsHidden());
    }

    #[Test]
    public function toggleItemIsInactiveByDefaultAsLabelsAreHidden(): void
    {
        $dropdown = $this->renderButtonBarWithViewSettingsDropdown(['id' => 17, 'viewMode' => 2]);

        $toggleItem = $this->findLabelToggleItem($dropdown);
        self::assertNotNull($toggleItem);
        self::assertSame('Show page action labels', $toggleItem->getLabel());
        self::assertFalse($toggleItem->isActive());
        self::assertStringContainsString('hidePageActionLabels=0', (string)$toggleItem->getHref());
    }

    #[Test]
    public function toggleItemIsActiveWhenLabelsAreShown(): void
    {
        $this->getSettings()->hideLabels(false);

        $dropdown = $this->renderButtonBarWithViewSettingsDropdown(['id' => 17, 'viewMode' => 2]);

        $toggleItem = $this->findLabelToggleItem($dropdown);
        self::assertNotNull($toggleItem);
        self::assertTrue($toggleItem->isActive());
        self::assertStringContainsString('hidePageActionLabels=1', (string)$toggleItem->getHref());
    }

    #[Test]
    public function requestedStateFromQueryParameterIsPersisted(): void
    {
        $this->renderButtonBarWithViewSettingsDropdown(['id' => 17, 'viewMode' => 2, 'hidePageActionLabels' => '1']);

        self::assertTrue($this->getSettings()->areLabelsHidden());
    }

    #[Test]
    public function toggleItemIsAddedOnlyOnceWhenTheEventIsDispatchedRepeatedly(): void
    {
        $listener = $this->getListener();
        $componentFactory = $this->get(ComponentFactory::class);
        $dropdown = $componentFactory->createDropDownButton()->setLabel('View');
        $coreItem = $componentFactory->createDropDownGeneric();
        $coreItem->setTag('typo3-backend-page-layout-toggle-hidden');
        $dropdown->addItem($coreItem);

        $buttonBar = new ButtonBar($componentFactory, $this->createMock(EventDispatcherInterface::class));
        $request = $this->createRequest(['id' => 17], 'web_layout');
        $buttons = [
            ButtonBar::BUTTON_POSITION_RIGHT => [
                0 => [$dropdown],
            ],
        ];

        $event = new ModifyButtonBarEvent($buttons, $buttonBar, $request);
        $listener($event);
        $buttons = $event->getButtons();
        $event = new ModifyButtonBarEvent($buttons, $buttonBar, $request);
        $listener($event);

        $dropdown = $this->getDropDown($event);
        self::assertCount(2, $dropdown->getItems());
        self::assertNotNull($this->findLabelToggleItem($dropdown));
    }

    #[Test]
    public function requestedStateIsAppliedWithoutWaitingForTheButtonBarEvent(): void
    {
        // the page module renders its content before the button bar event is dispatched
        $this->createRequest(['id' => 17, 'viewMode' => 2, 'hidePageActionLabels' => '1'], 'web_layout');

        self::assertTrue($this->getSettings()->areLabelsHidden());
    }

    #[Test]
    public function requestedStateOverridesThePersistedState(): void
    {
        $this->getSettings()->hideLabels(true);
        $this->createRequest(['id' => 17, 'viewMode' => 2, 'hidePageActionLabels' => '0'], 'web_layout');

        self::assertFalse($this->getSettings()->areLabelsHidden());
    }

    #[Test]
    public function listenerIsRegisteredForTheButtonBarEvent(): void
    {
        $componentFactory = $this->get(ComponentFactory::class);
        $dropdown = $componentFactory->createDropDownButton()->setLabel('View');
        $coreItem = $componentFactory->createDropDownGeneric();
        $coreItem->setTag('typo3-backend-page-layout-toggle-hidden');
        $dropdown->addItem($coreItem);

        $event = new ModifyButtonBarEvent(
            [ButtonBar::BUTTON_POSITION_RIGHT => [0 => [$dropdown]]],
            new ButtonBar($componentFactory, $this->createMock(EventDispatcherInterface::class)),
            $this->createRequest(['id' => 17], 'web_layout')
        );

        // dispatched via the real dispatcher, so the #[AsEventListener] wiring is covered as well
        $this->get(EventDispatcherInterface::class)->dispatch($event);

        $dropdown = $this->getDropDown($event);
        self::assertNotNull($this->findLabelToggleItem($dropdown));
    }

    #[Test]
    public function otherDropdownsAreLeftUntouched(): void
    {
        $componentFactory = $this->get(ComponentFactory::class);
        $dropdown = $componentFactory->createDropDownButton()->setLabel('Page actions');
        $dropdown->addItem($componentFactory->createDropDownGeneric()->setLabel('Some other entry'));

        $buttonBar = new ButtonBar($componentFactory, $this->createMock(EventDispatcherInterface::class));
        $request = $this->createRequest(['id' => 17], 'web_layout');
        $event = new ModifyButtonBarEvent(
            [ButtonBar::BUTTON_POSITION_RIGHT => [0 => [$dropdown]]],
            $buttonBar,
            $request
        );

        $this->getListener()($event);

        $dropdown = $this->getDropDown($event);
        self::assertCount(1, $dropdown->getItems());
        self::assertNull($this->findLabelToggleItem($dropdown));
    }

    #[Test]
    public function toggleItemIsNotAddedInOtherModules(): void
    {
        $componentFactory = $this->get(ComponentFactory::class);
        $dropdown = $componentFactory->createDropDownButton()->setLabel('View');
        $coreItem = $componentFactory->createDropDownGeneric();
        $coreItem->setTag('typo3-backend-page-layout-toggle-hidden');
        $dropdown->addItem($coreItem);

        $buttonBar = new ButtonBar($componentFactory, $this->createMock(EventDispatcherInterface::class));
        $request = $this->createRequest(['id' => 17], 'records');
        $event = new ModifyButtonBarEvent(
            [ButtonBar::BUTTON_POSITION_RIGHT => [0 => [$dropdown]]],
            $buttonBar,
            $request
        );

        $this->getListener()($event);

        $dropdown = $this->getDropDown($event);
        self::assertCount(1, $dropdown->getItems());
    }

    private function getSettings(): PageActionSettings
    {
        return $this->get(PageActionSettings::class);
    }

    private function getListener(): AddPageActionLabelToggleListener
    {
        return new AddPageActionLabelToggleListener(
            $this->getSettings(),
            $this->get(ComponentFactory::class),
            $this->get(UriBuilder::class),
            $this->get(IconFactory::class),
            $this->get(LanguageServiceFactory::class),
        );
    }

    /**
     * @param array<string, int|string> $queryParams
     */
    private function renderButtonBarWithViewSettingsDropdown(array $queryParams): DropDownButton
    {
        $componentFactory = $this->get(ComponentFactory::class);
        $dropdown = $componentFactory->createDropDownButton()->setLabel('View');
        $coreItem = $componentFactory->createDropDownGeneric();
        $coreItem->setTag('typo3-backend-page-layout-toggle-hidden');
        $dropdown->addItem($coreItem);

        $buttonBar = new ButtonBar($componentFactory, $this->createMock(EventDispatcherInterface::class));
        $event = new ModifyButtonBarEvent(
            [ButtonBar::BUTTON_POSITION_RIGHT => [0 => [$dropdown]]],
            $buttonBar,
            $this->createRequest($queryParams, 'web_layout')
        );

        $this->getListener()($event);

        return $this->getDropDown($event);
    }

    private function getDropDown(ModifyButtonBarEvent $event): DropDownButton
    {
        $button = $event->getButtons()[ButtonBar::BUTTON_POSITION_RIGHT][0][0] ?? null;
        self::assertInstanceOf(DropDownButton::class, $button);

        return $button;
    }

    private function findLabelToggleItem(DropDownButton $dropdown): ?DropDownToggle
    {
        foreach ($dropdown->getItems() as $item) {
            if ($item instanceof DropDownToggle
                && isset($item->getAttributes()['data-nb-page-action-label-toggle'])
            ) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param array<string, int|string> $queryParams
     */
    private function createRequest(array $queryParams, string $moduleIdentifier): ServerRequestInterface
    {
        $request = (new ServerRequest('https://example.com/typo3/module/web/layout', 'GET'))
            ->withQueryParams($queryParams);
        // icon rendering (sprite resources) relies on the normalized params of the current request
        $request = $request
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request))
            ->withAttribute(
                'module',
                $this->createConfiguredMock(ModuleInterface::class, ['getIdentifier' => $moduleIdentifier])
            );

        $GLOBALS['TYPO3_REQUEST'] = $request;

        return $request;
    }
}
