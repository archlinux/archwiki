<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\TexVC\Nodes;

class Fun2 extends TexNode {

	/** @var string */
	protected $fname;
	/** @var TexNode */
	protected $arg1;
	/** @var TexNode */
	protected $arg2;

	public function __construct( string $fname, TexNode $arg1, TexNode $arg2 ) {
		parent::__construct( $fname, $arg1, $arg2 );
		$this->fname = $fname;
		$this->arg1 = $arg1;
		$this->arg2 = $arg2;
	}

	public function inCurlies() {
		return $this->render();
	}

	public function render() {
		return '{' . $this->fname . ' ' . $this->arg1->inCurlies() . $this->arg2->inCurlies() . '}';
	}

	public function extractIdentifiers( $args = null ) {
		if ( $args == null ) {
			$args = [ $this->arg1, $this->arg2 ];
		}
		return parent::extractIdentifiers( $args );
	}

	public function name() {
		return 'FUN2';
	}
}

/**
 * 'use strict';
 * const TexNode = require('./texnode');
 * const assert = require('assert');
 *
 * class Fun2 extends TexNode {
 * constructor(fname, arg1, arg2) {
 * assert.strictEqual(
 * arguments.length,
 * 3,
 * 'Incorrect number or arguments');
 * assert.ok(
 * (fname instanceof String || typeof fname === 'string') &&
 * arg1 instanceof TexNode,
 * arg2 instanceof TexNode,
 * 'Incorrect argument type');
 * super(fname, arg1, arg2);
 * this.fname = fname;
 * this.arg1 = arg1;
 * this.arg2 = arg2;
 * }
 *
 * inCurlies() {
 * return this.render();
 * }
 *
 * render() {
 * return '{' + this.fname +
 * ' ' + this.arg1.inCurlies() +
 * this.arg2.inCurlies() + '}';
 * }
 *
 * extractIdentifiers(args = [this.arg1, this.arg2]) {
 * return super.extractIdentifiers(args);
 * }
 * get name() {
 * return 'FUN2';
 * }
 * }
 */
