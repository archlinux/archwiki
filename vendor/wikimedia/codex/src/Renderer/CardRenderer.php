<?php
/**
 * CardRenderer.php
 *
 * This file is part of the Codex PHP library, which provides a PHP-based interface for creating
 * UI components consistent with the Codex design system.
 *
 * The `CardRenderer` class leverages the `TemplateParser` and `Sanitizer` utilities to ensure the
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
use Wikimedia\Codex\Component\Card;
use Wikimedia\Codex\Contract\Renderer\IRenderer;
use Wikimedia\Codex\Parser\TemplateParser;
use Wikimedia\Codex\Traits\AttributeResolver;
use Wikimedia\Codex\Utility\Sanitizer;

/**
 * CardRenderer is responsible for rendering the HTML markup
 * for a Card component using a Mustache template.
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
class CardRenderer implements IRenderer {

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
	 * Constructor to initialize the CardRenderer with a sanitizer and a template parser.
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
	 * Renders the HTML for a card component.
	 *
	 * Uses the provided Card component to generate HTML markup adhering to the Codex design system.
	 *
	 * @since 0.1.0
	 * @param Card $component The Card object to render.
	 * @return string The rendered HTML string for the component.
	 */
	public function render( $component ): string {
		if ( !$component instanceof Card ) {
			throw new InvalidArgumentException( "Expected instance of Card, got " . get_class( $component ) );
		}

		$thumbnail = $component->getThumbnail();
		$thumbnailData = null;
		if ( $thumbnail ) {
			$thumbnailData = [
				'id' => $this->sanitizer->sanitizeText( $thumbnail->getId() ),
				'coreClass' => 'cdx-card__thumbnail',
				'backgroundImage' => $this->sanitizer->sanitizeText( $thumbnail->getBackgroundImage() ),
				'useDefaultPlaceholder' => (bool)$component->getThumbnail(),
				'placeholderClass' => $this->sanitizer->sanitizeText( $thumbnail->getPlaceholderClass() ),
				'attributes' => $this->resolve( $this->sanitizer->sanitizeAttributes( $thumbnail->getAttributes() ) ),
			];
		}

		$cardData = [
			'id' => $this->sanitizer->sanitizeText( $component->getId() ),
			'tag' => $component->getUrl() !== '' ? 'a' : 'span',
			'title' => $this->sanitizer->sanitizeText( $component->getTitle() ),
			'description' => $this->sanitizer->sanitizeText( $component->getDescription() ),
			'supportingText' => $this->sanitizer->sanitizeText( $component->getSupportingText() ),
			'url' => $this->sanitizer->sanitizeUrl( $component->getUrl() ),
			'iconClass' => $this->sanitizer->sanitizeText( $component->getIconClass() ),
			'thumbnail' => $thumbnailData,
			'attributes' => $this->resolve( $this->sanitizer->sanitizeAttributes( $component->getAttributes() ) ),
		];

		return $this->templateParser->processTemplate( 'card', $cardData );
	}
}
