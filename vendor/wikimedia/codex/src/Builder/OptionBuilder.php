<?php
/**
 * OptionBuilder.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `Option` class, a builder for constructing
 * individual option items within the `Select` component using the Codex design system.
 *
 * The `Option` class allows for easy and flexible creation of option elements with
 * properties such as value, display text, and selected state.
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
use Wikimedia\Codex\Component\Option;

/**
 * OptionBuilder
 *
 * This class implements the builder pattern to construct instances of Option.
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
class OptionBuilder {

	/**
	 * The value of the option, used for form submission and programmatic selection.
	 */
	protected string $value = '';

	/**
	 * The display text for the option, shown in the select dropdown.
	 */
	protected string $text = '';

	/**
	 * Indicates whether the option is selected by default.
	 */
	protected bool $selected = false;

	/**
	 * Set the value for the option.
	 *
	 * The value is used for form submission when this option is selected. The value is HTML-escaped for safety.
	 *
	 * @since 0.1.0
	 * @param string $value The unique value of the option.
	 * @return $this Returns the Option instance for method chaining.
	 */
	public function setValue( string $value ): self {
		if ( trim( $value ) === '' ) {
			throw new InvalidArgumentException( 'Option value cannot be empty.' );
		}
		$this->value = $value;

		return $this;
	}

	/**
	 * Set the display text for the option.
	 *
	 * The text is shown in the select dropdown. If not set, the value can also serve as the default display text.
	 *
	 * @since 0.1.0
	 * @param string $text The display text for the option.
	 * @return $this Returns the Option instance for method chaining.
	 */
	public function setText( string $text ): self {
		$this->text = $text;

		return $this;
	}

	/**
	 * Set whether the option should be selected by default.
	 *
	 * This method specifies whether the option is pre-selected when the select dropdown is first displayed.
	 *
	 * @since 0.1.0
	 * @param bool $selected Whether the option is selected by default.
	 * @return $this Returns the Option instance for method chaining.
	 */
	public function setSelected( bool $selected ): self {
		$this->selected = $selected;

		return $this;
	}

	/**
	 * Build and return the Option component object.
	 * This method constructs the immutable Option object with all the properties set via the builder.
	 *
	 * @since 0.1.0
	 * @return Option The constructed Option.
	 */
	public function build(): Option {
		return new Option(
			$this->value,
			$this->text,
			$this->selected,
		);
	}
}
