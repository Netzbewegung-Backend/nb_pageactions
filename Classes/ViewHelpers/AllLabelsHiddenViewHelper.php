<?php

declare(strict_types=1);

namespace Netzbewegung\NbPageActions\ViewHelpers;

use Netzbewegung\NbPageActions\Service\PageActionSettings;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Returns whether the labels of all action labels in the language columns of
 * the page module are hidden, which is the case if the "Show page action
 * labels" setting of the module is inactive and the user configured it with the
 * user setting "Hide all action labels".
 */
final class AllLabelsHiddenViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function __construct(
        private readonly PageActionSettings $pageActionSettings,
    ) {}

    public function render(): bool
    {
        return $this->pageActionSettings->areAllLabelsHidden();
    }
}
