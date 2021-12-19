<?php

declare( strict_types = 1 );

namespace Wikimedia\Dodo;

/**
 * NodeFilter.php
 * --------
 * Stub for https://dom.spec.whatwg.org/#interface-nodefilter
 *
 * @package Wikimedia\Dodo
 */
abstract class NodeFilter implements \Wikimedia\IDLeDOM\NodeFilter {
	// Helper functions from IDLeDOM
	use \Wikimedia\IDLeDOM\Helper\NodeFilter;

	/**
	 * @inheritDoc
	 */
	abstract public function acceptNode( /* Node */ $node ): int;
}
