<?php

declare(strict_types=1);

namespace Netzbewegung\NbPageActions\Service;

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Versioning\VersionState;

/**
 * Builds the "enable/disable" and "delete" actions for a page record of the
 * page module's language comparison view.
 *
 * The permission logic mirrors the core context menu provider
 * \TYPO3\CMS\Backend\ContextMenu\ItemProviders\PageProvider, so the buttons
 * are only rendered for users who are allowed to perform the action anyway.
 * The actions themselves are plain links to the DataHandler gateway (tce_db),
 * which is the same mechanism the context menu uses for enable/disable/delete.
 */
final class PageActionService
{
    private const TABLE = 'pages';

    public function __construct(
        private readonly TcaSchemaFactory $tcaSchemaFactory,
        private readonly UriBuilder $uriBuilder,
        private readonly LanguageServiceFactory $languageServiceFactory,
    ) {}

    /**
     * @param array<string, mixed> $pageRecord page record in the language of the given language column
     * @return list<array{type: string, label: string, icon: string, url: string, attributes: array<string, string>}>
     */
    public function getActions(array $pageRecord, string $returnUrl): array
    {
        $actions = [];

        $toggleVisibilityAction = $this->getToggleVisibilityAction($pageRecord, $returnUrl);
        if ($toggleVisibilityAction !== null) {
            $actions[] = $toggleVisibilityAction;
        }

        $deleteAction = $this->getDeleteAction($pageRecord, $returnUrl);
        if ($deleteAction !== null) {
            $actions[] = $deleteAction;
        }

        return $actions;
    }

    /**
     * @param array<string, mixed> $pageRecord
     * @return array{type: string, label: string, icon: string, url: string, attributes: array<string, string>}|null
     */
    private function getToggleVisibilityAction(array $pageRecord, string $returnUrl): ?array
    {
        $disabledFieldName = $this->getDisabledFieldName();
        $isHidden = (int)($pageRecord[$disabledFieldName] ?? 0) === 1;

        if (!$this->canToggleVisibility($pageRecord, $isHidden ? 1 : 0)) {
            return null;
        }

        $pageUid = (int)$pageRecord['uid'];

        return [
            'type' => $isHidden ? 'enable' : 'disable',
            'label' => $this->getLanguageService()->sL(
                'LLL:EXT:core/Resources/Private/Language/locallang_common.xlf:' . ($isHidden ? 'enable' : 'disable')
            ),
            'icon' => $isHidden ? 'actions-edit-unhide' : 'actions-edit-hide',
            'url' => (string)$this->uriBuilder->buildUriFromRoute('tce_db', [
                'data' => [
                    self::TABLE => [
                        $pageUid => [
                            $this->getDisabledFieldName() => $isHidden ? 0 : 1,
                        ],
                    ],
                ],
                'redirect' => $returnUrl,
            ]),
            'attributes' => [],
        ];
    }

    /**
     * @param array<string, mixed> $pageRecord
     * @return array{type: string, label: string, icon: string, url: string, attributes: array<string, string>}|null
     */
    private function getDeleteAction(array $pageRecord, string $returnUrl): ?array
    {
        if (!$this->canDeletePage($pageRecord)) {
            return null;
        }

        $pageUid = (int)$pageRecord['uid'];
        $languageService = $this->getLanguageService();
        $recordTitle = trim(BackendUtility::cropToTitleLength(BackendUtility::getRecordTitle(self::TABLE, $pageRecord)));
        if ($this->getBackendUser()->shallDisplayDebugInformation()) {
            $recordTitle .= ' [' . self::TABLE . ':' . $pageUid . ']';
        }

        return [
            'type' => 'delete',
            'label' => $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:cm.delete'),
            'icon' => 'actions-edit-delete',
            'url' => (string)$this->uriBuilder->buildUriFromRoute('tce_db', [
                'cmd' => [
                    self::TABLE => [
                        $pageUid => [
                            'delete' => 1,
                        ],
                    ],
                ],
                'redirect' => $returnUrl,
            ]),
            'attributes' => [
                'data-severity' => 'warning',
                'data-title' => $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:mess.delete.title'),
                'data-content' => sprintf(
                    $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:mess.delete'),
                    $recordTitle
                ),
                'data-button-close-text' => $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_common.xlf:cancel'),
                'data-button-ok-text' => $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:delete'),
            ],
        ];
    }

    /**
     * Mirrors PageProvider::canBeToggled() for the "hidden" field of pages.
     *
     * @param array<string, mixed> $pageRecord
     */
    private function canToggleVisibility(array $pageRecord, int $expectedValue): bool
    {
        return $this->hasDisableColumnWithValue($pageRecord, $expectedValue)
            && $this->canEditPage($pageRecord);
    }

