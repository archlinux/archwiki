<?php
/**
 * TextInputBuilder
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `TextInput` class, a builder for constructing
 * text input components using the Codex design system.
 *
 * A text input is a form element that lets users input and edit a single-line text value.
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
use Wikimedia\Codex\Component\TextInput;
use Wikimedia\Codex\Renderer\TextInputRenderer;

/**
 * TextInputBuilder
 *
 * This class implements the builder pattern to construct instances of TextInput.
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
class TextInputBuilder {

	/**
	 * Supported input types for the text input.
	 */
	private const TEXT_INPUT_TYPES = [
		'text',
		'search',
		'number',
		'email',
		'month',
		'password',
		'tel',
		'url',
		'week',
		'date',
		'datetime-local',
		'time',
	];

	/**
	 * Allowed validation statuses for the TextInput.
	 */
	private const ALLOWED_STATUSES = [
		'default',
		'error',
		'warning',
		'success'
	];

	/**
	 * Input field type.
	 */
	private string $type = 'text';

	/**
	 * Whether to show a start icon.
	 */
	private bool $hasStartIcon = false;

	/**
	 * Whether to show an end icon.
	 */
	private bool $hasEndIcon = false;

	/**
	 * Whether the input is disabled.
	 */
	private bool $disabled = false;

	/**
	 * Validation status for the input.
	 */
	private string $status = 'default';

	/**
	 * CSS class for the start icon.
	 */
	private string $startIconClass = '';

	/**
	 * CSS class for the end icon.
	 */
	private string $endIconClass = '';

	/**
	 * Additional HTML attributes for the TextInput.
	 */
	private array $inputAttributes = [];

	/**
	 * Additional attributes for the wrapper element.
	 */
	private array $wrapperAttributes = [];

	/**
	 * Placeholder text for the TextInput.
	 */
	private string $placeholder = '';

	/**
	 * The name attribute of the TextInput.
	 */
	private string $name = '';

	/**
	 * The default value of the TextInput.
	 */
	private string $value = '';

	/**
	 * ID attribute for the TextInput.
	 */
	private string $inputId = '';

	/**
	 * The renderer instance used to render the text input.
	 */
	protected TextInputRenderer $renderer;

	/**
	 * Constructor for the TextInputBuilder class.
	 *
	 * @param TextInputRenderer $renderer The renderer to use for rendering the text input.
	 */
	public function __construct( TextInputRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Set the type of the input field.
	 *
	 * This method sets the type attribute of the input field, which determines
	 * the type of data the input field accepts, such as 'text', 'email', 'password', etc.
	 *
	 * Example usage:
	 *
	 *     $textInput->setType('email');
	 *
	 * @since 0.1.0
	 * @param string $type The type of the input field (e.g., 'text', 'email').
	 * @return $this Returns the TextInput instance for method chaining.
	 */
	public function setType( string $type ): self {
		if ( !in_array( $type, self::TEXT_INPUT_TYPES, true ) ) {
			throw new InvalidArgumentException( "Invalid input type: $type" );
		}
		$this->type = $type;

		return $this;
	}

	/**
	 * Set the name attribute for the input field.
	 *
	 * This method specifies the name attribute for the input field, which is used to identify
	 * the input form control when submitting the form data.
	 *
	 * Example usage:
	 *
	 *     $textInput->setName('email');
	 *
	 * @since 0.1.0
	 * @param string $name The name attribute for the input field.
	 * @return $this Returns the TextInput instance for method chaining.
	 */
	public function setName( string $name ): self {
		$this->name = $name;

		return $this;
	}

	/**
	 * Set the value attribute for the input field.
	 *
	 * This method specifies the value attribute for the input field, which represents the
	 * current value of the input field.
	 *
	 * Example usage:
	 *
	 *     $textInput->setValue('example@example.com');
	 *
	 * @since 0.1.0
	 * @param string $value The value of the input field.
	 * @return $this Returns the TextInput instance for method chaining.
	 */
	public function setValue( string $value ): self {
		$this->value = $value;

		return $this;
	}

	/**
	 * Set the ID for the input field.
	 *
	 * This method sets the ID attribute for the input field, which is useful for linking
	 * the input field to a label or for other JavaScript interactions.
	 *
	 * Example usage:
	 *
	 *     $textInput->setInputId('email-input');
	 *
	 * @since 0.1.0
	 * @param string $inputId The ID of the input field.
	 * @return $this Returns the TextInput instance for method chaining.
	 */
	public function setInputId( string $inputId ): self {
		$this->inputId = $inputId;

		return $this;
	}

	/**
	 * Set whether the input has a start icon.
	 *
	 * This method specifies whether the input field should have an icon at the start.
	 * The icon can be used to visually indicate the type of input expected.
	 *
	 * Example usage:
	 *
	 *     $textInput->setHasStartIcon(true);
	 *
	 * @since 0.1.0
	 * @param bool $hasStartIcon Indicates whether the input field has a start icon.
	 * @return $this Returns the TextInput instance for method chaining.
	 */
	public function setHasStartIcon( bool $hasStartIcon ): self {
		$this->hasStartIcon = $hasStartIcon;

		return $this;
	}

	/**
	 * Set whether the input has an end icon.
	 *
	 * This method specifies whether the input field should have an icon at the end.
	 * The icon can be used to visually indicate additional functionality or context.
	 *
	 * Example usage:
	 *
	 *     $textInput->setHasEndIcon(true);
	 *
	 * @since 0.1.0
	 * @param bool $hasEndIcon Indicates whether the input field has an end icon.
	 * @return $this Returns the TextInput instance for method chaining.
	 */
	public function setHasEndIcon( bool $hasEndIcon ): self {
		$this->hasEndIcon = $hasEndIcon;

		return $this;
	}

	/**
	 * Set whether the input is disabled.
	 *
	 * This method disables the input field, making it uneditable and visually distinct.
	 * The disabled attribute is useful for read-only forms or when the input is temporarily inactive.
	 *
	 * Example usage:
	 *
	 *     $textInput->setDisabled(true);
	 *
	 * @since 0.1.0
	 * @param bool $disabled Indicates whether the input field should be disabled.
	 * @return $this Returns the TextInput instance for method chaining.
	 */
	public function setDisabled( bool $disabled ): self {
		$this->disabled = $disabled;

		return $this;
	}

	/**
	 * Set the validation status for the input.
	 *
	 * Example usage:
	 *
	 *     $textInput->setStatus('error');
	 *
	 * @since 0.1.0
	 * @param string $status Current validation status.
	 * @return $this
	 */
	public function setStatus( string $status ): self {
		if ( !in_array( $status, self::ALLOWED_STATUSES, true ) ) {
			throw new InvalidArgumentException( "Invalid status: $status" );
		}
		$this->status = $status;

		return $this;
	}

	/**
	 * Set the CSS class for the start icon.
	 *
	 * This method specifies the CSS class that will be applied to the start icon.
	 * The class can be used to style the icon or apply a background image.
	 *
	 * Example usage:
	 *
	 *     $textInput->setStartIconClass('icon-class-name');
	 *
	 * @since 0.1.0
	 * @param string $startIconClass The CSS class for the start icon.
	 * @return $this Returns the TextInput instance for method chaining.
	 */
	public function setStartIconClass( string $startIconClass ): self {
		$this->startIconClass = $startIconClass;

		return $this;
	}

	/**
	 * Set the CSS class for the end icon.
	 *
	 * This method specifies the CSS class that will be applied to the end icon.
	 * The class can be used to style the icon or apply a background image.
	 *
	 * Example usage:
	 *
	 *     $textInput->setEndIconClass('icon-class-name');
	 *
	 * @since 0.1.0
	 * @param string $endIconClass The CSS class for the end icon.
	 * @return $this Returns the TextInput instance for method chaining.
	 */
	public function setEndIconClass( string $endIconClass ): self {
		$this->endIconClass = $endIconClass;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the input element.
	 *
	 * This method allows custom HTML attributes to be added to the input element, such as `data-*`,
	 * `aria-*`, or any other valid attributes that enhance functionality or accessibility.
	 *
	 * Example usage:
	 *
	 *     $textInput->setInputAttributes(['data-test' => 'value']);
	 *
	 * @since 0.1.0
	 * @param array $inputAttributes An associative array of HTML attributes for the input element.
	 * @return $this Returns the TextInput instance for method chaining.
	 */
	public function setInputAttributes( array $inputAttributes ): self {
		foreach ( $inputAttributes as $key => $value ) {
			$this->inputAttributes[$key] = $value;
		}
		return $this;
	}

	/**
	 * Set additional HTML attributes for the outer wrapper element.
	 *
	 * This method allows custom HTML attributes to be added to the outer wrapper element,
	 * enhancing its behavior or styling.
	 *
	 * Example usage:
	 *
	 *     $textInput->setWrapperAttributes(['id' => 'custom-wrapper']);
	 *
	 * @since 0.1.0
	 * @param array $wrapperAttributes An associative array of HTML attributes for the wrapper element.
	 * @return $this Returns the TextInput instance for method chaining.
	 */
	public function setWrapperAttributes( array $wrapperAttributes ): self {
		foreach ( $wrapperAttributes as $key => $value ) {
			$this->wrapperAttributes[$key] = $value;
		}
		return $this;
	}

	/**
	 * Set the placeholder text for the input element.
	 *
	 * This method sets the placeholder text, which is displayed when the input field is empty.
	 * It provides a hint to the user about what should be entered in the field.
	 *
	 * Example usage:
	 *
	 *     $textInput->setPlaceholder('johndoe@example.com');
	 *
	 * @since 0.1.0
	 * @param string $placeholder The placeholder text for the input field.
	 * @return $this Returns the TextInput instance for method chaining.
	 */
	public function setPlaceholder( string $placeholder ): self {
		$this->placeholder = $placeholder;

		return $this;
	}

	/**
	 * Build and return the TextInput component object.
	 * This method constructs the immutable TextInput object with all the properties set via the builder.
	 *
	 * @since 0.1.0
	 * @return TextInput The constructed TextInput.
	 */
	public function build(): TextInput {
		return new TextInput(
			$this->type,
			$this->hasStartIcon,
			$this->hasEndIcon,
			$this->disabled,
			$this->status,
			$this->startIconClass,
			$this->endIconClass,
			$this->inputAttributes,
			$this->wrapperAttributes,
			$this->placeholder,
			$this->name,
			$this->value,
			$this->inputId,
			$this->renderer
		);
	}
}
