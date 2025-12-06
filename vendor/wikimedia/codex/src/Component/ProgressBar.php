<?php
/**
 * ProgressBar.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `ProgressBar` class, responsible for managing
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

use Wikimedia\Codex\Renderer\ProgressBarRenderer;

/**
 * ProgressBar
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
class ProgressBar {

	/**
	 * The ID for the progress bar.
	 */
	private string $id;

	/**
	 * The ARIA label for the progress bar, important for accessibility.
	 */
	private string $label;

	/**
	 * Whether the progress bar is a smaller, inline variant.
	 */
	private bool $inline;

	/**
	 * Whether the progress bar is disabled.
	 */
	private bool $disabled;

	/**
	 * Additional HTML attributes for the outer `<div>` element of the progress bar.
	 */
	private array $attributes;

	/**
	 * The renderer instance used to render the progress bar.
	 */
	private ProgressBarRenderer $renderer;

	/**
	 * Constructor for the ProgressBar component.
	 *
	 * Initializes a ProgressBar instance with the specified properties.
	 *
	 * @param string $id The ID for the progress bar.
	 * @param string $label The ARIA label for the progress bar.
	 * @param bool $inline Whether the progress bar is inline.
	 * @param bool $disabled Whether the progress bar is disabled.
	 * @param array $attributes Additional HTML attributes for the progress bar.
	 * @param ProgressBarRenderer $renderer The renderer to use for rendering the progress bar.
	 */
	public function __construct(
		string $id,
		string $label,
		bool $inline,
		bool $disabled,
		array $attributes,
		ProgressBarRenderer $renderer
	) {
		$this->id = $id;
		$this->label = $label;
		$this->inline = $inline;
		$this->disabled = $disabled;
		$this->attributes = $attributes;
		$this->renderer = $renderer;
	}

	/**
	 * Get the ProgressBar's HTML ID attribute.
	 *
	 * This method returns the ID assigned to the progress bar element, which is used
	 * for identifying the progress bar in the HTML document.
	 *
	 * @since 0.1.0
	 * @return string The ID of the ProgressBar.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the ARIA label for the progress bar.
	 *
	 * This method returns the ARIA label that is used for the progress bar, which is important for accessibility.
	 * The label provides a descriptive name for the progress bar, helping users with assistive technologies
	 * to understand its purpose.
	 *
	 * @since 0.1.0
	 * @return string The ARIA label for the progress bar.
	 */
	public function getLabel(): string {
		return $this->label;
	}

	/**
	 * Get whether the progress bar is inline.
	 *
	 * This method returns a boolean value indicating whether the progress bar is the smaller, inline variant.
	 *
	 * @since 0.1.0
	 * @return bool True if the progress bar is inline, false otherwise.
	 */
	public function isInline(): bool {
		return $this->inline;
	}

	/**
	 * Get whether the progress bar is disabled.
	 *
	 * This method returns a boolean value indicating whether the progress bar is disabled.
	 *
	 * @since 0.1.0
	 * @return bool True if the progress bar is disabled, false otherwise.
	 */
	public function isDisabled(): bool {
		return $this->disabled;
	}

	/**
	 * Get the additional HTML attributes for the outer `<div>` element.
	 *
	 * This method returns an associative array of HTML attributes that are applied to the outer `<div>` element of the
	 * progress bar. These attributes can include `id`, `data-*`, `aria-*`, or any other valid HTML attributes.
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
