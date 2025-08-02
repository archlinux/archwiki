<?php
namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

abstract class MMLleaf extends MMLbase {
	protected string $text;

	public function __construct(
		string $name,
		string $texclass = '',
		array $attributes = [],
		string $text = ''
	) {
		parent::__construct( $name, $texclass, $attributes );
		$this->text = $text;
	}

	/**
	 * Get text
	 * @return string
	 */
	public function getText(): string {
		return $this->text;
	}
}
