<?php
/**
 * SelectRenderer.php
 *
 * This file is part of the Codex PHP library, which provides a PHP-based interface for creating
 * UI components consistent with the Codex design system.
 *
 * The `SelectRenderer` class leverages the `TemplateParser` and `Sanitizer` utilities to ensure the
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
use Wikimedia\Codex\Component\Option;
use Wikimedia\Codex\Component\Select;
use Wikimedia\Codex\Contract\Renderer\IRenderer;
use Wikimedia\Codex\Parser\TemplateParser;
use Wikimedia\Codex\Traits\AttributeResolver;
use Wikimedia\Codex\Utility\Sanitizer;

/**
 * SelectRenderer is responsible for rendering the HTML markup
 * for a Select component using a Mustache template.
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
class SelectRenderer implements IRenderer {

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
	 * Constructor to initialize the SelectRenderer with a sanitizer and a template parser.
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
	 * Renders the HTML for a select dropdown component.
	 *
	 * Uses the provided Select component to generate HTML markup adhering to the Codex design system.
	 *
	 * @since 0.1.0
	 * @param Select $component The Select object to render.
	 * @return string The rendered HTML string for the component.
	 */
	public function render( $component ): string {
		if ( !$component instanceof Select ) {
			throw new InvalidArgumentException( "Expected instance of Select, got " . get_class( $component ) );
		}

		$selectData = [
			'id' => $this->sanitizer->sanitizeText( $component->getId() ),
			'isDisabled' => $component->isDisabled(),
			'selectedOption' => $component->getSelectedOption(),
			'attributes' => $this->resolve( $this->sanitizer->sanitizeAttributes( $component->getAttributes() ) ),
			'options' => $this->prepareOptions( $component ),
			'optGroups' => $this->prepareOptGroups( $component ),
		];

		return $this->templateParser->processTemplate( 'select', $selectData );
	}

	/**
	 * Prepare options for rendering.
	 *
	 * @since 0.1.0
	 * @param Select $object The Select component object.
	 * @return array An array of sanitized option data for rendering.
	 */
	private function prepareOptions( Select $object ): array {
		$options = [];
		foreach ( $object->getOptions() as $option ) {
			if ( !$option instanceof Option ) {
				throw new InvalidArgumentException( "Expected instance of Option in options" );
			}
			$options[] = [
				'value' => $this->sanitizer->sanitizeText( $option->getValue() ),
				'text' => $this->sanitizer->sanitizeText( $option->getText() ),
				'isSelected' => $option->isSelected(),
			];
		}

		return $options;
	}

	/**
	 * Prepare optGroups for rendering.
	 *
	 * @since 0.1.0
	 * @param Select $object The Select component object containing optGroups.
	 * @return array Prepared array of optGroups with their respective options for rendering.
	 */
	private function prepareOptGroups( Select $object ): array {
		$optGroups = [];
		foreach ( $object->getOptGroups() as $label => $groupOptions ) {
			$group = [
				'label' => $this->sanitizer->sanitizeText( $label ),
				'options' => [],
			];
			foreach ( $groupOptions as $option ) {
				if ( !$option instanceof Option ) {
					throw new InvalidArgumentException( "Expected instance of Option in optGroups" );
				}
				$group['options'][] = [
					'value' => $this->sanitizer->sanitizeText( $option->getValue() ),
					'text' => $this->sanitizer->sanitizeText( $option->getText() ),
					'isSelected' => $option->isSelected(),
				];
			}
			$optGroups[] = $group;
		}

		return $optGroups;
	}
}
