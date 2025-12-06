<?php
/**
 * TextAreaRenderer.php
 *
 * This file is part of the Codex PHP library, which provides a PHP-based interface for creating
 * UI components consistent with the Codex design system.
 *
 * The `TextAreaRenderer` class leverages the `TemplateParser` and `Sanitizer` utilities to ensure the
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
use Wikimedia\Codex\Component\TextArea;
use Wikimedia\Codex\Contract\Renderer\IRenderer;
use Wikimedia\Codex\Parser\TemplateParser;
use Wikimedia\Codex\Traits\AttributeResolver;
use Wikimedia\Codex\Utility\Sanitizer;

/**
 * TextAreaRenderer is responsible for rendering the HTML markup
 * for a TextArea component using a Mustache template.
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
class TextAreaRenderer implements IRenderer {

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
	 * Constructor to initialize the TextAreaRenderer with a sanitizer and a template parser.
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
	 * Renders the HTML for a textarea component.
	 *
	 * Uses the provided TextArea component to generate HTML markup adhering to the Codex design system.
	 *
	 * @since 0.1.0
	 * @param TextArea $component The TextArea component to render.
	 * @return string The rendered HTML string for the component.
	 */
	public function render( $component ): string {
		if ( !$component instanceof TextArea ) {
			throw new InvalidArgumentException( "Expected instance of TextArea, got " . get_class( $component ) );
		}

		$textareaData = [
			'id' => $this->sanitizer->sanitizeText( $component->getId() ),
			'name' => $this->sanitizer->sanitizeText( $component->getName() ),
			'placeholder' => $this->sanitizer->sanitizeText( $component->getPlaceholder() ),
			'value' => $this->sanitizer->sanitizeText( $component->getValue() ),
			'isDisabled' => $component->isDisabled(),
			'isReadonly' => $component->isReadonly(),
			'hasStartIcon' => $component->hasStartIcon(),
			'hasEndIcon' => $component->hasEndIcon(),
			'startIconClass' => $this->sanitizer->sanitizeText( $component->getStartIconClass() ),
			'endIconClass' => $this->sanitizer->sanitizeText( $component->getEndIconClass() ),
			'status' => $component->getStatus(),
			'textAreaAttributes' => $this->resolve(
				$this->sanitizer->sanitizeAttributes( $component->getTextareaAttributes() )
			),
			'wrapperAttributes' => $this->resolve(
				$this->sanitizer->sanitizeAttributes( $component->getWrapperAttributes() )
			),
		];

		return $this->templateParser->processTemplate( 'text-area', $textareaData );
	}
}
