<?php
/**
 * Option.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Option` class, responsible for managing
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

/**
 * Option
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
class Option {

	/**
	 * The value of the option, used for form submission and programmatic selection.
	 */
	protected string $value;

	/**
	 * The display text for the option, shown in the select dropdown.
	 */
	protected string $text;

	/**
	 * Indicates whether the option is selected by default.
	 */
	protected bool $selected;

	/**
	 * Constructor for the Option component.
	 *
	 * Initializes an Option instance with the specified properties.
	 *
	 * @param string $value The value of the option.
	 * @param string $text The display text of the option.
	 * @param bool $selected Whether the option is selected by default.
	 */
	public function __construct(
		string $value,
		string $text,
		bool $selected
	) {
		$this->value = $value;
		$this->text = $text;
		$this->selected = $selected;
	}

	/**
	 * Get the option's value.
	 *
	 * @since 0.1.0
	 * @return string The value of the option.
	 */
	public function getValue(): string {
		return $this->value;
	}

	/**
	 * Get the option's display text.
	 *
	 * @since 0.1.0
	 * @return string The display text of the option.
	 */
	public function getText(): string {
		return $this->text;
	}

	/**
	 * Get the option's selected state.
	 *
	 * @since 0.1.0
	 * @return bool Whether the option is selected.
	 */
	public function isSelected(): bool {
		return $this->selected;
	}
}
