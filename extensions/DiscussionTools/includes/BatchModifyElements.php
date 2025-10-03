<?php

namespace MediaWiki\Extension\DiscussionTools;

use LogicException;
use MediaWiki\Html\HtmlHelper;
use Wikimedia\RemexHtml\Serializer\SerializerNode;

class BatchModifyElements {
	/** @var array<array{callable, callable}> */
	private array $modifications = [];

	/**
	 * Add a modification to the queue.
	 *
	 * @param callable $shouldModifyCallback
	 * @param callable $modifyCallback
	 */
	public function add( callable $shouldModifyCallback, callable $modifyCallback ): void {
		$this->modifications[] = [ $shouldModifyCallback, $modifyCallback ];
	}

	/**
	 * Apply all modifications to a fragment.
	 *
	 * @param string $htmlFragment
	 * @param bool $html5format
	 * @return string
	 */
	public function apply( string $htmlFragment, bool $html5format = true ): string {
		if ( !count( $this->modifications ) ) {
			return $htmlFragment;
		}

		return HtmlHelper::modifyElements(
			$htmlFragment,
			function ( SerializerNode $node ) {
				foreach ( $this->modifications as [ $shouldModify, $modify ] ) {
					if ( $shouldModify( $node ) ) {
						return true;
					}
				}
				return false;
			},
			function ( SerializerNode $node ) {
				$modified = null;
				foreach ( $this->modifications as [ $shouldModify, $modify ] ) {
					if ( $shouldModify( $node ) ) {
						if ( !$modified ) {
							$modified = $modify( $node );
						} else {
							// Ideally we would support matching mulitple modifiers, but as a modifier
							// can return a string but has to be passed a SerizliazerNode, this is
							// not trivial.
							throw new LogicException( 'Node matches multiple modifiers.' );
						}
					}
				}
				return $modified ?? $node;
			},
			$html5format
		);
	}
}
