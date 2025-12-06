<?php
/**
 * ToggleSwitchRenderer.php
 *
 * This file is part of the Codex PHP library, which provides a PHP-based interface for creating
 * UI components consistent with the Codex design system.
 *
 * The `ToggleSwitchRenderer` class leverages the `TemplateParser` and `Sanitizer` utilities to ensure the
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
use Wikimedia\Codex\Component\ToggleSwitch;
use Wikimedia\Codex\Contract\Renderer\IRenderer;
use Wikimedia\Codex\Parser\TemplateParser;
use Wikimedia\Codex\Traits\AttributeResolver;
use Wikimedia\Codex\Utility\Sanitizer;

/**
 * ToggleSwitchRenderer is responsible for rendering the HTML markup
 * for a ToggleSwitch component using a Mustache template.
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
class ToggleSwitchRenderer implements IRenderer {

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
	 * Constructor to initialize the ToggleSwitchRenderer with a sanitizer and a template parser.
	 *
	 * @since 0.1.0
	 * @param Sanitizer $sanitizer The sanitizer instance used for content sanitization.
	 * @param TemplateParser $templateParser The template parser instance.
	 */
	public function __construct( Sanitizer $sanitizer, TemplateParser $templateParser ) {
		$this->sanitizer = $sanitizer;
		$this->templateParser = $templateParser;
	}

	/**
	 * Renders the HTML for a ToggleSwitch component.
	 *
	 * @since 0.1.0
	 * @param ToggleSwitch $component The ToggleSwitch component to render.
	 * @return string The rendered HTML string for the component.
	 */
	public function render( $component ): string {
		if ( !$component instanceof ToggleSwitch ) {
			throw new InvalidArgumentException( "Expected instance of ToggleSwitch, got " . get_class( $component ) );
		}

		$label = $component->getLabel();

		$labelData = [
			'id' => $this->sanitizer->sanitizeText( $label->getId() ),
			'coreClass' => 'cdx-toggle-switch__label',
			'labelText' => $label->getLabelText(),
			'optionalFlag' => $label->isOptional(),
			'isVisuallyHidden' => $label->isVisuallyHidden(),
			'inputId' => $component->getInputId(),
			'description' => $this->sanitizer->sanitizeText( $label->getDescription() ),
			'descriptionId' => $this->sanitizer->sanitizeText( $label->getDescriptionId() ?? '' ),
			'isDisabled' => $label->isDisabled(),
			'iconClass' => $this->sanitizer->sanitizeText( $label->getIconClass() ?? '' ),
			'attributes' => $this->resolve(
				$this->sanitizer->sanitizeAttributes( $label->getAttributes() )
			),
		];

		$toggleData = [
			'id' => $this->sanitizer->sanitizeText( $component->getInputId() ),
			'name' => $this->sanitizer->sanitizeText( $component->getName() ),
			'value' => $this->sanitizer->sanitizeText( $component->getValue() ),
			'inputId' => $component->getInputId(),
			'isChecked' => $component->isChecked(),
			'isDisabled' => $component->isDisabled(),
			'ariaDescribedby' => $this->sanitizer->sanitizeText( $label->getDescriptionId() ?? '' ),
			'inputAttributes' => $this->resolve(
				$this->sanitizer->sanitizeAttributes( $component->getInputAttributes() )
			),
			'wrapperAttributes' => $this->resolve(
				$this->sanitizer->sanitizeAttributes( $component->getWrapperAttributes() )
			),
			'label' => $labelData,
		];

		return $this->templateParser->processTemplate( 'toggle-switch', $toggleData );
	}
}
