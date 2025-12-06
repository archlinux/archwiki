<?php
/**
 * Tab.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Tab` class, responsible for managing
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
 * Tab
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
class Tab {

	/**
	 * The ID for the tab.
	 */
	private string $id;

	/**
	 * The unique name of the tab, used for programmatic selection.
	 */
	private string $name;

	/**
	 * The text label displayed for the tab in the Tabs component's header.
	 */
	private string $label;

	/**
	 * The HTML content associated with the tab, displayed when the tab is selected.
	 */
	private string $content;

	/**
	 * Indicates whether the tab is selected by default.
	 */
	private bool $selected;

	/**
	 * Indicates whether the tab is disabled, preventing interaction and navigation.
	 */
	private bool $disabled;

	/**
	 * Constructor for the Tab component.
	 *
	 * Initializes a Tab instance with the specified properties.
	 *
	 * @param string $id The ID for the tab.
	 * @param string $name The unique name of the tab.
	 * @param string $label The label of the tab.
	 * @param string $content The content of the tab.
	 * @param bool $selected Whether the tab is selected by default.
	 * @param bool $disabled Whether the tab is disabled.
	 */
	public function __construct(
		string $id,
		string $name,
		string $label,
		string $content,
		bool $selected,
		bool $disabled
	) {
		$this->id = $id;
		$this->name = $name;
		$this->label = $label;
		$this->content = $content;
		$this->selected = $selected;
		$this->disabled = $disabled;
	}

	/**
	 * Get the Tab's HTML ID attribute.
	 *
	 * This method returns the ID assigned to the tab element, which is used
	 * for identifying the tab in the HTML document.
	 *
	 * @since 0.1.0
	 * @return string The ID of the Tab element.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the tab's name.
	 *
	 * @since 0.1.0
	 * @return string The unique name of the tab.
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Get the tab's label.
	 *
	 * @since 0.1.0
	 * @return string The label of the tab.
	 */
	public function getLabel(): string {
		return $this->label;
	}

	/**
	 * Get the tab's content.
	 *
	 * @since 0.1.0
	 * @return string The content of the tab.
	 */
	public function getContent(): string {
		return $this->content;
	}

	/**
	 * Get the tab's selected state.
	 *
	 * @since 0.1.0
	 * @return bool Whether the tab is selected.
	 */
	public function isSelected(): bool {
		return $this->selected;
	}

	/**
	 * Get the tab's disabled state.
	 *
	 * @since 0.1.0
	 * @return bool Whether the tab is disabled.
	 */
	public function isDisabled(): bool {
		return $this->disabled;
	}
}
