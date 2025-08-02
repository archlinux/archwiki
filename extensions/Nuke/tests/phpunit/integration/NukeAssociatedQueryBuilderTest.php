<?php

namespace MediaWiki\Extension\Nuke\Test\Integration;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Nuke\NukeAssociatedQueryBuilder;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Nuke\NukeAssociatedQueryBuilder
 */
class NukeAssociatedQueryBuilderTest extends MediaWikiIntegrationTestCase {

	protected function getNukeAssociatedQueryBuilder() {
		return new NukeAssociatedQueryBuilder(
			$this->getServiceContainer()->getDBLoadBalancerFactory()->getReplicaDatabase(),
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getNamespaceInfo(),
			RequestContext::getMain()->getLanguage()
		);
	}

	/**
	 * Test that talk pages are properly found by the query builder.
	 *
	 * @return void
	 */
	public function testTalkPages() {
		$user1 = $this->getMutableTestUser();
		$user2 = $this->getMutableTestUser();

		$page1 =
			$this->insertPage( 'Test 1', '', NS_MAIN, $user1->getUser() );
		$page2 =
			$this->insertPage( 'Test 2', '', NS_MAIN, $user1->getUser() );
		$page3 =
			$this->insertPage( 'Test 3', '', NS_PROJECT, $user1->getUser() );
		$this->insertPage( 'Test 1', '', NS_TALK, $user2->getUser() );
		$this->insertPage( 'Test 3', '', NS_PROJECT_TALK, $user2->getUser() );

		$result = $this->getNukeAssociatedQueryBuilder()
			->getTalkPages( [ $page1[ 'title' ], $page2['title'], $page3['title'] ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$result = iterator_to_array( $result );
		$this->assertCount( 2, $result );

		$this->assertEquals( NS_TALK, $result[0]->page_namespace );
		$this->assertEquals( 'Test_1', $result[0]->page_title );
		$this->assertEquals( $user2->getUser()->getName(), $result[0]->actor_name );

		$this->assertEquals( NS_PROJECT_TALK, $result[1]->page_namespace );
		$this->assertEquals( 'Test_3', $result[1]->page_title );
		$this->assertEquals( $user2->getUser()->getName(), $result[1]->actor_name );
	}

	/**
	 * Test that errors are properly handled by the query builder when passing in a page
	 * with a namespace which does not have a talk page.
	 *
	 * @return void
	 */
	public function testSpecialPage() {
		$user1 = $this->getMutableTestUser();
		$user2 = $this->getMutableTestUser();

		$page1 =
			$this->insertPage( 'Test 1', '', NS_MAIN, $user1->getUser() );
		$this->insertPage( 'Test 1', '', NS_TALK, $user2->getUser() );

		$result = $this->getNukeAssociatedQueryBuilder()
			->getTalkPages( [
				$page1[ 'title' ],
				Title::makeTitle( NS_SPECIAL, 'Nuke' )
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$result = iterator_to_array( $result );
		$this->assertCount( 1, $result );

		$this->assertEquals( NS_TALK, $result[0]->page_namespace );
		$this->assertEquals( 'Test_1', $result[0]->page_title );
		$this->assertEquals( $user2->getUser()->getName(), $result[0]->actor_name );
	}

	/**
	 * Test that redirect pages are properly found by the query builder.
	 *
	 * @return void
	 */
	public function testRedirectPages() {
		$user1 = $this->getMutableTestUser();
		$user2 = $this->getMutableTestUser();

		$page1 =
			$this->insertPage( 'Test 1', '', NS_MAIN, $user1->getUser() );
		$page2 =
			$this->insertPage( 'Test 2', '', NS_MAIN, $user1->getUser() );
		$page3 =
			$this->insertPage( 'Test 3', '', NS_PROJECT, $user1->getUser() );
		$this->insertPage(
			'Redirect 1',
			'#REDIRECT [[Test 1]]',
			NS_MAIN,
			$user2->getUser()
		);
		$this->insertPage(
			'Redirect 2',
			'#REDIRECT [[Test 1]]',
			NS_MAIN,
			$user2->getUser()
		);
		$this->insertPage(
			'Redirect 3',
			'#REDIRECT [[Test 1]]',
			NS_MAIN,
			$user2->getUser()
		);
		$this->insertPage(
			'Redirect 4',
			'#REDIRECT [[Project:Test 3]]',
			NS_MAIN,
			$user2->getUser()
		);
		$this->insertPage(
			'Redirect 5',
			'#REDIRECT [[Project:Test 3]]',
			NS_PROJECT,
			$user2->getUser()
		);
		// This should not show up (incorrect namespace target).
		$this->insertPage(
			'Redirect 6',
			'#REDIRECT [[Test 3]]',
			NS_PROJECT,
			$user2->getUser()
		);
		$this->runJobs();

		$result = $this->getNukeAssociatedQueryBuilder()
			->getRedirectPages( [ $page1[ 'title' ], $page2['title'], $page3['title'] ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$result = iterator_to_array( $result );
		$this->assertCount( 5, $result );

		$this->assertEquals( NS_MAIN, $result[0]->page_namespace );
		$this->assertEquals( 'Redirect_1', $result[0]->page_title );
		$this->assertEquals( $user2->getUser()->getName(), $result[0]->actor_name );

		$this->assertEquals( NS_MAIN, $result[1]->page_namespace );
		$this->assertEquals( 'Redirect_2', $result[1]->page_title );
		$this->assertEquals( $user2->getUser()->getName(), $result[1]->actor_name );

		$this->assertEquals( NS_MAIN, $result[2]->page_namespace );
		$this->assertEquals( 'Redirect_3', $result[2]->page_title );
		$this->assertEquals( $user2->getUser()->getName(), $result[2]->actor_name );

		$this->assertEquals( NS_MAIN, $result[3]->page_namespace );
		$this->assertEquals( 'Redirect_4', $result[3]->page_title );
		$this->assertEquals( $user2->getUser()->getName(), $result[3]->actor_name );

		$this->assertEquals( NS_PROJECT, $result[4]->page_namespace );
		$this->assertEquals( 'Redirect_5', $result[4]->page_title );
		$this->assertEquals( $user2->getUser()->getName(), $result[4]->actor_name );
	}

}
