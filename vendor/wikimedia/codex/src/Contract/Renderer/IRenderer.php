<?php
/**
 * IRenderer.php
 *
 * This file is part of the Codex PHP library, which provides a PHP-based interface for rendering
 * UI components consistent with the Codex design system. The `IRenderer` interface defines
 * the contract for rendering any UI component. Any class implementing this interface must provide
 * a method to generate the HTML markup for a given component object.
 *
 * @category Contract\Renderer
 * @package  Codex\Contract\Renderer
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Contract\Renderer;

/**
 * IRenderer defines the interface for rendering any component.
 *
 * Any class that implements this interface must provide the `render` method,
 * which takes a Component object and returns the corresponding HTML markup as a string.
 *
 * @category Contract\Renderer
 * @package  Codex\Contract\Renderer
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
interface IRenderer {
	/**
	 * Renders the HTML markup for a Component.
	 *
	 * This method is responsible for generating the complete HTML markup for the provided
	 * component object. The implementation should ensure that the generated markup is
	 * consistent with the Codex design system's guidelines.
	 *
	 * @since 0.1.0
	 * @param mixed $component The Component object to render.
	 * @return string The generated HTML markup for the component.
	 */
	public function render( $component ): string;
}
