<?php

namespace Wikimedia\RemexHtml\DOM;

use Wikimedia\RemexHtml\HTMLData;
use Wikimedia\RemexHtml\Tokenizer\Attribute;
use Wikimedia\RemexHtml\Tokenizer\Attributes;
use Wikimedia\RemexHtml\TreeBuilder\Element;
use Wikimedia\RemexHtml\TreeBuilder\TreeBuilder;
use Wikimedia\RemexHtml\TreeBuilder\TreeHandler;

/**
 * A TreeHandler which constructs a DOMDocument.
 *
 * Note that this class permits third-party `DOMImplementation`s
 * (documents other than `\DOMDocument`, nodes other than `\DOMNode`,
 * etc) and so no enforced PHP type hints are used which name these
 * classes directly.  For the sake of static type checking, the
 * types *in comments* are given as if the standard PHP `\DOM*`
 * classes are being used but at runtime everything is duck-typed.
 */
class DOMBuilder implements TreeHandler {

	/** @var string|null The name of the input document type */
	public $doctypeName;

	/** @var string|null The public ID */
	public $public;

	/** @var string|null The system ID */
	public $system;

	/**
	 * @var int The quirks mode. May be either TreeBuilder::NO_QUIRKS,
	 *   TreeBuilder::LIMITED_QUIRKS or TreeBuilder::QUIRKS to indicate
	 *   no-quirks mode, limited-quirks mode or quirks mode respectively.
	 */
	public $quirks;

	/** @var \DOMDocument */
	private $doc;

	/** @var callable|null */
	private $errorCallback;

	private bool $suppressHtmlNamespace;

	private bool $createElementSetsNullNS;

	private bool $suppressIdAttribute;

	private bool $setAttributeWorkarounds;

	/** @var string 'none', 'coerce', or 'parser' */
	private string $coercionWorkaround;

	/** @var callable(string):\DOMDocument */
	private $htmlParser;

	/** @var \DOMImplementation */
	private $domImplementation;

	/** @var class-string */
	private $domExceptionClass;

	/** @var bool */
	private $isFragment;

	/** @var bool */
	private $coerced = false;

