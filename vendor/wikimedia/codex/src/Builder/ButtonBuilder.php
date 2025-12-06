<?php
/**
 * ButtonBuilder.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `Button` class, a builder for constructing
 * button elements using the Codex design system.
 *
 * A Button triggers an action when the user clicks or taps on it.
 * It can be styled in various ways to reflect its importance, function, and state.
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
use Wikimedia\Codex\Component\Button;
use Wikimedia\Codex\Renderer\ButtonRenderer;

/**
 * ButtonBuilder
 *
 * This class implements the builder pattern to construct instances of Button.
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
class ButtonBuilder {

	/**
	 * Allowed action styles for the button.
	 */
	private const ALLOWED_ACTIONS = [
		'default',
		'progressive',
		'destructive',
	];

	/**
	 * Allowed sizes for the button.
	 */
	private const ALLOWED_SIZES = [
		'medium',
		'large',
	];

	/**
	 * Allowed button types.
	 */
	private const ALLOWED_TYPES = [
		'button',
		'submit',
		'reset',
	];

	/**
	 * Allowed weight styles for the button.
	 */
	private const ALLOWED_WEIGHTS = [
		'normal',
		'primary',
		'quiet',
	];

	/**
	 * The ID for the button.
	 */
	protected string $id = '';

	/**
	 * The text label displayed on the button.
	 */
	protected string $label = '';

	/**
	 * The visual action style of the button (e.g., default, progressive, destructive).
	 */
	protected string $action = 'default';

	/**
	 * The size of the button (e.g., medium, large).
	 */
	protected string $size = 'medium';

	/**
	 * The type of the button (e.g., button, submit, reset).
	 */
	protected string $type = 'button';

	/**
	 * The visual prominence of the button (e.g., normal, primary, quiet).
	 */
	protected string $weight = 'normal';

	/**
	 * The CSS class for an icon, if the button includes one.
	 */
	protected ?string $iconClass = null;

	/**
	 * Indicates if the button is icon-only (no text).
	 */
	protected bool $iconOnly = false;

	/**
	 * Indicates if the button is disabled.
	 */
	protected bool $disabled = false;

	/**
	 * Additional HTML attributes for the button element.
	 */
	protected array $attributes = [];

	/**
	 * The renderer instance used to render the button.
	 */
	protected ButtonRenderer $renderer;

	/**
	 * Constructor for the ButtonBuilder class.
	 *
	 * @param ButtonRenderer $renderer The renderer to use for rendering the button.
	 */
	public function __construct( ButtonRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Set the button's HTML ID attribute.
	 *
	 * @since 0.1.0
	 * @param string $id The ID for the button element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set the label for the button.
	 *
	 * This method defines the text that will be displayed on the button. The label is crucial for providing
	 * users with context about the button's action. In cases where the button is not icon-only, the label
	 * will be wrapped in a `<span>` element within the button.
	 *
	 * It's important to use concise and descriptive text for the label to ensure usability.
	 *
	 * @since 0.1.0
	 * @param string $label The text label displayed on the button.
	 * @return $this Returns the Button instance for method chaining.
	 */
	public function setLabel( string $label ): self {
		if ( trim( $label ) === '' && !$this->iconOnly ) {
			throw new InvalidArgumentException( 'Button label cannot be empty unless the button is icon-only.' );
		}
		$this->label = $label;

		return $this;
	}

	/**
	 * Set the action style for the button.
	 *
	 * This method determines the visual style of the button, which reflects the nature of the action
	 * it represents. The action can be one of the following:
	 * - 'default': A standard action button with no special emphasis.
	 * - 'progressive': Indicates a positive or confirmatory action, often styled with a green or blue background.
	 * - 'destructive': Used for actions that have a significant or irreversible impact, typically styled in red.
	 *
	 * The action style is applied as a CSS class (`cdx-button--action-{action}`) to the button element.
	 *
	 * @since 0.1.0
	 * @param string $action The action style for the button.
	 * @return $this Returns the Button instance for method chaining.
	 */
	public function setAction( string $action ): self {
		if ( !in_array( $action, self::ALLOWED_ACTIONS, true ) ) {
			throw new InvalidArgumentException( "Invalid action: $action" );
		}
		$this->action = $action;

		return $this;
	}

	/**
	 * Set the weight style for the button.
	 *
	 * This method sets the visual prominence of the button, which can be:
	 * - 'normal': A standard button with default emphasis.
	 * - 'primary': A high-importance button that stands out, often used for primary actions.
	 * - 'quiet': A subtle, low-emphasis button, typically used for secondary or tertiary actions.
	 *
	 * The weight style is applied as a CSS class (`cdx-button--weight-{weight}`) to the button element.
	 *
	 * @since 0.1.0
	 * @param string $weight The weight style for the button.
	 * @return $this Returns the Button instance for method chaining.
	 */
	public function setWeight( string $weight ): self {
		if ( !in_array( $weight, self::ALLOWED_WEIGHTS, true ) ) {
			throw new InvalidArgumentException( "Invalid weight: $weight" );
		}
		$this->weight = $weight;

		return $this;
	}

	/**
	 * Set the size of the button.
	 *
	 * This method defines the size of the button, which can be either:
	 * - 'medium': The default size, suitable for most use cases.
	 * - 'large': A larger button, often used to improve accessibility or to emphasize an action.
	 *
	 * The size is applied as a CSS class (`cdx-button--size-{size}`) to the button element.
	 *
	 * @since 0.1.0
	 * @param string $size The size of the button.
	 * @return $this Returns the Button instance for method chaining.
	 */
	public function setSize( string $size ): self {
		if ( !in_array( $size, self::ALLOWED_SIZES, true ) ) {
			throw new InvalidArgumentException( "Invalid size: $size" );
		}
		$this->size = $size;

		return $this;
	}

	/**
	 * Set the type of the button.
	 *
	 * This method sets the button's type attribute, which can be one of the following:
	 * - 'button': A standard clickable button.
	 * - 'submit': A button used to submit a form.
	 * - 'reset': A button used to reset form fields to their initial values.
	 *
	 * The type attribute is applied directly to the `<button>` element.
	 *
	 * @since 0.1.0
	 * @param string $type The type for the button.
	 * @return $this Returns the Button instance for method chaining.
	 */
	public function setType( string $type ): self {
		if ( !in_array( $type, self::ALLOWED_TYPES, true ) ) {
			throw new InvalidArgumentException( "Invalid button type: $type" );
		}
		$this->type = $type;

		return $this;
	}

	/**
	 * Set the icon class for the button.
	 *
	 * This method specifies a CSS class for an icon to be displayed inside the button. The icon is rendered
	 * within a `<span>` element with the class `cdx-button__icon`, and should be defined using a suitable
	 * icon font or SVG sprite.
	 *
	 * The icon enhances the button's usability by providing a visual cue regarding the button's action.
	 *
	 * @since 0.1.0
	 * @param string $iconClass The CSS class for the icon.
	 * @return $this Returns the Button instance for method chaining.
	 */
	public function setIconClass( string $iconClass ): self {
		$this->iconClass = $iconClass;

		return $this;
	}

	/**
	 * Set whether the button should be icon-only.
	 *
	 * This method determines whether the button should display only an icon, without any text.
	 * When set to `true`, the button will only render the icon, making it useful for scenarios where
	 * space is limited, such as in toolbars or mobile interfaces.
	 *
	 * Icon-only buttons should always include an `aria-label` attribute for accessibility, ensuring that
	 * the button's purpose is clear to screen reader users.
	 *
	 * @since 0.1.0
	 * @param bool $iconOnly Whether the button is icon-only.
	 * @return $this Returns the Button instance for method chaining.
	 */
	public function setIconOnly( bool $iconOnly ): self {
		$this->iconOnly = $iconOnly;

		return $this;
	}

	/**
	 * Set whether the button is disabled.
	 *
	 * This method disables the button, preventing any interaction.
	 * A disabled button appears inactive and cannot be clicked.
	 *
	 * Example usage:
	 *
	 *     $button->setDisabled(true);
	 *
	 * @since 0.1.0
	 * @param bool $disabled Indicates whether the button is disabled.
	 * @return $this Returns the Button instance for method chaining.
	 */
	public function setDisabled( bool $disabled ): self {
		$this->disabled = $disabled;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the button element.
	 *
	 * This method allows custom HTML attributes to be added to the button element, such as `id`, `data-*`, `aria-*`,
	 * or any other valid attributes. These attributes can be used to integrate the button with JavaScript, enhance
	 * accessibility, or provide additional metadata.
	 *
	 * The values of these attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * Example usage:
	 *
	 *     $button->setAttributes([
	 *         'id' => 'submit-button',
	 *         'data-toggle' => 'modal',
	 *         'aria-label' => 'Submit Form'
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this Returns the Button instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}
		return $this;
	}

	/**
	 * Build and return the Button component object.
	 * This method constructs the immutable Button object with all the properties set via the builder.
	 *
	 * @since 0.1.0
	 * @return Button The constructed Button.
	 */
	public function build(): Button {
		return new Button(
			$this->id,
			$this->label,
			$this->action,
			$this->size,
			$this->type,
			$this->weight,
			$this->iconClass,
			$this->iconOnly,
			$this->disabled,
			$this->attributes,
			$this->renderer
		);
	}
}
