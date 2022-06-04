<?php

namespace MediaWiki\Extension\Math\InputCheck;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Http\HttpRequestFactory;
use Psr\Log\LoggerInterface;
use WANObjectCache;

class InputCheckFactory {

	public const CONSTRUCTOR_OPTIONS = [
		'MathMathMLUrl',
		'MathLaTeXMLTimeout',
	];
	/** @var string */
	private $url;
	/** @var int */
	private $timeout;
	/** @var WANObjectCache */
	private $cache;
	/** @var HttpRequestFactory */
	private $httpFactory;
	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param ServiceOptions $options
	 * @param WANObjectCache $cache
	 * @param HttpRequestFactory $httpFactory
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		ServiceOptions $options,
		WANObjectCache $cache,
		HttpRequestFactory $httpFactory,
		LoggerInterface $logger
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->url = $options->get( 'MathMathMLUrl' );
		$this->timeout = $options->get( 'MathLaTeXMLTimeout' );
		$this->cache = $cache;
		$this->httpFactory = $httpFactory;
		$this->logger = $logger;
	}

	/**
	 * @param string $input
	 * @param string $type
	 * @return MathoidChecker
	 */
	public function newMathoidChecker( string $input, string $type ): MathoidChecker {
		return new MathoidChecker(
			$this->cache,
			$this->httpFactory,
			$this->logger,
			$this->url,
			$this->timeout,
			$input,
			$type
		);
	}
}
