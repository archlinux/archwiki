<?php
/**
 * InfoChip.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `InfoChip` class, responsible for managing
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

use Wikimedia\Codex\Renderer\InfoChipRenderer;

/**
 * InfoChip
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
class InfoChip {

	/**
	 * The ID for the InfoChip.
	 */
	protected string $id;

	/**
	 * The text displayed inside the InfoChip.
	 */
	protected string $text;

	/**
	 * The status type, determines chip's visual style. Options include 'notice', 'warning', 'error', and 'success'.
	 */
	protected string $status;

	/**
	 * The CSS class for a custom icon used in the InfoChip, applicable only for the 'notice' status.
	 */
	protected ?string $icon;

	/**
	 * Additional HTML attributes for the outer `<div>` element of the InfoChip.
	 */
	protected array $attributes;

	/**
	 * The renderer instance used to render the infoChip.
	 */
	protected InfoChipRenderer $renderer;

	/**
	 * Constructor for the InfoChip component.
	 *
	 * Initializes an InfoChip instance with the specified properties.
	 *
	 * @param string $id The ID for the InfoChip.
	 * @param string $text The text displayed inside the InfoChip.
	 * @param string $status The status type of the InfoChip.
	 * @param string|null $icon The CSS class for a custom icon, if any.
	 * @param array $attributes Additional HTML attributes for the InfoChip element.
	 * @param InfoChipRenderer $renderer The renderer to use for rendering the InfoChip.
	 */
	public function __construct(
		string $id,
		string $text,
		string $status,
		?string $icon,
		array $attributes,
		InfoChipRenderer $renderer
	) {
		$this->id = $id;
		$this->text = $text;
		$this->status = $status;
		$this->icon = $icon;
		$this->attributes = $attributes;
		$this->renderer = $renderer;
	}

	/**
	 * Get the InfoChip's HTML ID attribute.
	 *
	 * This method returns the ID that is assigned to the InfoChip element.
	 * The ID can be used for targeting the chip with JavaScript, CSS, or for accessibility purposes.
	 *
	 * @since 0.1.0
	 * @return string The ID of the InfoChip element.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the text content of the info chip.
	 *
	 * This method returns the text that is displayed inside the info chip.
	 * The text provides the primary information that the chip conveys.
	 *
	 * @since 0.1.0
	 * @return string The text content of the info chip.
	 */
	public function getText(): string {
		return $this->text;
	}

	/**
	 * Get the status type of the info chip.
	 *
	 * This method returns the status type of the info chip, which determines its visual style.
	 * The status can be one of the following: 'notice', 'warning', 'error', or 'success'.
	 *
	 * @since 0.1.0
	 * @return string The status type of the info chip.
	 */
	public function getStatus(): string {
		return $this->status;
	}

	/**
	 * Get the custom icon class for the info chip.
	 *
	 * This method returns the CSS class for a custom icon used in the info chip, if applicable.
	 * This option is only available for chips with the "notice" status.
	 *
	 * @since 0.1.0
	 * @return string|null The CSS class for the custom icon, or null if no icon is set.
	 */
	public function getIcon(): ?string {
		return $this->icon;
	}

	/**
	 * Retrieve additional HTML attributes for the outer `<div>` element.
	 *
	 * This method returns an associative array of additional HTML attributes that will be applied
	 * to the outer `<div>` element of the info chip. These attributes can be used to improve
	 * accessibility, customization, or to integrate with JavaScript.
	 *
	 * @since 0.1.0
	 * @return array The additional attributes as an array.
	 */
	public function getAttributes(): array {
		return $this->attributes;
	}

	/**
	 * Get the component's HTML representation.
	 *
	 * This method generates the HTML markup for the component, incorporating relevant properties
	 * and any additional attributes. The component is structured using appropriate HTML elements
	 * as defined by the implementation.
	 *
	 * @since 0.1.0
	 * @return string The generated HTML string for the component.
	 */
	public function getHtml(): string {
		return $this->renderer->render( $this );
	}
}
