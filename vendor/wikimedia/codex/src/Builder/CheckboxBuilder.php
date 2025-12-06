<?php
/**
 * CheckboxBuilder.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `Checkbox` class, a builder for constructing
 * checkbox components using the Codex design system.
 *
 * A Checkbox is a binary input that can appear by itself or in a multiselect group.
 * Checkboxes can be selected, unselected, or in an indeterminate state.
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
use Wikimedia\Codex\Component\Checkbox;
use Wikimedia\Codex\Component\Label;
use Wikimedia\Codex\Renderer\CheckboxRenderer;

/**
 * CheckboxBuilder
 *
 * This class implements the builder pattern to construct instances of Checkbox.
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
class CheckboxBuilder {

	/**
	 * The ID for the checkbox input.
	 */
	protected string $inputId = '';

	/**
	 * The name attribute for the checkbox input.
	 */
	protected string $name = '';

	/**
	 * The label object for the checkbox.
	 */
	protected ?Label $label = null;

	/**
	 * The value associated with the checkbox input.
	 */
	protected string $value = '';

	/**
	 * Indicates if the checkbox is selected by default.
	 */
	protected bool $checked = false;

	/**
	 * Indicates if the checkbox is disabled.
	 */
	protected bool $disabled = false;

	/**
	 * Indicates if the checkbox should be displayed inline.
	 */
	protected bool $inline = false;

	/**
	 * Additional HTML attributes for the input.
	 */
	private array $inputAttributes = [];

	/**
	 * Additional attributes for the wrapper element.
	 */
	private array $wrapperAttributes = [];

	/**
	 * The renderer instance used to render the checkbox.
	 */
	protected CheckboxRenderer $renderer;

	/**
	 * Constructor for the CheckboxBuilder class.
	 *
	 * @param CheckboxRenderer $renderer The renderer to use for rendering the checkbox.
	 */
	public function __construct( CheckboxRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Set the ID for the checkbox input.
	 *
	 * The ID is a unique identifier for the checkbox input element. It is used to associate the input
	 * with its corresponding label and for any JavaScript or CSS targeting.
	 *
	 * @since 0.1.0
	 * @param string $inputId The ID for the checkbox input.
	 * @return $this Returns the Checkbox instance for method chaining.
	 */
	public function setInputId( string $inputId ): self {
		$this->inputId = $inputId;

		return $this;
	}

	/**
	 * Set the name for the checkbox input.
	 *
	 * The name attribute is used to identify form data after the form is submitted. It is crucial when
	 * handling multiple checkboxes as part of a group or when submitting form data via POST or GET requests.
	 *
	 * @since 0.1.0
	 * @param string $name The name attribute for the checkbox input.
	 * @return $this Returns the Checkbox instance for method chaining.
	 */
	public function setName( string $name ): self {
		$this->name = $name;

		return $this;
	}

	/**
	 * Set the label for the checkbox input.
	 *
	 * This method accepts a Label object which provides a descriptive label for the checkbox.
	 *
	 * @since 0.1.0
	 * @param Label $label The Label object for the checkbox.
	 * @return $this Returns the Checkbox instance for method chaining.
	 */
	public function setLabel( Label $label ): self {
		$this->label = $label;

		return $this;
	}

	/**
	 * Set the value for the checkbox input.
	 *
	 * The value is the data that is submitted when the checkbox is checked and the form is submitted.
	 * This is particularly important when dealing with groups of checkboxes where each needs a distinct value.
	 *
	 * @since 0.1.0
	 * @param string $value The value for the checkbox input.
	 * @return $this Returns the Checkbox instance for method chaining.
	 */
	public function setValue( string $value ): self {
		$this->value = $value;

		return $this;
	}

	/**
	 * Set whether the checkbox should be checked.
	 *
	 * This method determines whether the checkbox is selected by default. If set to `true`,
	 * the checkbox will be rendered in a checked state, otherwise, it will be unchecked.
	 *
	 * @since 0.1.0
	 * @param bool $checked Whether the checkbox should be checked.
	 * @return $this Returns the Checkbox instance for method chaining.
	 */
	public function setChecked( bool $checked ): self {
		$this->checked = $checked;

		return $this;
	}

	/**
	 * Set whether the checkbox should be disabled.
	 *
	 * This method determines whether the checkbox is disabled, preventing user interaction.
	 * A disabled checkbox cannot be checked or unchecked by the user and is typically styled to appear inactive.
	 *
	 * @since 0.1.0
	 * @param bool $disabled Whether the checkbox should be disabled.
	 * @return $this Returns the Checkbox instance for method chaining.
	 */
	public function setDisabled( bool $disabled ): self {
		$this->disabled = $disabled;

		return $this;
	}

	/**
	 * Set whether the checkbox should display inline.
	 *
	 * This method determines whether the checkbox and its label should be displayed inline with other elements.
	 * Inline checkboxes are typically used when multiple checkboxes need to appear on the same line.
	 *
	 * @since 0.1.0
	 * @param bool $inline Indicates whether the checkbox should be displayed inline.
	 * @return $this Returns the Checkbox instance for method chaining.
	 */
	public function setInline( bool $inline ): self {
		$this->inline = $inline;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the root checkbox input.
	 *
	 * This method allows custom HTML attributes to be added to the checkbox element, such as `id`, `data-*`, `aria-*`,
	 * or any other valid attributes. These attributes can be used to integrate the checkbox with JavaScript, enhance
	 * accessibility, or provide additional metadata.
	 *
	 * The values of these attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * @since 0.1.0
	 * @param array $inputAttributes An associative array of HTML attributes for the input element.
	 * @return $this Returns the Checkbox instance for method chaining.
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
	 * @return $this Returns the Checkbox instance for method chaining.
	 */
	public function setWrapperAttributes( array $wrapperAttributes ): self {
		foreach ( $wrapperAttributes as $key => $value ) {
			$this->wrapperAttributes[$key] = $value;
		}

		return $this;
	}

	/**
	 * Build and return the Checkbox component object.
	 * This method constructs the immutable Checkbox object with all the properties set via the builder.
	 *
	 * @since 0.1.0
	 * @return Checkbox The constructed Checkbox.
	 */
	public function build(): Checkbox {
		if ( !$this->inputId ) {
			throw new InvalidArgumentException( "The 'id' is required for Checkbox." );
		}
		if ( !$this->label ) {
			throw new InvalidArgumentException( "The 'label' is required for Checkbox." );
		}

		return new Checkbox(
			$this->inputId,
			$this->name,
			$this->label,
			$this->value,
			$this->checked,
			$this->disabled,
			$this->inline,
			$this->inputAttributes,
			$this->wrapperAttributes,
			$this->renderer,
		);
	}
}
