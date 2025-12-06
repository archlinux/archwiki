<?php
/**
 * RadioBuilder.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `Radio` class, a builder for constructing
 * radio button components using the Codex design system.
 *
 * A Radio is a binary input that is usually combined in a group of two or more options.
 * They signal a pattern where users can only select one of the available options. Radios
 * are also known as “radio buttons.”
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
use Wikimedia\Codex\Component\Label;
use Wikimedia\Codex\Component\Radio;
use Wikimedia\Codex\Renderer\RadioRenderer;

/**
 * RadioBuilder
 *
 * This class implements the builder pattern to construct instances of Radio.
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
class RadioBuilder {

	/**
	 * The ID for the radio input.
	 */
	protected string $inputId = '';

	/**
	 * The name attribute for the radio input.
	 */
	protected string $name = '';

	/**
	 * The label object for the radio.
	 */
	protected ?Label $label = null;

	/**
	 * The value associated with the radio input.
	 */
	protected string $value = '';

	/**
	 * Indicates if the radio is selected by default.
	 */
	protected bool $checked = false;

	/**
	 * Indicates if the radio is disabled.
	 */
	protected bool $disabled = false;

	/**
	 * Indicates if the radio should be displayed inline.
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
	 * The renderer instance used to render the radio.
	 */
	protected RadioRenderer $renderer;

	/**
	 * Constructor for the RadioBuilder class.
	 *
	 * @param RadioRenderer $renderer The renderer to use for rendering the radio.
	 */
	public function __construct( RadioRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Set the ID for the radio input.
	 *
	 * The ID is a unique identifier for the radio input element. It is used to associate the input
	 * with its corresponding label and for any JavaScript or CSS targeting.
	 *
	 * @since 0.1.0
	 * @param string $inputId The ID for the radio input.
	 * @return $this Returns the Radio instance for method chaining.
	 */
	public function setInputId( string $inputId ): self {
		$this->inputId = $inputId;

		return $this;
	}

	/**
	 * Set the name for the radio input.
	 *
	 * The name attribute is used to identify form data after the form is submitted. It is crucial when
	 * handling groups of radio buttons where only one option can be selected at a time.
	 *
	 * @since 0.1.0
	 * @param string $name The name attribute for the radio input.
	 * @return $this Returns the Radio instance for method chaining.
	 */
	public function setName( string $name ): self {
		$this->name = $name;

		return $this;
	}

	/**
	 * Set the label for the radio input.
	 *
	 * This method accepts a Label object which provides a descriptive label for the radio.
	 *
	 * @since 0.1.0
	 * @param Label $label The Label object for the radio.
	 * @return $this Returns the Radio instance for method chaining.
	 */
	public function setLabel( Label $label ): self {
		$this->label = $label;

		return $this;
	}

	/**
	 * Set the value for the radio input.
	 *
	 * The value is the data that is submitted when the radio button is selected and the form is submitted.
	 * This is particularly important when dealing with groups of radio buttons where each needs a distinct value.
	 *
	 * @since 0.1.0
	 * @param string $value The value for the radio input.
	 * @return $this Returns the Radio instance for method chaining.
	 */
	public function setValue( string $value ): self {
		$this->value = $value;

		return $this;
	}

	/**
	 * Set whether the radio should be checked.
	 *
	 * This method determines whether the radio button is selected by default. If set to `true`,
	 * the radio button will be rendered in a checked state, otherwise, it will be unchecked.
	 *
	 * @since 0.1.0
	 * @param bool $checked Whether the radio button should be checked.
	 * @return $this Returns the Radio instance for method chaining.
	 */
	public function setChecked( bool $checked ): self {
		$this->checked = $checked;

		return $this;
	}

	/**
	 * Set whether the radio should be disabled.
	 *
	 * This method determines whether the radio button is disabled, preventing user interaction.
	 * A disabled radio button cannot be selected or deselected by the user and is typically styled to appear inactive.
	 *
	 * @since 0.1.0
	 * @param bool $disabled Whether the radio button should be disabled.
	 * @return $this Returns the Radio instance for method chaining.
	 */
	public function setDisabled( bool $disabled ): self {
		$this->disabled = $disabled;

		return $this;
	}

	/**
	 * Set whether the radio button should be displayed inline.
	 *
	 * This method determines whether the radio button and its label should be displayed inline with other elements.
	 * Inline radio buttons are typically used when multiple radio buttons need to appear on the same line.
	 *
	 * @since 0.1.0
	 * @param bool $inline Indicates whether the radio button should be displayed inline.
	 * @return $this Returns the Radio instance for method chaining.
	 */
	public function setInline( bool $inline ): self {
		$this->inline = $inline;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the radio input.
	 *
	 * This method allows custom HTML attributes to be added to the radio input element, such as `id`, `data-*`,
	 * `aria-*`, or any other valid attributes. These attributes can be used to integrate the radio button with
	 * JavaScript, enhance accessibility, or provide additional metadata.
	 *
	 * The values of these attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * Example usage:
	 *
	 *     $radio->setInputAttributes([
	 *         'id' => 'radio-button-1',
	 *         'data-toggle' => 'radio-toggle',
	 *         'aria-label' => 'Radio Button 1'
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $inputAttributes An associative array of HTML attributes for the input element.
	 * @return $this Returns the Radio instance for method chaining.
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
	 * @return $this Returns the Radio instance for method chaining.
	 */
	public function setWrapperAttributes( array $wrapperAttributes ): self {
		foreach ( $wrapperAttributes as $key => $value ) {
			$this->wrapperAttributes[$key] = $value;
		}

		return $this;
	}

	/**
	 * Build and return the Radio component object.
	 * This method constructs the immutable Radio object with all the properties set via the builder.
	 *
	 * @since 0.1.0
	 * @return Radio The constructed Radio.
	 */
	public function build(): Radio {
		if ( !$this->inputId ) {
			throw new InvalidArgumentException( "The 'id' is required for Radio." );
		}
		if ( !$this->label ) {
			throw new InvalidArgumentException( "The 'label' is required for Radio." );
		}

		return new Radio(
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
