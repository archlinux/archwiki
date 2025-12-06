<?php
/**
 * CardBuilder.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `Card` class, a builder for constructing
 * card components using the Codex design system.
 *
 * A Card is used to group information and actions related to a single topic.
 * Cards can be clickable and offer a way to navigate to the content they represent (e.g., Wikipedia articles).
 *
 * @category Builder
 * @package  Codex\Builder
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Builder;

use InvalidArgumentException;
use Wikimedia\Codex\Component\Card;
use Wikimedia\Codex\Component\Thumbnail;
use Wikimedia\Codex\Renderer\CardRenderer;

/**
 * CardBuilder
 *
 * This class implements the builder pattern to construct instances of Card.
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
class CardBuilder {

	/**
	 * The ID for the card.
	 */
	protected string $id = '';

	/**
	 * The title text displayed on the card.
	 */
	protected string $title = '';

	/**
	 * The description text displayed on the card.
	 */
	protected string $description = '';

	/**
	 * Optional supporting text for additional details on the card.
	 */
	protected string $supportingText = '';

	/**
	 * The URL the card links to, if the card is clickable.
	 */
	protected string $url = '';

	/**
	 * The CSS class for an optional icon in the card.
	 */
	protected ?string $iconClass = null;

	/**
	 * The Thumbnail object representing the card's thumbnail.
	 */
	protected ?Thumbnail $thumbnail = null;

	/**
	 * Additional HTML attributes for the card element.
	 */
	protected array $attributes = [];

	/**
	 * The renderer instance used to render the card.
	 */
	protected CardRenderer $renderer;

	/**
	 * Constructor for the CardBuilder class.
	 *
	 * @param CardRenderer $renderer The renderer to use for rendering the card.
	 */
	public function __construct( CardRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Set the card's HTML ID attribute.
	 *
	 * @since 0.1.0
	 * @param string $id The ID for the card element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set the title for the card.
	 *
	 * The title is the primary text displayed on the card, typically representing the main topic
	 * or subject of the card. It is usually rendered in a larger font and is the most prominent
	 * piece of text on the card.
	 *
	 * @since 0.1.0
	 * @param string $title The title text displayed on the card.
	 * @return $this Returns the Card instance for method chaining.
	 */
	public function setTitle( string $title ): self {
		if ( trim( $title ) === '' ) {
			throw new InvalidArgumentException( 'Card title cannot be empty.' );
		}
		$this->title = $title;

		return $this;
	}

	/**
	 * Set the description for the card.
	 *
	 * The description provides additional details about the card's content. It is typically rendered
	 * below the title in a smaller font. The description is optional and can be used to give users
	 * more context about what the card represents.
	 *
	 * @since 0.1.0
	 * @param string $description The description text displayed on the card.
	 * @return $this Returns the Card instance for method chaining.
	 */
	public function setDescription( string $description ): self {
		$this->description = $description;

		return $this;
	}

	/**
	 * Set the supporting text for the card.
	 *
	 * The supporting text is an optional piece of text that can provide additional information
	 * or context about the card. It is typically placed at the bottom of the card, below the
	 * title and description, in a smaller font. This text can be used for subtitles, additional
	 * notes, or other relevant details.
	 *
	 * @since 0.1.0
	 * @param string $supportingText The supporting text displayed on the card.
	 * @return $this Returns the Card instance for method chaining.
	 */
	public function setSupportingText( string $supportingText ): self {
		$this->supportingText = $supportingText;

		return $this;
	}

	/**
	 * Set the URL for the card. If provided, the card will be an `<a>` element.
	 *
	 * This method makes the entire card clickable by wrapping it in an anchor (`<a>`) element,
	 * turning it into a link. This is particularly useful for cards that serve as navigational
	 * elements, leading users to related content, such as articles, profiles, or external pages.
	 *
	 * @since 0.1.0
	 * @param string $url The URL the card should link to.
	 * @return $this Returns the Card instance for method chaining.
	 */
	public function setUrl( string $url ): self {
		if ( !filter_var( $url, FILTER_VALIDATE_URL ) ) {
			throw new InvalidArgumentException( "Invalid URL: $url" );
		}
		$this->url = $url;

		return $this;
	}

	/**
	 * Set the icon class for the card.
	 *
	 * This method specifies a CSS class for an icon to be displayed inside the card.
	 * The icon can be used to visually represent the content or purpose of the card.
	 * It is typically rendered at the top or side of the card, depending on the design.
	 *
	 * @since 0.1.0
	 * @param string $iconClass The CSS class for the icon.
	 * @return $this Returns the Card instance for method chaining.
	 */
	public function setIconClass( string $iconClass ): self {
		$this->iconClass = $iconClass;

		return $this;
	}

	/**
	 * Set the thumbnail for the card.
	 *
	 * This method accepts a `Thumbnail` object, which configures the thumbnail associated with the card.
	 *
	 * Example usage:
	 *     $thumbnail = Thumbnail::setBackgroundImage('https://example.com/image.jpg');
	 *     $card->setThumbnail($thumbnail);
	 *
	 * @since 0.1.0
	 * @param Thumbnail $thumbnail The Thumbnail object.
	 * @return $this Returns the Card instance for method chaining.
	 */
	public function setThumbnail( Thumbnail $thumbnail ): self {
		$this->thumbnail = $thumbnail;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the card element.
	 *
	 * This method allows custom HTML attributes to be added to the card element, such as `id`, `data-*`, `aria-*`,
	 * or any other valid attributes. These attributes can be used to integrate the card with JavaScript, enhance
	 * accessibility, or provide additional metadata.
	 *
	 * The values of these attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this Returns the Card instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}
		return $this;
	}

	/**
	 * Build and return the Card component object.
	 * This method constructs the immutable Card object with all the properties set via the builder.
	 *
	 * @since 0.1.0
	 * @return Card The constructed Card.
	 */
	public function build(): Card {
		return new Card(
			$this->id,
			$this->title,
			$this->description,
			$this->supportingText,
			$this->url,
			$this->iconClass,
			$this->thumbnail,
			$this->attributes,
			$this->renderer
		);
	}
}
