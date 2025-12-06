<?php
/**
 * ProgressBarBuilder.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `ProgressBar` class, a builder for constructing
 * progress bar components using the Codex design system.
 *
 * A ProgressBar is a visual element used to indicate the progress of an action or process.
 *
 * @category Builder
 * @package  Codex\Builder
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Builder;

use Wikimedia\Codex\Component\ProgressBar;
use Wikimedia\Codex\Renderer\ProgressBarRenderer;

/**
 * ProgressBarBuilder
 *
 * This class implements the builder pattern to construct instances of ProgressBar.
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
class ProgressBarBuilder {

	/**
	 * The ID for the progress bar.
	 */
	protected string $id = '';

	/**
	 * The ARIA label for the progress bar, important for accessibility.
	 */
	protected string $label = '';

	/**
	 * Whether the progress bar is a smaller, inline variant.
	 */
	protected bool $inline = false;

	/**
	 * Whether the progress bar is disabled.
	 */
	protected bool $disabled = false;

	/**
	 * Additional HTML attributes for the outer `<div>` element of the progress bar.
	 */
	protected array $attributes = [];

	/**
	 * The renderer instance used to render the progress bar.
	 */
	protected ProgressBarRenderer $renderer;

	/**
	 * Constructor for the ProgressBarBuilder class.
	 *
	 * @param ProgressBarRenderer $renderer The renderer to use for rendering the progress bar.
	 */
	public function __construct( ProgressBarRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Set the ProgressBar's HTML ID attribute.
	 *
	 * @since 0.1.0
	 * @param string $id The ID for the ProgressBar element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set the ARIA label for the progress bar.
	 *
	 * This method sets the ARIA label for the progress bar, which is important for accessibility.
	 * The label provides a descriptive name for the progress bar, helping users with assistive technologies
	 * to understand its purpose.
	 *
	 * Example usage:
	 *
	 *     $progressBar->setLabel('File upload progress');
	 *
	 * @since 0.1.0
	 * @param string $label The ARIA label for the progress bar.
	 * @return $this Returns the ProgressBar instance for method chaining.
	 */
	public function setLabel( string $label ): self {
		$this->label = $label;

		return $this;
	}

	/**
	 * Set whether the progress bar should be displayed inline.
	 *
	 * This method sets the `inline` property, which controls whether the progress bar should be
	 * displayed as a smaller, inline variant. The inline variant is typically used in compact spaces.
	 *
	 * Example usage:
	 *
	 *     $progressBar->setInline(true);
	 *
	 * @since 0.1.0
	 * @param bool $inline Whether the progress bar should be displayed inline.
	 * @return $this Returns the ProgressBar instance for method chaining.
	 */
	public function setInline( bool $inline ): self {
		$this->inline = $inline;

		return $this;
	}

	/**
	 * Set whether the progress bar is disabled.
	 *
	 * This method sets the `disabled` property, which controls whether the progress bar is disabled.
	 * A disabled progress bar may be visually different and indicate to the user that it is inactive.
	 *
	 * Example usage:
	 *
	 *     $progressBar->setDisabled(true);
	 *
	 * @since 0.1.0
	 * @param bool $disabled Whether the progress bar is disabled.
	 * @return $this Returns the ProgressBar instance for method chaining.
	 */
	public function setDisabled( bool $disabled ): self {
		$this->disabled = $disabled;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the outer `<div>` element.
	 *
	 * This method allows custom HTML attributes to be added to the outer `<div>` element of the progress bar,
	 * such as `id`, `data-*`, `aria-*`, or any other valid attributes. These attributes can be used to
	 * enhance accessibility or integrate with JavaScript.
	 *
	 * The values of these attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * Example usage:
	 *
	 *     $progressBar->setAttributes([
	 *         'id' => 'file-upload-progress',
	 *         'data-upload' => 'true',
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this Returns the ProgressBar instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}
		return $this;
	}

	/**
	 * Build and return the ProgressBar component object.
	 * This method constructs the immutable ProgressBar object with all the properties set via the builder.
	 *
	 * @since 0.1.0
	 * @return ProgressBar The constructed ProgressBar.
	 */
	public function build(): ProgressBar {
		return new ProgressBar(
			$this->id,
			$this->label,
			$this->inline,
			$this->disabled,
			$this->attributes,
			$this->renderer
		);
	}
}
