<?php

namespace MediaWiki\Extension\Nuke;

use MediaWiki\Config\Config;
use MediaWiki\Exception\MWException;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

class NukeAssociatedQueryBuilder {

	private IReadableDatabase $readableDatabase;
	private Config $config;
	private NamespaceInfo $namespaceInfo;

	/**
	 * @param IReadableDatabase $readableDatabase
	 * @param Config $config
	 * @param NamespaceInfo $namespaceInfo
	 */
	public function __construct(
		IReadableDatabase $readableDatabase,
		Config $config,
		NamespaceInfo $namespaceInfo
	) {
		$this->readableDatabase = $readableDatabase;
		$this->config = $config;
		$this->namespaceInfo = $namespaceInfo;
	}

	/**
	 * Get the associated talk pages that exist for each page in the provided
	 * list.
	 *
	 * @param Title[] $pages
	 * @return SelectQueryBuilder
	 */
	public function getTalkPages( array $pages ): SelectQueryBuilder {
		$byNamespace = [];

		// Sort the pages by their associated talk namespaces.
		foreach ( $pages as $page ) {
			try {
				$talkNamespace = $this->namespaceInfo->getTalk( $page->getNamespace() );
			} catch ( MWException $e ) {
				continue;
			}
			$byNamespace[ $talkNamespace ][] = $page->getDBkey();
		}

		// Get the talk pages that exist for each page.
		$dbr = $this->readableDatabase;
		$queryBuilder = $dbr->newSelectQueryBuilder()
			->select( [
				'talk.page_id',
				'talk.page_namespace',
				'talk.page_title',
				'actor_name',
				'subject_page_id' => 'subject.page_id',
			] )
			->distinct()
			->from( 'page', 'talk' )
			->join( 'revision', 'first', [
				'first.rev_id=talk.page_latest',
				'first.rev_parent_id=0'
			] )
			->join( 'actor', null, 'actor_id=first.rev_actor' )
			// Self-join to identify the subject page
			->join( 'page', 'subject', [
				'subject.page_title=talk.page_title',
				// Talk namespaces are always 1 greater than the subject namespace.
				'subject.page_namespace=(talk.page_namespace - 1)'
			] )
			->setMaxExecutionTime(
				$this->config->get( MainConfigNames::MaxExecutionTimeForExpensiveQueries )
			);
		$conditions = [];
		foreach ( $byNamespace as $talkNamespace => $names ) {
			$conditions[] = $dbr->andExpr( [
				$dbr->expr( 'talk.page_namespace', '=', $talkNamespace ),
				$dbr->expr( 'talk.page_title', '=', $names ),
			] );
		}
		$queryBuilder->where( [
			$dbr->orExpr( $conditions )
		] );
		return $queryBuilder;
	}

	public function getRedirectPages( array $pages ): SelectQueryBuilder {
		$byNamespace = [];

		// Sort the pages by their namespaces.
		foreach ( $pages as $page ) {
			$byNamespace[ $page->getNamespace() ][] = $page->getDBkey();
		}

		$dbr = $this->readableDatabase;
		$queryBuilder = $dbr->newSelectQueryBuilder()
			->select( [
				'rdpage.page_id',
				'rdpage.page_namespace',
				'rdpage.page_title',
				'actor_name',
				'target_page_id' => 'target.page_id'
			] )
			->distinct()
			->from( 'redirect' )
			->join( 'page', 'rdpage', 'rd_from=rdpage.page_id' )
			->join( 'revision', 'first', [
				'first.rev_id=rdpage.page_latest',
				'first.rev_parent_id=0'
			] )
			->join( 'actor', null, 'actor_id=first.rev_actor' )
			// Self-join to identify the target page
			->join( 'page', 'target', [
				'target.page_title=rd_title',
				'target.page_namespace=rd_namespace'
			] );

		$conditions = [];
		foreach ( $byNamespace as $namespace => $names ) {
			$conditions[] = $dbr->andExpr( [
				$dbr->expr( 'rd_namespace', '=', $namespace ),
				$dbr->expr( 'rd_title', '=', $names ),
			] );
		}
		$queryBuilder->where( $dbr->orExpr( $conditions ) );
		$queryBuilder->andWhere( $dbr->orExpr( [
			$dbr->expr( 'rd_interwiki', '=', null ),
			$dbr->expr( 'rd_interwiki', '=', "" )
		] ) );

		return $queryBuilder->setMaxExecutionTime(
			$this->config->get( MainConfigNames::MaxExecutionTimeForExpensiveQueries )
		);
	}

}
