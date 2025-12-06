<?php
/**
 * Thumbnail.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Thumbnail` class, responsible for managing
 * the behavior and properties of the corresponding component.
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Component;

use Wikimedia\Codex\Renderer\ThumbnailRenderer;

/**
 * Thumbnail
 *
 * This class is part of the Codex PHP library and is responsible for
 * representing an immutable object. It is primarily intended for use
 * with a builder class to construct its instances.
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class Thumbnail {

	/**
	 * The ID for the thumbnail.
	 */
	private string $id;

	/**
	 * The background image URL for the thumbnail.
	 */
	private string $backgroundImage;

	/**
	 * The CSS class for the custom placeholder icon.
	 */
	private string $placeholderClass;

	/**
	 * Additional HTML attributes for the thumbnail.
	 */
	private array $attributes;

	/**
	 * The renderer instance used to render the thumbnail.
	 */
	private ThumbnailRenderer $renderer;

	/**
	 * Constructor for the Thumbnail component.
	 *
	 * @param string $id The ID for the thumbnail.
	 * @param string $backgroundImage The background image URL.
	 * @param string $placeholderClass The CSS class for the placeholder icon.
	 * @param array $attributes Additional HTML attributes for the thumbnail.
	 * @param ThumbnailRenderer $renderer The renderer to use for rendering the thumbnail.
	 */
	public function __construct(
		string $id,
		string $backgroundImage,
		string $placeholderClass,
		array $attributes,
		ThumbnailRenderer $renderer
	) {
		$this->id = $id;
		$this->backgroundImage = $backgroundImage;
		$this->placeholderClass = $placeholderClass;
		$this->attributes = $attributes;
		$this->renderer = $renderer;
	}

	/**
	 * Get the HTML ID for the thumbnail.
	 *
	 * This method returns the HTML `id` attribute value for the thumbnail element.
	 *
	 * @since 0.1.0
	 * @return string The ID for the thumbnail.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the background image URL for the thumbnail.
	 *
	 * This method returns the URL of the background image that will be displayed within the thumbnail.
	 * The image serves as a visual preview of the content.
	 *
	 * @since 0.1.0
	 * @return string The URL of the background image.
	 */
	public function getBackgroundImage(): string {
		return $this->backgroundImage;
	}

	/**
	 * Get the CSS class for the custom placeholder icon.
	 *
	 * This method returns the CSS class for the custom placeholder icon that will be displayed if
	 * the background image is not provided.
	 * The placeholder gives users a visual indication of where an image will appear.
	 *
	 * @since 0.1.0
	 * @return string The CSS class for the placeholder icon.
	 */
	public function getPlaceholderClass(): string {
		return $this->placeholderClass;
	}

	/**
	 * Retrieve additional HTML attributes for the thumbnail element.
	 *
	 * This method returns an associative array of custom HTML attributes that will be applied to the outer
	 * `<span>` element of the thumbnail. These attributes can be used to improve accessibility, enhance styling,
	 * or integrate with JavaScript functionality.
	 *
	 * @since 0.1.0
	 * @return array The additional attributes as an array.
	 */
	public function getAttributes(): array {
		return $this->attributes;
	}

	/**
	 * Get the component's HTML representation.
	 *
	 * This method generates the HTML markup for the component, incorporating relevant properties
	 * and any additional attributes. The component is structured using appropriate HTML elements
	 * as defined by the implementation.
	 *
	 * @since 0.1.0
	 * @return string The generated HTML string for the component.
	 */
	public function getHtml(): string {
		return $this->renderer->render( $this );
	}
}
