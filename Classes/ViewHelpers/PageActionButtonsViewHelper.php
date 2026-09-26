<?php

declare(strict_types=1);

namespace Netzbewegung\NbPageActions\ViewHelpers;

use Netzbewegung\NbPageActions\Service\PageActionService;
use Netzbewegung\NbPageActions\Service\PageActionSettings;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\LanguageColumn;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Renders the enable/disable and delete buttons of a language column in the
 * language comparison view of the page module. Used in the override of
 * EXT:backend's partial "PageLayout/LanguageColumns".
 */
final class PageActionButtonsViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function __construct(
        private readonly PageActionService $pageActionService,
        private readonly PageActionSettings $pageActionSettings,
        private readonly IconFactory $iconFactory,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument(
            'languageColumn',
            LanguageColumn::class,
            'Language column of the page module language comparison view',
            true
        );
    }

    public function render(): string
    {
        $languageColumn = $this->arguments['languageColumn'];
        if (!$languageColumn instanceof LanguageColumn) {
            return '';
        }

        $showLabel = !$this->pageActionSettings->areLabelsHidden();
        $buttons = [];
        foreach ($this->pageActionService->getActions(
            $languageColumn->getPageRecord(),
            $languageColumn->getContext()->getReturnUrl()
        ) as $action) {
            $buttons[] = $this->renderButton($action, $showLabel);
        }

        return implode("\n", $buttons);
    }

    /**
     * @param array{type: string, label: string, icon: string, url: string, attributes: array<string, string>} $action
     */
    private function renderButton(array $action, bool $showLabel): string
    {
        $attributes = '';
        foreach ($action['attributes'] as $name => $value) {
            $attributes .= sprintf(' %s="%s"', $name, htmlspecialchars($value, ENT_QUOTES));
        }

        // Actions with modal attributes (delete) are confirmed by the core modal, like for content elements
        $classes = 'btn btn-default btn-sm nb-page-action';
        if (isset($action['attributes']['data-severity'])) {
            $classes .= ' t3js-modal-trigger';
        }

        $label = htmlspecialchars($action['label'], ENT_QUOTES);

        // Without label text the button is icon only, the label stays available as title and aria-label
        $labelAttributes = $showLabel ? '' : sprintf(' aria-label="%s"', $label);
        $labelContent = $showLabel
            ? sprintf('<span class="nb-page-action__label">%s</span>', $label)
            : '';

        return sprintf(
            '<a class="%s" data-page-action="%s" href="%s" title="%s"%s%s>%s%s</a>',
            $classes,
            htmlspecialchars($action['type'], ENT_QUOTES),
            htmlspecialchars($action['url'], ENT_QUOTES),
            $label,
            $labelAttributes,
            $attributes,
            $this->iconFactory->getIcon($action['icon'], IconSize::SMALL)->render(),
            $labelContent
        );
    }
}