	/**
	 * @param array $options An associative array of options:
	 *   - errorCallback : A function which is called on parse errors
	 *   - domImplementationClass : The string name of the DOMImplementation
	 *     class to use.  Defaults to `\DOMImplementation::class`; you can
	 *     pass `\Dom\Implementation::class` on PHP 8.4, or use a third-party
	 *     DOM implementation by passing an alternative class name here.
	 *   - domImplementation : The DOMImplementation object to use.  If this
	 *     parameter is missing or null, a new DOMImplementation object will
	 *     be constructed using the `domImplementationClass` option value.
	 *   - domExceptionClass : The string name of the DOMException
	 *     class to use.  Defaults to `\DOMException::class`; you can pass
	 *     `\Dom\Exception::class` on PHP 8.4, or use a third-party
	 *     DOM implementation by passing an alternative class name here.
	 *   - suppressHtmlNamespace : omit the namespace when creating HTML
	 *     elements. This corresponds to the PHP 8.4 `Dom\HTML_NO_DEFAULT_NS`
	 *     option to `Dom\HTMLDocument::createFromString()`.  This
	 *     defaults to `false` currently, but will default to `true`
	 *     when using `\DOMImplementation::class` in the next release.
	 *     Note that tag names with colons (like `<mw:section>`) won't
	 *     be properly parsed with `\DOMImplementation::class` unless
	 *     `suppressHtmlNamespace` is true, while the newer PHP 8.4
	 *     `\Dom\Implementation::class` will be forced to use a slow
	 *     workaround for tags containing colons if
	 *     `suppressHtmlNamespace` is `true`.
	 *   - suppressIdAttribute : don't call the nonstandard
	 *     `DOMElement::setIdAttribute()`/`Dom\Element::setIdAttribute()`
	 *     method while constructing elements.  False by default when using
	 *     `\DOMImplementation` or `\Dom\Implementation`, since this method
	 *     is needed for efficient `::getElementById()` calls in PHP.
	 *     Set to true if you are using a W3C spec-compliant DOM Implementation
	 *     and wish to avoid the use of nonstandard calls.
	 *   - coercionWorkaround : As discussed in T393922#10837089, the upstream
	 *     DOM spec allows fewer name characters for element, attribute, and
	 *     doctype names than permitted by the HTML parsing spec.  This option
	 *     can have the string value `'none'` which (re)throws a
	 *     DOMException when these characters are encountered during parsing,
	 *     or `'coerce'` to remap the names and set a flag; see
	 *     `::isCoerced()`.  A third option is to `'parser'` which will use
	 *     "another" HTML parser to create elements/attributes/doctypes with
	 *     the "illegal" names.  The default is to use `'parser'` in PHP 8.4+
	 *     and `'coerce'` otherwise.
	 *   - htmlParser: An optional callable with the signature
	 *     `string => Document`, used to implement the `'parser'` option to
	 *     `coercionWorkaround`, and also to create tags with names
	 *     containing colons when `suppressHtmlNamespace` is true and
	 *     `\Dom\Implementation::class` is used.  The default is `null`,
	 *     which uses `Dom\HTMLDocument::loadFromString` when available.
	 */
	public function __construct( $options = [] ) {
		$options += [
			'errorCallback' => null,
			'domImplementation' => null,
			'domImplementationClass' => \DOMImplementation::class,
			'domExceptionClass' => \DOMException::class,
		];
		$this->errorCallback = $options['errorCallback'];
		$this->domImplementation = $options['domImplementation'] ??
			new $options['domImplementationClass'];
		$this->domExceptionClass = $options['domExceptionClass'];

		$isOldNative = $this->domImplementation instanceof \DOMImplementation;
		$isNewNative = is_a( $this->domImplementation, '\Dom\Implementation' );

		$this->suppressHtmlNamespace = $options['suppressHtmlNamespace'] ??
			$isOldNative;
		$this->createElementSetsNullNS = $isOldNative;
		$this->suppressIdAttribute = $options['suppressIdAttribute'] ??
			!( $isOldNative || $isNewNative );
		$this->setAttributeWorkarounds = $isOldNative;
		if ( isset( $options['coercionWorkaround'] ) ) {
			$this->coercionWorkaround = $options['coercionWorkaround'];
		} elseif ( $isNewNative ) {
			$this->coercionWorkaround = 'parser';
		} else {
			$this->coercionWorkaround = 'coerce';
		}
		$this->htmlParser = $options['htmlParser'] ?? null;
	}

	private function rethrowIfNotDomException( \Throwable $t ) {
		if ( is_a( $t, $this->domExceptionClass, false ) ) {
			return;
		}
		// @phan-suppress-next-line PhanThrowTypeAbsent
		throw $t;
	}

	/**
	 * Get the constructed document or document fragment. In the fragment case,
	 * a DOMElement is returned, and the caller is expected to extract its
	 * inner contents, ignoring the wrapping element. This convention is
	 * convenient because the wrapping element gives libxml somewhere to put
	 * its namespace declarations. If we copied the children into a
	 * DOMDocumentFragment, libxml would invent new prefixes for the orphaned
	 * namespaces.
	 *
	 * @return \DOMNode
	 */
	public function getFragment() {
		if ( $this->isFragment ) {
			return $this->doc->documentElement;
		} else {
			return $this->doc;
		}
	}

	/**
	 * Returns true if the document was coerced due to libxml limitations. We
	 * follow HTML 5.1 § 8.2.7 "Coercing an HTML DOM into an infoset".
	 *
	 * @return bool
	 */
	public function isCoerced() {
		return $this->coerced;
	}

	/** @inheritDoc */
	public function startDocument( $fragmentNamespace, $fragmentName ) {
		$this->isFragment = $fragmentNamespace !== null;
		$this->doc = $this->createDocument();
	}

