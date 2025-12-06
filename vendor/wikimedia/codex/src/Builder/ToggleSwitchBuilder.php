<?php
/**
 * ToggleSwitchBuilder.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `ToggleSwitchBuilder` class, a builder for
 * constructing toggle switch components using the Codex design system.
 *
 * A ToggleSwitch enables the user to instantly toggle between on and off states.
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
use Wikimedia\Codex\Component\ToggleSwitch;
use Wikimedia\Codex\Renderer\ToggleSwitchRenderer;

/**
 * ToggleSwitchBuilder
 *
 * This class implements the builder pattern to construct instances of ToggleSwitch.
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
class ToggleSwitchBuilder {

	/**
	 * The ID for the toggle switch input.
	 */
	protected string $inputId = '';

	/**
	 * The name attribute for the toggle switch input.
	 */
	protected string $name = '';

	/**
	 * The value associated with the toggle switch input.
	 */
	protected string $value = '';

	/**
	 * The label for the toggle switch.
	 */
	protected ?Label $label = null;

	/**
	 * Whether the toggle is checked by default.
	 */
	protected bool $checked = false;

	/**
	 * Whether the toggle is disabled.
	 */
	protected bool $disabled = false;

	/**
	 * Additional HTML attributes for the input element.
	 */
	protected array $inputAttributes = [];

	/**
	 * Additional HTML attributes for the wrapper element.
	 */
	protected array $wrapperAttributes = [];

	/**
	 * The renderer instance used to render the toggle switch.
	 */
	protected ToggleSwitchRenderer $renderer;

	/**
	 * Constructor for the ToggleSwitchBuilder class.
	 *
	 * @param ToggleSwitchRenderer $renderer The renderer to use for rendering the toggle switch.
	 */
	public function __construct( ToggleSwitchRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Set the ID for the toggle switch input.
	 *
	 * @param string $inputId The ID for the toggle switch input.
	 * @return $this Returns the ToggleSwitch instance for method chaining.
	 */
	public function setInputId( string $inputId ): self {
		$this->inputId = $inputId;
		return $this;
	}

	/**
	 * Set the name for the toggle switch input.
	 *
	 * @param string $name The name attribute for the toggle switch input.
	 * @return $this Returns the ToggleSwitch instance for method chaining.
	 */
	public function setName( string $name ): self {
		$this->name = $name;
		return $this;
	}

	/**
	 * Set the value for the toggle switch input.
	 *
	 * @param string $value The value associated with the toggle switch input.
	 * @return $this Returns the ToggleSwitch instance for method chaining.
	 */
	public function setValue( string $value ): self {
		$this->value = $value;
		return $this;
	}

	/**
	 * Set the label for the toggle switch.
	 *
	 * @param Label $label The label object for the toggle switch.
	 * @return $this Returns the ToggleSwitch instance for method chaining.
	 */
	public function setLabel( Label $label ): self {
		$this->label = $label;
		return $this;
	}

	/**
	 * Set whether the toggle switch should be checked by default.
	 *
	 * @param bool $checked Whether the toggle switch should be checked by default.
	 * @return $this Returns the ToggleSwitch instance for method chaining.
	 */
	public function setChecked( bool $checked ): self {
		$this->checked = $checked;
		return $this;
	}

	/**
	 * Set whether the toggle switch should be disabled.
	 *
	 * @param bool $disabled Whether the toggle switch should be disabled.
	 * @return $this Returns the ToggleSwitch instance for method chaining.
	 */
	public function setDisabled( bool $disabled ): self {
		$this->disabled = $disabled;
		return $this;
	}

	/**
	 * Set additional HTML attributes for the input element.
	 *
	 * @param array $inputAttributes An associative array of HTML attributes for the input element.
	 * @return $this Returns the ToggleSwitch instance for method chaining.
	 */
	public function setInputAttributes( array $inputAttributes ): self {
		$this->inputAttributes = $inputAttributes;
		return $this;
	}

	/**
	 * Set additional HTML attributes for the wrapper element.
	 *
	 * @param array $wrapperAttributes An associative array of HTML attributes for the wrapper element.
	 * @return $this Returns the ToggleSwitch instance for method chaining.
	 */
	public function setWrapperAttributes( array $wrapperAttributes ): self {
		$this->wrapperAttributes = $wrapperAttributes;
		return $this;
	}

	/**
	 * Build and return the ToggleSwitch component object.
	 *
	 * @return ToggleSwitch The constructed ToggleSwitch component.
	 */
	public function build(): ToggleSwitch {
		if ( !$this->inputId ) {
			throw new InvalidArgumentException( "The 'id' is required for ToggleSwitch." );
		}
		if ( !$this->label ) {
			throw new InvalidArgumentException( "The 'label' is required for ToggleSwitch." );
		}

		return new ToggleSwitch(
			$this->inputId,
			$this->name,
			$this->value,
			$this->label,
			$this->checked,
			$this->disabled,
			$this->inputAttributes,
			$this->wrapperAttributes,
			$this->renderer
		);
	}
}
