<?php

namespace MediaWiki\Extension\Math\Tests;

use MockHttpTrait;

trait MathMockHttpTrait {
	use MockHttpTrait;

	protected function setupBadMathRestBaseMockHttp() {
		$this->installMockHttp(
			$this->makeFakeHttpMultiClient( [ [
				'code' => 400,
				'body' => file_get_contents( __DIR__ . '/InputCheck/data/restbase/fail.json' ),
			] ] )
		);
	}

	protected function setupSyntaxErrorRestBaseMockHttp() {
		$this->installMockHttp(
			$this->makeFakeHttpMultiClient( [ [
				'code' => 400,
				'body' => file_get_contents( __DIR__ . '/InputCheck/data/restbase/syntax_error.json' ),
			] ] )
		);
	}

	protected function setupGoodMathRestBaseMockHttp( bool $withSvg = false ) {
		$requests = [
			$this->makeFakeHttpMultiClient( [ [
				'code' => 200,
				'headers' => [
					'x-resource-location' => 'RESOURCE_LOCATION',
				],
				'body' => file_get_contents( __DIR__ . '/InputCheck/data/restbase/sinx.json' ),
			] ] ),
			$this->makeFakeHttpMultiClient( [ file_get_contents( __DIR__ . '/data/restbase/sinx.mml' ), ] ),
		];
		if ( $withSvg ) {
			$requests[] = $this->makeFakeHttpMultiClient( [ 'SVGSVSVG', ] );
		}
		$this->installMockHttp( $requests );
	}

	protected function setupGoodChemRestBaseMockHttp() {
		$this->installMockHttp( [
			$this->makeFakeHttpMultiClient( [ [
				'code' => 200,
				'headers' => [
					'x-resource-location' => 'RESOURCE_LOCATION',
				],
				'body' => file_get_contents( __DIR__ . '/InputCheck/data/restbase/chem.json' ),
			] ] ),
			$this->makeFakeHttpMultiClient( [ file_get_contents( __DIR__ . '/data/restbase/h2o.mml' ), ] ),
		] );
	}
}
