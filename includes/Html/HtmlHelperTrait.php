<?php

namespace MediaWiki\Html;

use Wikimedia\Assert\Assert;
use Wikimedia\RemexHtml\Serializer\SerializerNode;

/**
 * Internal helper trait for HtmlHelper::modifyHtml.
 *
 * This is designed to extend a HtmlFormatter.
 *
 * @phan-file-suppress PhanTraitParentReference
 */
trait HtmlHelperTrait {
	/** @var callable */
	private $shouldModifyCallback;

	/** @var callable */
	private $modifyCallback;

	public function __construct( $options, callable $shouldModifyCallback, callable $modifyCallback ) {
		parent::__construct( $options );
		$this->shouldModifyCallback = $shouldModifyCallback;
		$this->modifyCallback = $modifyCallback;
	}

	public function element( SerializerNode $parent, SerializerNode $node, $contents ) {
		if ( ( $this->shouldModifyCallback )( $node ) ) {
			$node = clone $node;
			$node->attrs = clone $node->attrs;
			$newNode = ( $this->modifyCallback )( $node );
			Assert::parameterType( SerializerNode::class, $newNode, 'return value' );
			return parent::element( $parent, $newNode, $contents );
		} else {
			return parent::element( $parent, $node, $contents );
		}
	}

	public function startDocument( $fragmentNamespace, $fragmentName ) {
		return '';
	}
}