	/**
	 * @param string|null $doctypeName
	 * @param string|null $public
	 * @param string|null $system
	 * @return \DOMDocument
	 * @suppress PhanTypeMismatchArgumentInternalReal
	 *   Null args to DOMImplementation::createDocument
	 */
	protected function createDocument(
		?string $doctypeName = null,
		?string $public = null,
		?string $system = null
	) {
		$impl = $this->domImplementation;
		if ( $doctypeName === '' && $this->coercionWorkaround === 'coerce' ) {
			$this->coerced = true;
			$doc = $impl->createDocument( null, '' );
		} elseif ( $doctypeName === null ) {
			$doc = $impl->createDocument( null, '' );
		} elseif (
			$doctypeName === 'html' && ( !$public ) && ( !$system ) &&
			method_exists( $impl, 'createHtmlDocument' )
		) {
			// @phan-suppress-next-line PhanUndeclaredMethod
			$doc = $impl->createHtmlDocument();
			// Remove all nodes but the doctype node (the first)
			while ( $doc->lastChild && $doc->lastChild->nodeType !== 10 ) {
				$doc->removeChild( $doc->lastChild );
			}
		} else {
			$doctype = $this->maybeCoerce(
				$doctypeName,
				static fn ( $name ) => $impl->createDocumentType( $doctypeName, $public ?? '', $system ?? '' ),
				fn () => $this->parserDoctypeWorkaround( $doctypeName, $public ?? '', $system ?? '' )
			);
			$doc = $impl->createDocument( null, '', $doctype );
		}
		$doc->encoding = 'UTF-8';
		return $doc;
	}

	/** @inheritDoc */
	public function endDocument( $pos ) {
	}

	/**
	 * @param int $preposition
	 * @param Element $refElement
	 * @param \DOMNode $node
	 */
	protected function insertNode( $preposition, $refElement, $node ) {
		if ( $preposition === TreeBuilder::ROOT ) {
			$parent = $this->doc;
			$refNode = null;
		} elseif ( $preposition === TreeBuilder::BEFORE ) {
			$parent = $refElement->userData->parentNode;
			$refNode = $refElement->userData;
		} else {
			$parent = $refElement->userData;
			$refNode = null;
		}
		$parent->insertBefore( $node, $refNode );
	}

	/**
	 * Helper function to try to execute a function, coercing the given
	 * name and trying again if it throws a DOMException.
	 * @phan-template T
	 * @param string $name The name to possibly coerce
	 * @param callable(string):T $func The operation we wish to perform
	 * @param ?callable():T $parserWorkaround An alternative method to
	 *  perform this operation using an HTML parser.
	 * @return T returns the value returned by $func
	 */
	private function maybeCoerce( string $name, callable $func, ?callable $parserWorkaround ) {
		if ( $this->coercionWorkaround === 'none' ) {
			return $func( $name );
		}
		try {
			return $func( $name );
		} catch ( \Throwable $e ) {
			$this->rethrowIfNotDomException( $e );
		}
		if ( $this->coercionWorkaround === 'coerce' || $parserWorkaround === null ) {
			return $func( $this->coerceName( $name ) );
		}
		// Alternative implementation using an HTML parser.
		return $parserWorkaround();
	}

	/**
	 * Helper function to set the value of an attribute on the node,
	 * working around various PHP bugs in the process.
	 * @param \DOMElement $node
	 * @param Attribute $attr
	 * @param bool $needRetval Whether we need the previous value returned
	 * @return ?string The old value of the attribute
	 */
	private function setAttribute( $node, Attribute $attr, bool $needRetval = false ): ?string {
		if ( $this->setAttributeWorkarounds ) {
			return $this->setAttributeWorkaround( $node, $attr, $needRetval );
		}
		return $this->maybeCoerce(
			$attr->qualifiedName,
			static function ( $name ) use ( $node, $attr, $needRetval ) {
				$oldValue = $needRetval ? $node->getAttribute( $name ) : null;
				if ( $attr->namespaceURI === null ) {
					$node->setAttribute( $name, $attr->value );
				} else {
					$node->setAttributeNS( $attr->namespaceURI, $name, $attr->value );
				}
				return $oldValue;
			},
			fn () => $this->parserAttrWorkaround( $node, $attr )
		);
	}

