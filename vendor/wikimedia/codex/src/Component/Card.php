<?php
/**
 * Card.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Card` class, responsible for managing
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

use Wikimedia\Codex\Renderer\CardRenderer;

/**
 * Card
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
class Card {

	/**
	 * The ID for the card.
	 */
	protected string $id;

	/**
	 * The title text displayed on the card.
	 */
	protected string $title;

	/**
	 * The description text displayed on the card.
	 */
	protected string $description;

	/**
	 * Optional supporting text for additional details on the card.
	 */
	protected string $supportingText;

	/**
	 * The URL the card links to, if the card is clickable.
	 */
	protected string $url;

	/**
	 * The CSS class for an optional icon in the card.
	 */
	protected ?string $iconClass;

	/**
	 * The Thumbnail object representing the card's thumbnail.
	 */
	protected ?Thumbnail $thumbnail;

	/**
	 * Additional HTML attributes for the card element.
	 */
	protected array $attributes;

	/**
	 * The renderer instance used to render the card.
	 */
	protected CardRenderer $renderer;

	/**
	 * Constructor for the Card component.
	 *
	 * Initializes a Card instance with the specified properties.
	 *
	 * @param string $id The ID for the card.
	 * @param string $title The title text displayed on the card.
	 * @param string $description The description text displayed on the card.
	 * @param string $supportingText The supporting text displayed on the card.
	 * @param string $url The URL the card links to, if clickable.
	 * @param string|null $iconClass The CSS class for an optional icon in the card.
	 * @param Thumbnail|null $thumbnail The Thumbnail object representing the card's thumbnail.
	 * @param array $attributes Additional HTML attributes for the card element.
	 * @param CardRenderer $renderer The renderer to use for rendering the card.
	 */
	public function __construct(
		string $id,
		string $title,
		string $description,
		string $supportingText,
		string $url,
		?string $iconClass,
		?Thumbnail $thumbnail,
		array $attributes,
		CardRenderer $renderer
	) {
		$this->id = $id;
		$this->title = $title;
		$this->description = $description;
		$this->supportingText = $supportingText;
		$this->url = $url;
		$this->iconClass = $iconClass;
		$this->thumbnail = $thumbnail;
		$this->attributes = $attributes;
		$this->renderer = $renderer;
	}

	/**
	 * Get the card's HTML ID attribute.
	 *
	 * This method returns the ID that is assigned to the card element. The ID is useful for targeting
	 * the card with JavaScript, CSS, or for accessibility purposes.
	 *
	 * @since 0.1.0
	 * @return string The ID of the card element.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the title text displayed on the card.
	 *
	 * This method returns the title text that is prominently displayed on the card.
	 * The title usually represents the main topic or subject of the card.
	 *
	 * @since 0.1.0
	 * @return string The title of the card.
	 */
	public function getTitle(): string {
		return $this->title;
	}

	/**
	 * Get the description text displayed on the card.
	 *
	 * This method returns the description text that provides additional details about
	 * the card's content. The description is typically rendered below the title.
	 *
	 * @since 0.1.0
	 * @return string The description of the card.
	 */
	public function getDescription(): string {
		return $this->description;
	}

	/**
	 * Get the supporting text displayed on the card.
	 *
	 * This method returns the supporting text that provides further context or details
	 * about the card's content. The supporting text is typically rendered at the bottom
	 * of the card, below the title and description.
	 *
	 * @since 0.1.0
	 * @return string The supporting text of the card.
	 */
	public function getSupportingText(): string {
		return $this->supportingText;
	}

	/**
	 * Get the URL the card links to.
	 *
	 * This method returns the URL that the card links to if the card is clickable.
	 * If a URL is provided, the card is rendered as an anchor (`<a>`) element.
	 *
	 * @since 0.1.0
	 * @return string The URL the card links to.
	 */
	public function getUrl(): string {
		return $this->url;
	}

	/**
	 * Get the icon class for the card.
	 *
	 * This method returns the CSS class used for the icon displayed inside the card.
	 * The icon is an optional visual element that can enhance the card's content.
	 *
	 * @since 0.1.0
	 * @return string|null The CSS class for the icon, or null if no icon is set.
	 */
	public function getIconClass(): ?string {
		return $this->iconClass;
	}

	/**
	 * Get the thumbnail object associated with the card.
	 *
	 * This method returns the Thumbnail object representing the card's thumbnail.
	 *
	 * @since 0.1.0
	 * @return Thumbnail|null The Thumbnail object or null if no thumbnail is set.
	 */
	public function getThumbnail(): ?Thumbnail {
		return $this->thumbnail;
	}

	/**
	 * Retrieve additional HTML attributes for the card element.
	 *
	 * This method returns an associative array of additional HTML attributes that will be applied
	 * to the card element. These attributes can be used to enhance the appearance, accessibility,
	 * or functionality of the card.
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
