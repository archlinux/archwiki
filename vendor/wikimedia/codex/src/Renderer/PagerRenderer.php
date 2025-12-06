<?php
/**
 * PagerRenderer.php
 *
 * This file is part of the Codex PHP library, which provides a PHP-based interface for creating
 * UI components consistent with the Codex design system.
 *
 * The `PagerRenderer` class leverages the `TemplateParser` and `Sanitizer` utilities to ensure the
 * component object is rendered according to Codex design system standards.
 *
 * @category Renderer
 * @package  Codex\Renderer
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Renderer;

use InvalidArgumentException;
use UnexpectedValueException;
use Wikimedia\Codex\Builder\PagerBuilder;
use Wikimedia\Codex\Component\Pager;
use Wikimedia\Codex\Contract\ILocalizer;
use Wikimedia\Codex\Contract\Renderer\IRenderer;
use Wikimedia\Codex\ParamValidator\ParamDefinitions;
use Wikimedia\Codex\ParamValidator\ParamValidator;
use Wikimedia\Codex\ParamValidator\ParamValidatorCallbacks;
use Wikimedia\Codex\Parser\TemplateParser;
use Wikimedia\Codex\Traits\AttributeResolver;
use Wikimedia\Codex\Utility\Codex;
use Wikimedia\Codex\Utility\Sanitizer;

/**
 * PagerRenderer is responsible for rendering the HTML markup
 * for a Pager component using a Mustache template.
 *
 * This class uses the `TemplateParser` and `Sanitizer` utilities to manage
 * the template rendering process, ensuring that the component object's HTML
 * output adheres to the Codex design system's standards.
 *
 * @category Renderer
 * @package  Codex\Renderer
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class PagerRenderer implements IRenderer {

	/**
	 * Use the AttributeResolver trait
	 */
	use AttributeResolver;

	/**
	 * The sanitizer instance used for content sanitization.
	 */
	private Sanitizer $sanitizer;

	/**
	 * The template parser instance.
	 */
	private TemplateParser $templateParser;

	/**
	 * The localization instance implementing ILocalizer.
	 */
	private ILocalizer $localizer;

	/**
	 * The Codex instance for utility methods.
	 */
	private Codex $codex;

	/**
	 * The param validator.
	 */
	protected ParamValidator $paramValidator;

	/**
	 * The param validator callbacks.
	 */
	protected ParamValidatorCallbacks $paramValidatorCallbacks;

	/**
	 * Array of icon classes for the pager buttons.
	 */
	private array $iconClasses = [
		"first" => "cdx-table-pager__icon--first",
		"previous" => "cdx-table-pager__icon--previous",
		"next" => "cdx-table-pager__icon--next",
		"last" => "cdx-table-pager__icon--last",
	];

	/**
	 * Constructor to initialize the PagerRenderer with necessary dependencies.
	 *
	 * @since 0.1.0
	 * @param Sanitizer $sanitizer The sanitizer instance for cleaning user-provided data and attributes.
	 * @param TemplateParser $templateParser The template parser instance for rendering Mustache templates.
	 * @param ILocalizer $localizer The localizer instance for supporting translations and localization.
	 * @param ParamValidator $paramValidator The parameter validator instance for validating query parameters.
	 * @param ParamValidatorCallbacks $paramValidatorCallbacks The callbacks instance for accessing validated
	 *                                                         parameter values.
	 */
	public function __construct(
		Sanitizer $sanitizer,
		TemplateParser $templateParser,
		ILocalizer $localizer,
		ParamValidator $paramValidator,
		ParamValidatorCallbacks $paramValidatorCallbacks
	) {
		$this->sanitizer = $sanitizer;
		$this->templateParser = $templateParser;
		$this->localizer = $localizer;
		$this->codex = new Codex();
		$this->paramValidator = $paramValidator;
		$this->paramValidatorCallbacks = $paramValidatorCallbacks;
	}

	/**
	 * Renders the HTML for a pager component.
	 *
	 * Uses the provided Pager to generate HTML markup adhering to the Codex design system.
	 *
	 * @since 0.1.0
	 * @param Pager $component The Pager object to render.
	 * @return string The rendered HTML string for the component.
	 */
	public function render( $component ): string {
		if ( !$component instanceof Pager ) {
			throw new InvalidArgumentException( "Expected instance of Pager, got " . get_class( $component ) );
		}

		$selectHtml = $this->buildSelect( $component );

		$buttons = [
			'firstButton' => $this->buildButtonData( $component, PagerBuilder::ACTION_FIRST ),
			'prevButton' => $this->buildButtonData( $component, PagerBuilder::ACTION_PREVIOUS ),
			'nextButton' => $this->buildButtonData( $component, PagerBuilder::ACTION_NEXT ),
			'lastButton' => $this->buildButtonData( $component, PagerBuilder::ACTION_LAST ),
		];

		$hiddenFields = $this->buildHiddenFields();

		$pagerData = [
			'id' => $this->sanitizer->sanitizeText( $component->getId() ),
			'position' => $this->sanitizer->sanitizeText( $component->getPosition() ),
			'startOrdinal' => $component->getStartOrdinal(),
			'endOrdinal' => $component->getEndOrdinal(),
			'totalResults' => $component->getTotalResults(),
			'isPending' => $component->getEndOrdinal() < $component->getStartOrdinal(),
			'hasTotalResults' => $component->getTotalResults() > 0,
			'select' => $selectHtml,
			'firstButton' => $buttons['firstButton'],
			'prevButton' => $buttons['prevButton'],
			'nextButton' => $buttons['nextButton'],
			'lastButton' => $buttons['lastButton'],
			'hiddenFields' => $hiddenFields,
		];

		return $this->templateParser->processTemplate( 'pager', $pagerData );
	}

	/**
	 * Build the select dropdown data to be passed to the Mustache template.
	 *
	 * @since 0.1.0
	 * @param Pager $pager
	 * @return string The select dropdown data for Mustache.
	 */
	protected function buildSelect( Pager $pager ): string {
		$sizeOptions = $pager->getPaginationSizeOptions();
		$currentLimit = $pager->getLimit();

		$options = [];

		foreach ( $sizeOptions as $size ) {
			$options[] = [
				'value' => $this->sanitizer->sanitizeText( (string)$size ),
				'text' => $this->localizer->msg(
					'cdx-table-pager-items-per-page-current', $size
				),
				'selected' => ( $size == $currentLimit ),
			];
		}

		return $this->codex->select()
			->setOptions( $options )
			->setSelectedOption( (string)$currentLimit )
			->setAttributes( [
				'name' => 'limit',
				'onchange' => 'this.form.submit();',
				'class' => 'cdx-select',
			] )->build()->getHtml();
	}

	/**
	 * Build an individual pagination button.
	 *
	 * Generates the data array for a single pagination button based on the action.
	 *
	 * @since 0.1.0
	 * @param Pager $pager The Pager object.
	 * @param string $action The action for the button (e.g., PagerBuilder::ACTION_FIRST).
	 * @return array The data array for the pagination button.
	 */
	protected function buildButtonData( Pager $pager, string $action ): array {
		$iconClass = $this->iconClasses[$action] ?? '';
		$dir = '';
		switch ( $action ) {
			case PagerBuilder::ACTION_FIRST:
				$disabled = $pager->isFirstDisabled();
				$ariaLabelKey = 'cdx-table-pager-button-first-page';
				$offset = $pager->getFirstOffset();
				break;
			case PagerBuilder::ACTION_PREVIOUS:
				$disabled = $pager->isPrevDisabled();
				$ariaLabelKey = 'cdx-table-pager-button-prev-page';
				$offset = $pager->getPrevOffset();
				break;
			case PagerBuilder::ACTION_NEXT:
				$disabled = $pager->isNextDisabled();
				$ariaLabelKey = 'cdx-table-pager-button-next-page';
				$offset = $pager->getNextOffset();
				break;
			case PagerBuilder::ACTION_LAST:
				$disabled = $pager->isLastDisabled();
				$ariaLabelKey = 'cdx-table-pager-button-last-page';
				$offset = $pager->getLastOffset();
				$dir = 'prev';
				break;
			default:
				throw new InvalidArgumentException( "Unknown action: $action" );
		}

		return [
			'isDisabled' => $disabled,
			'weight' => 'quiet',
			'iconOnly' => true,
			'ariaLabelKey' => $ariaLabelKey,
			'iconClass' => $iconClass,
			'type' => 'submit',
			'name' => 'offset',
			'value' => $this->sanitizer->sanitizeText( (string)$offset ),
			'dir' => $dir,
		];
	}

	/**
	 * Build hidden fields for the pagination form.
	 *
	 * This method generates the hidden input fields needed for the pagination form, including offset, direction,
	 * and other query parameters.
	 *
	 * @since 0.1.0
	 * @return array The generated HTML string for the hidden fields.
	 */
	protected function buildHiddenFields(): array {
		$definitions = ParamDefinitions::getDefinitionsForContext( 'table' );

		foreach ( $definitions as $param => $rules ) {
			try {
				$this->paramValidator->validateValue(
					$param,
					$this->paramValidatorCallbacks->getValue(
						$param,
						$rules[ParamValidator::PARAM_DEFAULT],
						[]
					),
					$rules
				);
			} catch ( UnexpectedValueException $e ) {
				throw new InvalidArgumentException( "Invalid value for parameter '$param': " . $e->getMessage() );
			}
		}

		$fields = [];
		$keys = [ 'sort', 'desc', 'asc' ];
		foreach ( $keys as $key ) {
			$value = $this->paramValidatorCallbacks->getValue( $key, '', [] );
			if ( $value !== '' ) {
				$fields[] = [
					'key' => $this->sanitizer->sanitizeText( $key ),
					'value' => $this->sanitizer->sanitizeText( $value ),
				];
			}
		}

		return $fields;
	}
}
