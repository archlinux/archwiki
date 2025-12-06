<?php
/**
 * AccordionBuilder.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `Accordion` class, a builder for constructing
 * accordion components using the Codex design system.
 *
 * An Accordion is an expandable and collapsible section of content, often featured in a
 * vertically stacked list with other Accordions. Accordions are commonly used to organize
 * content into collapsed sections, making the interface easier to navigate.
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
use Wikimedia\Codex\Component\Accordion;
use Wikimedia\Codex\Component\HtmlSnippet;
use Wikimedia\Codex\Renderer\AccordionRenderer;

/**
 * AccordionBuilder
 *
 * This class implements the builder pattern to construct instances of Accordion.
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
class AccordionBuilder {

	/**
	 * The ID for the accordion.
	 */
	protected string $id = '';

	/**
	 * The accordion's header title.
	 */
	protected string $title = '';

	/**
	 * Additional text under the title.
	 */
	protected string $description = '';

	/**
	 * The content shown when the accordion is expanded.
	 */
	protected string $content = '';

	/**
	 * Determines if the accordion is expanded by default.
	 */
	protected bool $isOpen = false;

	/**
	 * Additional HTML attributes for the <details> element.
	 */
	protected array $attributes = [];

	/**
	 * The renderer instance used to render the accordion.
	 */
	protected AccordionRenderer $renderer;

	/**
	 * Constructor for the AccordionBuilder class.
	 *
	 * @param AccordionRenderer $renderer The renderer to use for rendering the accordion.
	 */
	public function __construct( AccordionRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Set the accordion's HTML ID attribute.
	 *
	 * @since 0.1.0
	 * @param string $id The ID for the accordion element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set the title for the accordion header.
	 *
	 * This method specifies the title text that appears in the accordion's header section.
	 * The title serves as the main clickable element that users interact with to expand or collapse
	 * the accordion content. The title is rendered inside a `<span>` element with the class
	 * `cdx-accordion__header__title`, which is nested within an `<h3>` header inside the `<summary>` element.
	 *
	 * The title should be concise yet descriptive enough to give users a clear understanding
	 * of the content they will see when the accordion is expanded.
	 *
	 * @since 0.1.0
	 * @param string $title The title text to be displayed in the accordion header.
	 * @return $this Returns the Accordion instance for method chaining.
	 */
	public function setTitle( string $title ): self {
		if ( trim( $title ) === '' ) {
			throw new InvalidArgumentException( 'Title cannot be empty.' );
		}
		$this->title = $title;

		return $this;
	}

	/**
	 * Set the description for the accordion header.
	 *
	 * The description is an optional text that provides additional context or details about the accordion's content.
	 * This text is displayed beneath the title in the header section and is wrapped in a `<span>` element with
	 * the class `cdx-accordion__header__description`. This description is particularly useful when the title alone
	 * does not fully convey the nature of the accordion's content.
	 *
	 * This method is especially helpful for making the accordion more accessible and informative,
	 * allowing users to understand the content before deciding to expand it.
	 *
	 * @since 0.1.0
	 * @param string $description The description text to be displayed in the accordion header.
	 * @return $this Returns the Accordion instance for method chaining.
	 */
	public function setDescription( string $description ): self {
		$this->description = $description;

		return $this;
	}

	/**
	 * Set the content for the accordion body as plain text.
	 *
	 * This method defines plain text content that will be safely escaped before rendering.
	 * It should be used when the content does not contain any HTML markup and needs to be treated strictly as text.
	 *
	 * @since 0.1.0
	 * @param string $content The plain text content to be displayed inside the accordion.
	 * @param-taint $content escapes_html
	 * @return $this Returns the Accordion instance for method chaining.
	 */
	public function setContentText( string $content ): self {
		$this->content = $content;

		return $this;
	}

	/**
	 * Set the content for the accordion body as HTML.
	 *
	 * This method defines HTML content by passing an `HtmlSnippet` object. The content
	 * will be rendered as-is without escaping, ensuring the correct HTML output.
	 *
	 * @since 0.1.0
	 * @param HtmlSnippet $content The HTML content to be displayed inside the accordion.
	 * @param-taint $content exec_html
	 * @return $this Returns the Accordion instance for method chaining.
	 */
	public function setContentHtml( HtmlSnippet $content ): self {
		$this->content = (string)$content;

		return $this;
	}

	/**
	 * Set whether the accordion should be open by default.
	 *
	 * By default, accordions are rendered in a collapsed state. However, setting this property to `true`
	 * will cause the accordion to be expanded when the page initially loads. This adds the `open` attribute
	 * to the `<details>` element, making the content visible without interaction.
	 *
	 * This feature is useful in scenarios where critical content needs to be immediately visible, without requiring
	 * any action to expand the accordion.
	 *
	 * @since 0.1.0
	 * @param bool $isOpen Indicates whether the accordion should be open by default.
	 * @return $this Returns the Accordion instance for method chaining.
	 */
	public function setOpen( bool $isOpen ): self {
		$this->isOpen = $isOpen;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the `<details>` element.
	 *
	 * This method allows custom attributes to be added to the `<details>` element, such as `id`, `class`, `data-*`,
	 * `role`, or any other valid HTML attributes. These attributes can be used to further customize the accordion
	 * behavior, integrate it with JavaScript, or enhance accessibility.
	 *
	 * The values of these attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * Example usage:
	 *
	 *     $accordion->setAttributes([
	 *         'id' => 'some-id',
	 *         'data-toggle' => 'collapse'
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this Returns the Accordion instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}
		return $this;
	}

	/**
	 * Build and return the Accordion component object.
	 * This method constructs the immutable Accordion object with all the properties set via the builder.
	 *
	 * @since 0.1.0
	 * @return Accordion The constructed Accordion.
	 */
	public function build(): Accordion {
		return new Accordion(
			$this->id,
			$this->title,
			$this->description,
			$this->content,
			$this->isOpen,
			$this->attributes,
			$this->renderer
		);
	}
}