	/**
	 * @return \DOMNode
	 */
	protected function createNode( Element $element ) {
		$isHtmlNS = ( $element->namespace === HTMLData::NS_HTML );
		if ( $this->createElementSetsNullNS ) {
			$useCreateElement = $this->suppressHtmlNamespace && $isHtmlNS;
			if ( str_contains( $element->name, ':' ) ) {
				// With \DOMImplementation, we have to use createElement
				// for elements with colons in their names, even though
				// this will leave them with a null namespace.
				$useCreateElement = true;
			}
		} elseif ( $this->suppressHtmlNamespace && $isHtmlNS ) {
			$useCreateElement = false;
		} else {
			// Use createElement for HTML namespace elements, because otherwise
			// the localName will get split on a ':' in $element->name.
			$useCreateElement = $isHtmlNS;
		}
		if ( $useCreateElement ) {
			$node = $this->maybeCoerce(
				$element->name,
				fn ( $name ) => $this->doc->createElement( $name ),
				fn () => $this->parserElementWorkaround( $element->name )
			);
		} elseif ( $this->createElementSetsNullNS || !str_contains( $element->name, ':' ) ) {
			$namespace = ( $this->suppressHtmlNamespace && $isHtmlNS ) ? null :
				$element->namespace;
			$node = $this->maybeCoerce( $element->name, fn ( $name ) =>
				$this->doc->createElementNS( $namespace, $name ),
				null
			);
		} else {
			$node = $this->parserElementWorkaround( $element->name );
		}

		foreach ( $element->attrs->getObjects() as $attr ) {
			if ( $attr->namespaceURI === null &&
				!preg_match( '/[^-A-Za-z]/S', $attr->qualifiedName )
			) {
				// Fast path: no coercion or namespaces necessary
				$node->setAttribute( $attr->qualifiedName, $attr->value );
			} else {
				$this->setAttribute( $node, $attr );
			}
		}
		if ( ( !$this->suppressIdAttribute ) && $node->hasAttribute( 'id' ) ) {
			// This is a call to a non-standard DOM method required by PHP in
			// order to implement DOMDocument::getElementById() efficiently.
			$node->setIdAttribute( 'id', true );
		}
		$element->userData = $node;
		return $node;
	}

	/** @inheritDoc */
	public function characters( $preposition, $refElement, $text, $start, $length,
		$sourceStart, $sourceLength
	) {
		// Parse $preposition and $refElement as in self::insertNode()
		if ( $preposition === TreeBuilder::ROOT ) {
			$parent = $this->doc;
			$refNode = null;
		} elseif ( $preposition === TreeBuilder::BEFORE ) {
			$parent = $refElement->userData->parentNode;
			$refNode = $refElement->userData;
		} else {
			$parent = $refElement->userData;
			$refNode = null;
		}
		// https://html.spec.whatwg.org/#insert-a-character
		// If the adjusted insertion location is in a Document node, then
		// return.
		if ( $parent === $this->doc ) {
			return;
		}
		$data = substr( $text, $start, $length );
		// If there is a Text node immediately before the adjusted insertion
		// location, then append data to that Text node's data.
		if ( $refNode === null ) {
			$prev = $parent->lastChild;
		} else {
			/** @var \DOMNode $refNode */
			$prev = $refNode->previousSibling;
		}
		if ( $prev !== null && $prev->nodeType === XML_TEXT_NODE ) {
			'@phan-var \DOMCharacterData $prev'; /** @var \DOMCharacterData $prev */
			$prev->appendData( $data );
		} else {
			$node = $this->doc->createTextNode( $data );
			$this->insertNode( $preposition, $refElement, $node );
		}
	}

	/** @inheritDoc */
	public function insertElement( $preposition, $refElement, Element $element, $void,
		$sourceStart, $sourceLength
	) {
		if ( $element->userData ) {
			$node = $element->userData;
		} else {
			$node = $this->createNode( $element );
		}
		$this->insertNode( $preposition, $refElement, $node );
	}

	/** @inheritDoc */
	public function endTag( Element $element, $sourceStart, $sourceLength ) {
	}

