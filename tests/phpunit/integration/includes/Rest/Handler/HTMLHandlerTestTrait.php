<?php

namespace MediaWiki\Tests\Rest\Handler;

use Exception;
use HashBagOStuff;
use IContextSource;
use MediaWiki\Block\BlockErrorFormatter;
use MediaWiki\Edit\ParsoidOutputStash;
use MediaWiki\Edit\ParsoidRenderID;
use MediaWiki\Edit\SimpleParsoidOutputStash;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\UserAuthority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Rest\RequestData;
use MediaWiki\User\User;
use WikiPage;

/**
 * This trait is used in PageHTMLHandlerTest.php & RevisionHTMLHandlerTest.php
 * to construct requests and perform stashing for the Parsoid Output stash feature.
 */
trait HTMLHandlerTestTrait {

	private $parsoidOutputStash = null;

	private function getParsoidOutputStash(): ParsoidOutputStash {
		if ( !$this->parsoidOutputStash ) {
			$chFactory = $this->getServiceContainer()->getContentHandlerFactory();
			$this->parsoidOutputStash = new SimpleParsoidOutputStash( $chFactory, new HashBagOStuff(), 120 );
		}
		return $this->parsoidOutputStash;
	}

	private function getAuthority(): Authority {
		$services = $this->getServiceContainer();
		return new UserAuthority(
		// We need a newly created user because we want IP and newbie to apply.
			new User(),
			new FauxRequest(),
			$this->createMock( IContextSource::class ),
			$services->getPermissionManager(),
			$services->getRateLimiter(),
			$this->createMock( BlockErrorFormatter::class )
		);
	}

	/**
	 * @param WikiPage $page
	 * @param array $queryParams
	 * @param array $config
	 *
	 * @return array
	 * @throws Exception
	 */
	private function executePageHTMLRequest(
		WikiPage $page,
		array $queryParams = [],
		array $config = [],
		Authority $authority = null
	): array {
		$handler = $this->newHandler();
		$request = new RequestData( [
			'pathParams' => [ 'title' => $page->getTitle()->getPrefixedDBkey() ],
			'queryParams' => $queryParams,
		] );
		$result = $this->executeHandler(
			$handler,
			$request,
			$config + [ 'format' => 'html' ],
			[],
			[],
			[],
			$authority
		);
		$etag = $result->getHeaderLine( 'ETag' );
		$stashKey = ParsoidRenderID::newFromETag( $etag );

		return [ $result->getBody()->getContents(), $etag, $stashKey ];
	}

	/**
	 * @param int $revId
	 * @param array $queryParams
	 * @param array $config
	 *
	 * @return array
	 * @throws Exception
	 */
	private function executeRevisionHTMLRequest(
		int $revId,
		array $queryParams = [],
		array $config = [],
		Authority $authority = null
	): array {
		$handler = $this->newHandler();
		$request = new RequestData( [
			'pathParams' => [ 'id' => $revId ],
			'queryParams' => $queryParams,
		] );
		$result = $this->executeHandler(
			$handler,
			$request,
			$config + [ 'format' => 'html' ],
			[],
			[],
			[],
			$authority
		);
		$etag = $result->getHeaderLine( 'ETag' );
		$stashKey = ParsoidRenderID::newFromETag( $etag );

		return [ $result->getBody()->getContents(), $etag, $stashKey ];
	}
}
