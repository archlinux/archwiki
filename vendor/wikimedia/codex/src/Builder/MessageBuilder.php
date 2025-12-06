<?php
/**
 * MessageBuilder.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `Message` class, a builder for constructing
 * message components using the Codex design system.
 *
 * A Message provides system feedback for users. Messages can be provided as a prominently-displayed
 * banner with a longer explanation, or as inline validation feedback.
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
use Wikimedia\Codex\Component\HtmlSnippet;
use Wikimedia\Codex\Component\Message;
use Wikimedia\Codex\Renderer\MessageRenderer;

/**
 * MessageBuilder
 *
 * This class implements the builder pattern to construct instances of Message.
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
class MessageBuilder {

	/**
	 * The valid status types for messages.
	 */
	private const STATUS_TYPES = [
		'notice',
		'warning',
		'error',
		'success',
	];

	/**
	 * The ID for the Message.
	 */
	protected string $id = '';

	/**
	 * The content displayed inside the message box.
	 */
	protected string $content = '';

	/**
	 * The type of the message (e.g., 'notice', 'warning', 'error', 'success').
	 */
	protected string $type = 'notice';

	/**
	 * Whether the message box should be displayed inline.
	 */
	protected bool $inline = false;

	/**
	 * The heading displayed at the top of the message content.
	 */
	protected string $heading = '';

	/**
	 * The CSS class name for the icon.
	 */
	protected string $iconClass = '';

	/**
	 * Additional HTML attributes for the message box.
	 */
	protected array $attributes = [];

	/**
	 * The renderer instance used to render the message.
	 */
	protected MessageRenderer $renderer;

	/**
	 * Constructor for the MessageBuilder class.
	 *
	 * @param MessageRenderer $renderer The renderer to use for rendering the message.
	 */
	public function __construct( MessageRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Set the Message's HTML ID attribute.
	 *
	 * @since 0.1.0
	 * @param string $id The ID for the Message element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set the content of the message box as plain text.
	 *
	 * This method specifies the text content that will be displayed inside the message box.
	 * The content will be escaped for security purposes.
	 *
	 * @since 0.1.0
	 * @param string $content The plain text content to be displayed inside the message box.
	 * @param-taint $content escapes_html
	 * @return $this Returns the Message instance for method chaining.
	 */
	public function setContentText( string $content ): self {
		$this->content = $content;

		return $this;
	}

	/**
	 * Set the content of the message box as HTML.
	 *
	 * This method accepts an `HtmlSnippet` object, which contains HTML content to be displayed
	 * inside the message box without escaping.
	 *
	 * @since 0.1.0
	 * @param HtmlSnippet $content The HTML content to be displayed inside the message box.
	 * @param-taint $content exec_html
	 * @return $this Returns the Message instance for method chaining.
	 */
	public function setContentHtml( HtmlSnippet $content ): self {
		$this->content = $content;

		return $this;
	}

	/**
	 * Set the type of the message box.
	 *
	 * This method sets the visual style of the message box based on its type.
	 * The type can be one of the following:
	 * - 'notice': For general information.
	 * - 'warning': For cautionary information.
	 * - 'error': For error messages.
	 * - 'success': For success messages.
	 *
	 * The type is applied as a CSS class (`cdx-message--{type}`) to the message element.
	 *
	 * @since 0.1.0
	 * @param string $type The type of message (e.g., 'notice', 'warning', 'error', 'success').
	 * @return $this Returns the Message instance for method chaining.
	 */
	public function setType( string $type ): self {
		if ( !in_array( $type, self::STATUS_TYPES, true ) ) {
			throw new InvalidArgumentException( "Invalid message type: $type" );
		}
		$this->type = $type;

		return $this;
	}

	/**
	 * Set the inline display of the message box.
	 *
	 * This method determines whether the message box should be displayed inline,
	 * without padding, background color, or border. Inline messages are typically used for
	 * validation feedback or brief notifications within the flow of content.
	 *
	 * @since 0.1.0
	 * @param bool $inline Whether the message box should be displayed inline.
	 * @return $this Returns the Message instance for method chaining.
	 */
	public function setInline( bool $inline ): self {
		$this->inline = $inline;

		return $this;
	}

	/**
	 * Set the heading of the message box.
	 *
	 * This method sets a heading for the message box, which will be displayed prominently at the top of the message
	 * content. The heading helps to quickly convey the primary purpose or topic of the message.
	 *
	 * Example usage:
	 *
	 *     $message->setHeading('Error: Invalid Input');
	 *
	 * @since 0.1.0
	 * @param string $heading The heading text to be displayed inside the message box.
	 * @return $this Returns the Message instance for method chaining.
	 */
	public function setHeading( string $heading ): self {
		$this->heading = $heading;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the message box.
	 *
	 * This method allows custom HTML attributes to be added to the outer `<div>` element of the message box,
	 * such as `id`, `data-*`, `aria-*`, or any other valid attributes. These attributes can be used to
	 * enhance accessibility or integrate with JavaScript.
	 *
	 * The values of these attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * Example usage:
	 *
	 *     $message->setAttributes([
	 *         'id' => 'error-message',
	 *         'data-type' => 'error',
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this Returns the Message instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}
		return $this;
	}

	/**
	 * Build and return the Message component object.
	 * This method constructs the immutable Message object with all the properties set via the builder.
	 *
	 * @since 0.1.0
	 * @return Message The constructed Message.
	 */
	public function build(): Message {
		return new Message(
			$this->id,
			$this->content,
			$this->type,
			$this->inline,
			$this->heading,
			$this->iconClass,
			$this->attributes,
			$this->renderer
		);
	}

}
