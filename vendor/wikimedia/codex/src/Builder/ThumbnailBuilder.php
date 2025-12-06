<?php
/**
 * ThumbnailBuilder.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `Thumbnail` class, a builder for constructing
 * thumbnail components using the Codex design system.
 *
 * A Thumbnail is a visual element used to display a small preview of an image.
 * Thumbnails provide users with a quick glimpse of the associated content.
 *
 * @category Builder
 * @package  Codex\Builder
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Builder;

use Wikimedia\Codex\Component\Thumbnail;
use Wikimedia\Codex\Renderer\ThumbnailRenderer;

/**
 * ThumbnailBuilder
 *
 * This class implements the builder pattern to construct instances of Thumbnail.
 * It provides a fluent interface for setting various properties and building the
 * final immutable object with predefined configurations and immutability.
 *
 * @category Builder
 * @package  Codex\Builder
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class ThumbnailBuilder {

	/**
	 * The ID for the thumbnail.
	 */
	protected string $id = '';

	/**
	 * Background image URL.
	 */
	protected string $backgroundImage = '';

	/**
	 * CSS class for custom placeholder icon.
	 */
	protected string $placeholderClass = '';

	/**
	 * Additional HTML attributes for the thumbnail.
	 */
	protected array $attributes = [];

	/**
	 * The renderer instance used to render the thumbnail.
	 */
	protected ThumbnailRenderer $renderer;

	/**
	 * Constructor for the ThumbnailBuilder class.
	 *
	 * @param ThumbnailRenderer $renderer The renderer to use for rendering the thumbnail.
	 */
	public function __construct( ThumbnailRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Set the Thumbnail HTML ID attribute.
	 *
	 * @since 0.1.0
	 * @param string $id The ID for the Thumbnail element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set the background image for the thumbnail.
	 *
	 * This method specifies the URL of the background image that will be displayed
	 * within the thumbnail. The image serves as a visual preview of the content.
	 *
	 * Example usage:
	 *
	 *     $thumbnail->setBackgroundImage('https://example.com/image.jpg');
	 *
	 * @since 0.1.0
	 * @param string $backgroundImage The URL of the background image.
	 * @return $this Returns the Thumbnail instance for method chaining.
	 */
	public function setBackgroundImage( string $backgroundImage ): self {
		$this->backgroundImage = $backgroundImage;

		return $this;
	}

	/**
	 * Set the CSS class for a custom placeholder icon.
	 *
	 * This method specifies a custom CSS class for a placeholder icon that will be displayed
	 * if the background image is not provided. The placeholder gives users a visual indication of where
	 * an image will appear.
	 *
	 * Example usage:
	 *
	 *     $thumbnail->setPlaceholderClass('custom-placeholder-icon');
	 *
	 * @since 0.1.0
	 * @param string $placeholderClass The CSS class for the placeholder icon.
	 * @return $this Returns the Thumbnail instance for method chaining.
	 */
	public function setPlaceholderClass( string $placeholderClass ): self {
		$this->placeholderClass = $placeholderClass;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the thumbnail element.
	 *
	 * This method allows custom HTML attributes to be added to the outer `<span>` element of the thumbnail,
	 * such as `id`, `data-*`, `aria-*`, or any other valid attributes. These attributes can be used to
	 * enhance accessibility or integrate with JavaScript.
	 *
	 * Example usage:
	 *
	 *     $thumbnail->setAttributes([
	 *         'id' => 'thumbnail-id',
	 *         'data-category' => 'images',
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this Returns the Thumbnail instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}
		return $this;
	}

	/**
	 * Build and return the Thumbnail component object.
	 * This method constructs the immutable Thumbnail object with all the properties set via the builder.
	 *
	 * @since 0.1.0
	 * @return Thumbnail The constructed Thumbnail.
	 */
	public function build(): Thumbnail {
		return new Thumbnail(
			$this->id,
			$this->backgroundImage,
			$this->placeholderClass,
			$this->attributes,
			$this->renderer
		);
	}
}