	/** @inheritDoc */
	public function doctype( $name, $public, $system, $quirks, $sourceStart, $sourceLength ) {
		if ( !$this->doc->firstChild ) {
			$this->doc = $this->createDocument( $name, $public, $system );
		}
		$this->doctypeName = $name;
		$this->public = $public;
		$this->system = $system;
		$this->quirks = $quirks;
	}

	/** @inheritDoc */
	public function comment( $preposition, $refElement, $text, $sourceStart, $sourceLength ) {
		$node = $this->doc->createComment( $text );
		$this->insertNode( $preposition, $refElement, $node );
	}

	/** @inheritDoc */
	public function error( $text, $pos ) {
		if ( $this->errorCallback ) {
			( $this->errorCallback )( $text, $pos );
		}
	}

	/** @inheritDoc */
	public function mergeAttributes( Element $element, Attributes $attrs, $sourceStart ) {
		$node = $element->userData;
		'@phan-var \DOMElement $node'; /** @var \DOMElement $node */
		foreach ( $attrs->getObjects() as $name => $attr ) {
			// As noted in createNode(), we can't use hasAttribute() reliably
			// on some DOM implementations. However, we can use the return
			// value (eg, of setAttributeNode()) to reliably tell us the
			// *previous* value, and so we can use that to fix up the corner
			// cases.
			$oldValue = $this->setAttribute( $node, $attr, true );
			if ( $oldValue !== null ) {
				// Put it back how it was
				$a = clone $attr;
				$a->value = $oldValue;
				$this->setAttribute( $node, $a );
			}
		}
	}

	/** @inheritDoc */
	public function removeNode( Element $element, $sourceStart ) {
		$node = $element->userData;
		$node->parentNode->removeChild( $node );
	}

	/** @inheritDoc */
	public function reparentChildren( Element $element, Element $newParent, $sourceStart ) {
		$this->insertElement( TreeBuilder::UNDER, $element, $newParent, false, $sourceStart, 0 );
		$node = $element->userData;
		/** @var \DOMElement $newParentNode */
		$newParentNode = $newParent->userData;
		'@phan-var \DOMElement $newParentNode';
		while ( $node->firstChild !== $newParentNode ) {
			$firstChild = $node->firstChild;
			'@phan-var \DOMNode $firstChild';
			$newParentNode->appendChild( $firstChild );
		}
	}

	// SetAttribute workarounds, for \DOMDocument

	/**
	 * Implement various workarounds for ::setAttribute needed for
	 * \DOMDocument.
	 * @param \DOMElement $node
	 * @param Attribute $attr
	 * @param bool $needRetval
	 * @return ?string The previous value of the attribute, or null if none
	 */
	private function setAttributeWorkaround( $node, Attribute $attr, bool $needRetval ): ?string {
		if ( $attr->namespaceURI === null ) {
			return $this->maybeCoerce(
				$attr->localName,
				fn ( $name ) => $this->setAttributeNode(
					$node,
					$this->doc->createAttribute( $name ),
					$attr->value
				),
				fn () => $this->parserAttrWorkaround( $node, $attr )
			);
		} elseif ( $attr->qualifiedName === 'xmlns' ) {
			# T235295: \DOMDocument treats 'xmlns' as special
			$oldValue = $needRetval && $node->hasAttribute( $attr->qualifiedName ) ?
				$node->getAttribute( $attr->qualifiedName ) : null;
			$node->setAttributeNS(
				$attr->namespaceURI, $attr->qualifiedName, $attr->value
			);
			return $oldValue;
		} else {
			return $this->maybeCoerce( $attr->qualifiedName, fn ( $name ) =>
				$this->setAttributeNode(
					$node,
					$this->doc->createAttributeNS( $attr->namespaceURI, $name ),
					$attr->value
				),
				fn () => $this->parserAttrWorkaround( $node, $attr )
			);
		}
	}

