<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use MediaWiki\Extension\AbuseFilter\Filter\Filter;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Filter\LastEditInfo;
use MediaWiki\Extension\AbuseFilter\Filter\Specs;
use TestUser;
use Wikimedia\Rdbms\IDatabase;

trait FilterFromSpecsTestTrait {
	/**
	 * @return array Default values for the filter created by {@link self::getFilterFromSpecs}
	 */
	private function getDefaultSpecs(): array {
		return [
			'rules' => '/**/',
			'lastEditor' => $this->getTestUser()->getUserIdentity(),
			'lastEditTimestamp' => '20190826000000',
			'enabled' => 1,
			'comments' => '',
			'name' => 'Mock filter',
			'privacy' => Flags::FILTER_PUBLIC,
			'hitCount' => 0,
			'throttled' => 0,
			'deleted' => 0,
			'actions' => [],
			'global' => 0,
			'group' => 'default',
		];
	}

	/**
	 * Creates a Filter object from the specified specifications.
	 * Intended for use when creating a test filter in the DB using {@link FilterStore::saveFilter}.
	 *
	 * @param array $filterSpecs
	 * @return Filter
	 */
	private function getFilterFromSpecs( array $filterSpecs ): Filter {
		$filterSpecs += $this->getDefaultSpecs();
		return new Filter(
			new Specs(
				$filterSpecs['rules'],
				$filterSpecs['comments'],
				$filterSpecs['name'],
				array_keys( $filterSpecs['actions'] ),
				$filterSpecs['group']
			),
			new Flags(
				$filterSpecs['enabled'],
				$filterSpecs['deleted'],
				$filterSpecs['privacy'],
				$filterSpecs['global']
			),
			$filterSpecs['actions'],
			new LastEditInfo(
				$filterSpecs['lastEditor']->getId(),
				$filterSpecs['lastEditor']->getName(),
				$this->getDb()->timestamp( $filterSpecs['lastEditTimestamp'] )
			),
			$filterSpecs['id'],
			$filterSpecs['hitCount'],
			$filterSpecs['throttled']
		);
	}

	/**
	 * @see MediaWikiIntegrationTestCase::getDb
	 * @return IDatabase
	 */
	abstract protected function getDb();

	/**
	 * @see MediaWikiIntegrationTestCase::getTestUser
	 * @param string|string[] $groups User groups that the test user should be in.
	 * @return TestUser
	 */
	abstract protected function getTestUser( $groups = [] );
}
