<?php
namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

use DOMException;
use MediaWiki\Extension\Math\Math;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\Tag;

class MMLbase {
	private string $name;
	private array $attributes;
	protected ?VisitorFactory $visitorFactory = null;
	/** @var array<MMLbase|string> */
	protected array $children = [];

	/**
	 * Constructor for MML element node
	 *
	 * @param string $name The element tag name (e.g., 'msubsup', 'msqrt')
	 * @param string $texclass TeX class name
	 * @param array $attributes Associative array of element attributes
	 * @param MMLbase|string|null ...$children MMLbase child elements (null values are allowed for placeholder values)
	 */
	public function __construct( string $name, string $texclass = '', array $attributes = [], ...$children ) {
		$this->name = $name;
		$this->attributes = $attributes;
		$this->children = $children;
		if ( $texclass !== '' ) {
			$this->attributes[ TAG::CLASSTAG ] = $texclass;
		}
	}

	/**
	 * Add child node to current children
	 * @param MMLbase|string|null ...$node
	 */
	public function addChild( ...$node ): void {
		foreach ( $node as $n ) {
			$this->children[] = $n;
		}
	}

	/**
	 * Get name children from the current element
	 * @return MMLbase[]
	 */
	public function getChildren(): array {
		return $this->children;
	}

	/**
	 * True if the current object is empty (no children and not a leaf)
	 * @return bool
	 */
	public function isEmpty(): bool {
		if ( $this->hasChildren() || $this instanceof MMLleaf ) {
			return false;
		}
		return true;
	}

	/**
	 * True if the current object has child objects
	 */
	public function hasChildren(): bool {
		return count( $this->children ) !== 0;
	}

	/**
	 * Get the current VisitorFactory or get from services: Math::getVisitorFactory()
	 * @return VisitorFactory
	 */
	protected function getVisitorFactory() {
		if ( !$this->visitorFactory ) {
			$this->visitorFactory = Math::getVisitorFactory();
		}
		return $this->visitorFactory;
	}

	/**
	 * Set VisitorFactory for the current element
	 */
	public function setVisitorFactory( VisitorFactory $visitorFactory ) {
		$this->visitorFactory = $visitorFactory;
	}

	/**
	 * Get the name (mi, mo, ...) from the current element
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Get all attributes from the current element
	 * @return array
	 */
	public function getAttributes() {
		return $this->attributes;
	}

	/**
	 * Accept a visitor to process this node
	 * @param MMLVisitor $visitor
	 */
	public function accept( MMLVisitor $visitor ) {
		$visitor->visit( $this );
	}

	/**
	 * Get string presentation of current element
	 * @throws DOMException
	 */
	public function __toString(): string {
		$visitor = $this->getVisitorFactory()->createVisitor();
		$visitor->visit( $this );
		return $visitor->getHTML();
	}

}
