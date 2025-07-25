<?php
namespace Wikimedia\RemexHtml\TreeBuilder;

use Wikimedia\RemexHtml\HTMLData;

/**
 * An implementation of the "stack of open elements" which, unlike CachingStack,
 * iterates through the stack in order to answer queries about which elements
 * are in scope. This is presumably faster for best case input.
 */
class SimpleStack extends Stack {
	/** @var array<int,Element> */
	private $elements;

	/**
	 * A 2-d array giving the element types which break a scope region for the
	 * default scope, i.e. the one for phrases of the form "has an X element
	 * in scope".
	 */
	private const DEFAULT_SCOPE = [
		HTMLData::NS_HTML => [
			'applet' => true,
			'caption' => true,
			'html' => true,
			'table' => true,
			'td' => true,
			'th' => true,
			'marquee' => true,
			'object' => true,
			'template' => true,
		],
		HTMLData::NS_MATHML => [
			'mi' => true,
			'mo' => true,
			'mn' => true,
			'ms' => true,
			'mtext' => true,
			'annotation-xml' => true,
		],
		HTMLData::NS_SVG => [
			'foreignObject' => true,
			'desc' => true,
			'title' => true,
		],
	];

	/**
	 * The element types which break the table scope.
	 */
	private const TABLE_SCOPE = [
		HTMLData::NS_HTML => [
			'html' => true,
			'table' => true,
			'template' => true,
		]
	];

	/**
	 * The element types which break the list scope. This is lazy-initialised.
	 * @var array<string,array<string,bool>>
	 */
	private static $listScope;

	/**
	 * The element types which break the button scope. This is lazy-initialised.
	 * @var array<string,array<string,bool>>
	 */
	private static $buttonScope;

	/** @inheritDoc */
	public function push( Element $elt ) {
		$n = count( $this->elements );
		$this->elements[$n] = $elt;
		$this->current = $elt;
		$elt->stackIndex = $n;
	}

	/** @inheritDoc */
	public function pop() {
		$elt = array_pop( $this->elements );
		$elt->stackIndex = null;
		$n = count( $this->elements );
		$this->current = $n ? $this->elements[$n - 1] : null;
		return $elt;
	}

	/** @inheritDoc */
	public function replace( Element $oldElt, Element $elt ) {
		$idx = $oldElt->stackIndex;
		$this->elements[$idx] = $elt;
		$oldElt->stackIndex = null;
		$elt->stackIndex = $idx;
		if ( $idx === count( $this->elements ) - 1 ) {
			$this->current = $elt;
		}
	}

	/** @inheritDoc */
	public function remove( Element $elt ) {
		$eltIndex = $elt->stackIndex;
		$n = count( $this->elements );
		for ( $i = $eltIndex + 1; $i < $n; $i++ ) {
			$this->elements[$i]->stackIndex--;
		}
		$elt->stackIndex = null;
	}

	/** @inheritDoc */
	public function isInScope( $name ) {
		return $this->isInSpecificScope( $name, self::DEFAULT_SCOPE );
	}

	/** @inheritDoc */
	public function isElementInScope( Element $elt ) {
		for ( $i = count( $this->elements ) - 1; $i >= 0; $i-- ) {
			$node = $this->elements[$i];
			if ( $node === $elt ) {
				return true;
			}
			if ( isset( self::DEFAULT_SCOPE[$node->namespace][$node->name] ) ) {
				return false;
			}
		}
		return false;
	}

	/** @inheritDoc */
	public function isOneOfSetInScope( $names ) {
		for ( $i = count( $this->elements ) - 1; $i >= 0; $i-- ) {
			$node = $this->elements[$i];
			if ( $node->namespace === HTMLData::NS_HTML && isset( $names[$node->name] ) ) {
				return true;
			}
			if ( isset( self::DEFAULT_SCOPE[$node->namespace][$node->name] ) ) {
				return false;
			}
		}
		return false;
	}

	/** @inheritDoc */
	public function isInListScope( $name ) {
		if ( self::$listScope === null ) {
			self::$listScope = self::DEFAULT_SCOPE;
			self::$listScope[HTMLData::NS_HTML] += [
				'ol' => true,
				'li' => true
			];
		}
		return $this->isInSpecificScope( $name, self::$listScope );
	}

	/** @inheritDoc */
	public function isInButtonScope( $name ) {
		if ( self::$buttonScope === null ) {
			self::$buttonScope = self::DEFAULT_SCOPE;
			self::$buttonScope[HTMLData::NS_HTML]['button'] = true;
		}
		return $this->isInSpecificScope( $name, self::$buttonScope );
	}

	/** @inheritDoc */
	public function isInTableScope( $name ) {
		return $this->isInSpecificScope( $name, self::TABLE_SCOPE );
	}

	/** @inheritDoc */
	public function isInSelectScope( $name ) {
		for ( $i = count( $this->elements ) - 1; $i >= 0; $i-- ) {
			$node = $this->elements[$i];
			if ( $node->namespace === HTMLData::NS_HTML && $node->name === $name ) {
				return true;
			}
			if ( $node->namespace !== HTMLData::NS_HTML ) {
				return false;
			}
			if ( $node->name !== 'optgroup' && $node->name !== 'option' ) {
				return false;
			}
		}
		return false;
	}

	private function isInSpecificScope( string $name, array $set ): bool {
		for ( $i = count( $this->elements ) - 1; $i >= 0; $i-- ) {
			$node = $this->elements[$i];
			if ( $node->namespace === HTMLData::NS_HTML && $node->name === $name ) {
				return true;
			}
			if ( isset( $set[$node->namespace][$node->name] ) ) {
				return false;
			}
		}
		return false;
	}

	/** @inheritDoc */
	public function item( $idx ) {
		return $this->elements[$idx];
	}

	/** @inheritDoc */
	public function length() {
		return count( $this->elements );
	}

	/** @inheritDoc */
	public function hasTemplate() {
		foreach ( $this->elements as $elt ) {
			if ( $elt->namespace === HTMLData::NS_HTML && $elt->name === 'template' ) {
				return true;
			}
		}
		return false;
	}
}
