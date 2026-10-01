<?php

declare(strict_types=1);

namespace Netzbewegung\NbPageActions\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * Holds the label visibility of the page action buttons, a setting of the page
 * module which is persisted as module data of that module, the same mechanism
 * the core uses for its "view" settings (e.g. viewMode or showHidden).
 *
 * The labels are hidden by default, the entry of the module's "view" dropdown
 * brings them back. The state is requested as query parameter of the page
 * module. The page module renders its content before the button bar event is
 * dispatched, so the current request has the higher priority to make the
 * setting take effect immediately.
 *
 * Both switches can be preset via user TSconfig (and therefore per backend
 * group as well), a user's own choice always wins over the TSconfig preset.
 */
final class PageActionSettings
{
    public const MODULE_IDENTIFIER = 'web_layout';
    public const QUERY_PARAMETER = 'hidePageActionLabels';
    public const USER_SETTING_HIDE_ALL_LABELS = 'nbPageActionsHideAllActionLabels';

    private const PROPERTY = self::QUERY_PARAMETER;

    /**
     * Whether the label hiding applies to all action labels of the language
     * columns or only to the page action buttons of this extension. Configured
     * as user setting in the backend user profile, presettable via the user
     * TSconfig option "options.pageActions.hideAllLabels".
     */
    public function hidesAllActionLabels(): bool
    {
        $userSettings = $this->getBackendUser()->getUserSettings()->toArray();
        if (array_key_exists(self::USER_SETTING_HIDE_ALL_LABELS, $userSettings)) {
            return (bool)$userSettings[self::USER_SETTING_HIDE_ALL_LABELS];
        }

        return $this->getTsConfigFlag('hideAllLabels') ?? false;
    }

    /**
     * Whether the labels of all action labels of the language columns are left
     * out: the "view" setting of the page module is active and the user
     * configured it with the user setting "Hide all action labels".
     */
    public function areAllLabelsHidden(): bool
    {
        return $this->areLabelsHidden() && $this->hidesAllActionLabels();
    }

    public function areLabelsHidden(): bool
    {
        $requestedState = $this->getRequestedState();
        if ($requestedState !== null) {
            return $requestedState;
        }

        $moduleData = $this->getBackendUser()->getModuleData(self::MODULE_IDENTIFIER);
        if (is_array($moduleData) && array_key_exists(self::PROPERTY, $moduleData)) {
            return $this->isShown($moduleData[self::PROPERTY]) === false;
        }

        return $this->getTsConfigFlag('hideLabels') ?? true;
    }

    public function hideLabels(bool $hide): void
    {
        $backendUser = $this->getBackendUser();
        $moduleData = $backendUser->getModuleData(self::MODULE_IDENTIFIER);
        $moduleData = is_array($moduleData) ? $moduleData : [];
        $moduleData[self::PROPERTY] = $hide;

        $backendUser->pushModuleData(self::MODULE_IDENTIFIER, $moduleData);
    }

    private function getRequestedState(): ?bool
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request instanceof ServerRequestInterface) {
            return null;
        }

        $requestedValue = $request->getQueryParams()[self::QUERY_PARAMETER] ?? null;
        if ($requestedValue === '0' || $requestedValue === false) {
            return false;
        }
        if ($requestedValue === '1' || $requestedValue === true) {
            return true;
        }

        return null;
    }

    private function getTsConfigFlag(string $key): ?bool
    {
        $options = $this->getBackendUser()->getTSConfig()['options.'] ?? null;
        if (!is_array($options)) {
            return null;
        }

        $pageActions = $options['pageActions.'] ?? null;
        if (!is_array($pageActions)) {
            return null;
        }

        $value = $pageActions[$key] ?? null;
        if (!is_string($value) || $value === '') {
            return null;
        }

        return (bool)(int)$value;
    }

    private function isShown(mixed $value): bool
    {
        return $value === false || $value === 0 || $value === '0';
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof BackendUserAuthentication) {
            throw new \RuntimeException('The global backend user is not available', 1769746300);
        }

        return $backendUser;
    }
}
