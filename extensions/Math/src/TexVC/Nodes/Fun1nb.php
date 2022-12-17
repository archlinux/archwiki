<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\TexVC\Nodes;

class Fun1nb extends Fun1 {

	public function name() {
		return 'FUN1nb';
	}

	public function inCurlies() {
		return '{' . $this->render() . '}';
	}

	public function render() {
		return $this->fname . ' ' . $this->arg->inCurlies() . ' ';
	}
}
