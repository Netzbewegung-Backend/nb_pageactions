<?php

declare(strict_types=1);

namespace Netzbewegung\NbPageActions\Tests\Functional\PageActions;

use Netzbewegung\NbPageActions\Service\PageActionService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The buttons of the page module's language comparison view are built from the
 * very same permission logic as the core context menu
 * (\TYPO3\CMS\Backend\ContextMenu\ItemProviders\PageProvider), so the tests
 * below pin the expected behaviour for a non-admin user group matrix.
 */
final class PageActionServiceTest extends FunctionalTestCase
{
    private const RETURN_URL = '/typo3/module/web/layout?id=17&viewMode=2&languages=0,1';

    protected array $testExtensionsToLoad = [
        'netzbewegung/nb_pageactions',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/BeGroups.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/BeUsers.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Pages.csv');
    }

    #[Test]
    public function adminGetsDisableAndDeleteActionForVisiblePage(): void
    {
        $actions = $this->getActions(1, 2);

        self::assertSame(['disable', 'delete'], array_column($actions, 'type'));
        self::assertNotSame('', $actions[0]['label']);
        self::assertSame('actions-edit-hide', $actions[0]['icon']);
        self::assertSame('actions-edit-delete', $actions[1]['icon']);
    }

    #[Test]
    public function adminGetsEnableActionForHiddenPage(): void
    {
        $actions = $this->getActions(1, 4);

        self::assertSame(['enable', 'delete'], array_column($actions, 'type'));
        self::assertSame('actions-edit-unhide', $actions[0]['icon']);
    }

    #[Test]
    public function toggleActionTargetsTheDataHandlerGatewayOfTheOwnRecord(): void
    {
        [$toggleAction] = $this->getActions(2, 2);

        $url = urldecode($toggleAction['url']);
        self::assertStringContainsString('/typo3/record/commit', $url);
        self::assertStringContainsString('data[pages][2][hidden]=1', $url);
        self::assertStringContainsString('redirect=' . self::RETURN_URL, $url);
    }

    #[Test]
    public function deleteActionIsAConfirmableDataHandlerLink(): void
    {
        $actions = $this->getActions(2, 2);
        $deleteAction = $actions[1];

        $url = urldecode($deleteAction['url']);
        self::assertStringContainsString('cmd[pages][2][delete]=1', $url);
        self::assertStringContainsString('redirect=' . self::RETURN_URL, $url);
        self::assertSame('warning', $deleteAction['attributes']['data-severity']);
        self::assertStringContainsString('Visible page', $deleteAction['attributes']['data-content']);
        self::assertNotSame('', $deleteAction['attributes']['data-title']);
    }

    #[Test]
    public function editorWithPagePermissionsGetsBothActions(): void
    {
        self::assertSame(
            ['disable', 'delete'],
            array_column($this->getActions(2, 2), 'type')
        );
    }

    #[Test]
    public function editorWithoutHiddenFieldPermissionGetsNoToggleAction(): void
    {
        self::assertSame(
            ['delete'],
            array_column($this->getActions(3, 2), 'type')
        );
    }

    #[Test]
    public function editorWithoutTablesModifyGetsDeleteButNoToggleAction(): void
    {
        self::assertSame(
            ['delete'],
            array_column($this->getActions(4, 2), 'type')
        );
    }

    #[Test]
    public function lockedPageYieldsNoActions(): void
    {
        self::assertSame([], $this->getActions(2, 5));
    }

    #[Test]
    public function pageWithoutPermissionsYieldsNoActions(): void
    {
        self::assertSame([], $this->getActions(2, 6));
    }

    #[Test]
    public function deletePlaceholderRecordYieldsNoActions(): void
    {
        self::assertSame([], $this->getActions(2, 7));
    }

    #[Test]
    public function translationIsOnlyActionableForUsersWithAccessToItsLanguage(): void
    {
        self::assertSame(
            ['disable', 'delete'],
            array_column($this->getActions(2, 3), 'type')
        );
        self::assertSame([], $this->getActions(5, 3));
        self::assertSame(
            ['disable', 'delete'],
            array_column($this->getActions(5, 2), 'type')
        );
    }

    #[Test]
    public function deleteIsWithdrawnWhenDisabledViaPageTsConfig(): void
    {
        self::assertSame(
            ['disable'],
            array_column($this->getActions(6, 2), 'type')
        );
    }

    /**
     * @return list<array{type: string, label: string, icon: string, url: string, attributes: array<string, string>}>
     */
    private function getActions(int $backendUserUid, int $pageUid): array
    {
        $backendUser = $this->setUpBackendUser($backendUserUid);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);

        return $this->getActionsForUser($backendUser, $pageUid);
    }

    /**
     * @return list<array{type: string, label: string, icon: string, url: string, attributes: array<string, string>}>
     */
    private function getActionsForUser(BackendUserAuthentication $backendUser, int $pageUid): array
    {
        return $this->get(PageActionService::class)->getActions(
            $this->getPageRecord($pageUid),
            self::RETURN_URL
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getPageRecord(int $pageUid): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder
            ->select('*')
            ->from('pages')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageUid)))
            ->executeQuery()
            ->fetchAssociative() ?: [];
    }
}