	/**
	 * Helper function to set an attribute from an attribute node object.
	 * @param \DOMElement $node
	 * @param \DOMAttr $attrNode
	 * @param string $value The desired attribute value
	 * @return ?string The old value of the attribute
	 */
	private function setAttributeNode( $node, $attrNode, string $value ): ?string {
		// FIXME: this apparently works to create a prefixed localName
		// in the null namespace, but this is probably taking advantage
		// of a bug in PHP's DOM library, and screws up in various
		// interesting ways. For example, some attributes created in this
		// way can't be discovered via hasAttribute() or hasAttributeNS().
		if ( $this->domImplementation instanceof \DOMImplementation ) {
			// This ::appendChild trick only works in broken DOM
			// implementations! DOM standard says it should throw.
			$attrNode->appendChild(
				$this->doc->createTextNode( $value )
			);
		} else {
			$attrNode->value = $value;
		}
		$old = $node->setAttributeNode( $attrNode );
		return $old !== null ? $old->value : null;
	}

	// Name coercion workaround: remap name and set a flag

	/**
	 * Replace unsupported characters with a code of the form U123456.
	 *
	 * @param string $name
	 * @return string
	 */
	private function coerceName( $name ) {
		$coercedName = DOMUtils::coerceName( $name );
		if ( $name !== $coercedName ) {
			$this->coerced = true;
		}
		return $coercedName;
	}

	// Name coercion workaround using an HTML parser

	/**
	 * Use the PHP \Dom\HTMLDocument parser to create a doctype node which
	 * is impossible to create with DOM methods.
	 * @see https://github.com/whatwg/dom/issues/849#issuecomment-2877929861
	 * @param string $doctypeName
	 * @param string $public
	 * @param string $system
	 * @return \DOMDocumentType (or an implementation-defined type)
	 */
	private function parserDoctypeWorkaround(
		string $doctypeName, string $public, string $system
	) {
		// If $doctypeName doesn't match the Name production or QName
		// production a DOMException is thrown from
		// ::createDocumentType() according to the spec:
		// https://dom.spec.whatwg.org/#interface-domimplementation
		// "Real" implementations add a DocumentType element anyway
		// when parsing, but there is no standard DOM method which
		// would allow us the create the invalid DocumentType which
		// is typically used in this case.
		$html = "<!DOCTYPE {$doctypeName} {$public} {$system}>";
		$doc = $this->parseHtml( $html );
		return $doc->doctype;
	}

	/**
	 * Use HTML parser to create an Attribute which is impossible to
	 * create using normal DOM methods.
	 * @see https://github.com/whatwg/dom/issues/769
	 * @param \DOMElement $node (or an implementation-defined type)
	 * @param Attribute $attr
	 * @return ?string The previous value of the given attribute
	 */
	private function parserAttrWorkaround(
		$node, Attribute $attr
	): ?string {
		$html = "<div id=target><div {$attr->qualifiedName}=''></div></div>";
		$doc = $this->parseHtml( $html );
		// @phan-suppress-next-line PhanTypeArraySuspicious (works for \Dom\Element)
		$a = $doc->getElementById( 'target' )->firstChild
		   ->attributes[0]->cloneNode( true );
		$a = $this->doc->adoptNode( $a );
		$a->value = $attr->value;
		$oldNode = $node->setAttributeNode( $a );
		return $oldNode !== null ? $oldNode->value : null;
	}

	/**
	 * Use HTML parser to create an Element which is impossible to
	 * create using normal DOM methods.
	 * @see https://github.com/whatwg/dom/issues/849
	 * @param string $name The tag name to create
	 * @return \DOMElement (or an implementation-defined type)
	 */
	private function parserElementWorkaround( string $name ) {
		$html = "<div id=target><{$name}>";
		$doc = $this->parseHtml( $html );
		$el = $doc->getElementById( 'target' )->firstChild;
		'@phan-var \DOMElement $el';
		return $this->doc->adoptNode( $el );
	}

	/**
	 * @param string $html
	 * @return \DOMDocument (or an implementation-defined type)
	 */
	private function parseHtml( string $html ) {
		if ( $this->htmlParser ) {
			return ( $this->htmlParser )( $html );
		} else {
			$options = LIBXML_NOERROR |
				( $this->suppressHtmlNamespace ? constant( '\Dom\HTML_NO_DEFAULT_NS' ) : 0 );
			$doc = \Dom\HTMLDocument::createFromString( $html, $options );
			'@phan-var \DOMDocument $doc';
			return $doc;
		}
	}

}