    /**
     * @param array<string, mixed> $pageRecord
     */
    private function hasDisableColumnWithValue(array $pageRecord, int $value): bool
    {
        $disabledFieldName = $this->getDisabledFieldName();
        if ($disabledFieldName === '' || !array_key_exists($disabledFieldName, $pageRecord)) {
            return false;
        }
        if ($this->getPageSchema()->getField($disabledFieldName)->supportsAccessControl()
            && !$this->getBackendUser()->check('non_exclude_fields', self::TABLE . ':' . $disabledFieldName)
        ) {
            return false;
        }

        return (int)$pageRecord[$disabledFieldName] === $value;
    }

    /**
     * Mirrors PageProvider::canBeEdited() for pages.
     *
     * @param array<string, mixed> $pageRecord
     */
    private function canEditPage(array $pageRecord): bool
    {
        if ($this->isRootPage($pageRecord) || $this->isDeletePlaceholder($pageRecord)) {
            return false;
        }
        if (!$this->hasLanguageAccess($pageRecord)) {
            return false;
        }
        $pageSchema = $this->getPageSchema();
        if ($pageSchema->hasCapability(TcaSchemaCapability::AccessReadOnly)) {
            return false;
        }
        if ($this->getBackendUser()->isAdmin()) {
            return true;
        }
        if ($pageSchema->hasCapability(TcaSchemaCapability::AccessAdminOnly)) {
            return false;
        }
        if (!$this->getBackendUser()->check('tables_modify', self::TABLE)) {
            return false;
        }

        return !$this->isEditLocked($pageRecord) && $this->hasPagePermission($pageRecord, Permission::PAGE_EDIT);
    }

    /**
     * Mirrors PageProvider::canBeDeleted() for pages.
     *
     * @param array<string, mixed> $pageRecord
     */
    private function canDeletePage(array $pageRecord): bool
    {
        return $this->hasLanguageAccess($pageRecord)
            && !$this->isRootPage($pageRecord)
            && !$this->isDeletePlaceholder($pageRecord)
            && !$this->isEditLocked($pageRecord)
            && !$this->isDeletionDisabledInTsConfig()
            && $this->hasPagePermission($pageRecord, Permission::PAGE_DELETE);
    }

    /**
     * @param array<string, mixed> $pageRecord
     */
    private function hasLanguageAccess(array $pageRecord): bool
    {
        if ($this->getBackendUser()->isAdmin()) {
            return true;
        }
        $languageFieldName = $this->getPageSchema()->getCapability(TcaSchemaCapability::Language)->getLanguageField()->getName();
        if ($languageFieldName !== '' && isset($pageRecord[$languageFieldName])) {
            return $this->getBackendUser()->checkLanguageAccess((int)$pageRecord[$languageFieldName]);
        }

        return true;
    }

    /**
     * @param array<string, mixed> $pageRecord
     */
    private function hasPagePermission(array $pageRecord, int $permission): bool
    {
        if ($this->getBackendUser()->isAdmin()) {
            return true;
        }

        return (new Permission($this->getBackendUser()->calcPerms($pageRecord)))->isGranted($permission);
    }

    /**
     * @param array<string, mixed> $pageRecord
     */
    private function isEditLocked(array $pageRecord): bool
    {
        return (bool)($pageRecord[$this->getPageSchema()->getCapability(TcaSchemaCapability::EditLock)->getFieldName()] ?? false);
    }

    /**
     * @param array<string, mixed> $pageRecord
     */
    private function isDeletePlaceholder(array $pageRecord): bool
    {
        return VersionState::tryFrom((int)($pageRecord['t3ver_state'] ?? 0)) === VersionState::DELETE_PLACEHOLDER;
    }

    /**
     * @param array<string, mixed> $pageRecord
     */
    private function isRootPage(array $pageRecord): bool
    {
        return (int)($pageRecord['uid'] ?? 0) === 0;
    }

    private function isDeletionDisabledInTsConfig(): bool
    {
        $tsConfig = $this->getBackendUser()->getTSConfig();

        return (bool)trim(
            $tsConfig['options.']['disableDelete.'][self::TABLE]
            ?? $tsConfig['options.']['disableDelete']
            ?? ''
        );
    }

    private function getDisabledFieldName(): string
    {
        return $this->getPageSchema()->getCapability(TcaSchemaCapability::RestrictionDisabledField)->getField()->getName();
    }

    private function getPageSchema(): TcaSchema
    {
        return $this->tcaSchemaFactory->get(self::TABLE);
    }

    private function getLanguageService(): LanguageService
    {
        $languageService = $GLOBALS['LANG'] ?? null;
        if (!$languageService instanceof LanguageService) {
            return $this->languageServiceFactory->create('en_US');
        }

        return $languageService;
    }

    /**
     * The authenticated backend user is only available as global (the
     * authentication middleware populates $GLOBALS['BE_USER'], the container
     * holds an empty instance for dependency injection).
     */
    private function getBackendUser(): BackendUserAuthentication
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof BackendUserAuthentication) {
            throw new \RuntimeException('The global backend user is not available', 1769746300);
        }

        return $backendUser;
    }
}
