<?php

// phpcs:disable

/* @phan-file-suppress PhanTypeSuspiciousEcho, PhanTypeConversionFromArray, PhanPluginUseReturnValueInternalKnown, PhanNoopNew */

/*
 * This test ensures that taint-check knows about unsafe methods in MediaWiki. Knowledge about those methods
 * can come either from annotations on the methods themselves, or from the plugin. It does not really matter,
 * as long as taint-check knows about them.
 *
 * If phan reports new security issues or unused suppressions in this file, DO NOT just fix the errors, and instead
 * make sure that your patch is not causing some of the taintedness data to be lost.
 *
 * If you are introducing an alias for any of these classes, then duplicate the relevant test so that it covers
 * both the old and the new class name.
 */

use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Html\Html;
use MediaWiki\Linker\Linker;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Request\WebRequest;
use MediaWiki\Shell\Result;
use MediaWiki\Shell\Shell;
use MediaWiki\Status\Status;
use MediaWiki\Title\TitleValue;
use Shellbox\Command\UnboxedResult;
use Shellbox\Shellbox;
use Wikimedia\Rdbms\DeleteQueryBuilder;
use Wikimedia\Rdbms\InsertQueryBuilder;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Rdbms\UpdateQueryBuilder;

die( 'This file should never be loaded' );

class TaintCheckAnnotationsTest {
	function testDatabase( \Wikimedia\Rdbms\Database $db ) {
		$db->query( $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->query( 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->select( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->select( '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->select( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->select( 'safe', 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->selectField( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectField( '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectField( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->selectField( 'safe', 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->selectFieldValues( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectFieldValues( '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectFieldValues( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->selectFieldValues( 'safe', 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->selectSQLText( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectSQLText( '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectSQLText( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->selectSQLText( 'safe', 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS
		$db->query( $db->selectSQLText( 'safe', 'safe' ) ); // Safe

		$db->selectRowCount( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectRowCount( '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectRowCount( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->selectRowCount( 'safe', 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->selectRow( $_GET['a'], '', [] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectRow( '', $_GET['a'], [] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectRow( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->selectRow( 'safe', 'safe', [] ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->delete( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->delete( '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->delete( '', '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->select( 'safe', 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->insert( $_GET['a'], [] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->insert( '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->insert( '', [], $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->insert( '', [], '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->insert( 'safe', [] ); // Safe

		$db->update( $_GET['a'], [], [] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->update( '', [ $_GET['a'] ], [] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->update( '', [], [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->update( '', [], [], $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->update( 'safe', [], [] ); // Safe

		$identQuoted = $db->addIdentifierQuotes( $_GET['a'] );
		echo $identQuoted;// @phan-suppress-current-line SecurityCheck-XSS
		$db->query( $identQuoted );// Safe

		$quoted = $db->addQuotes( $_GET['a'] );
		echo $quoted;// @phan-suppress-current-line SecurityCheck-XSS
		$db->query( $quoted );// Safe

		// buildLike is only hardcoded for the Database class
		echo $db->buildLike( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		$db->query( $db->buildLike( $_GET['a'] ) );// Safe
		echo $db->buildLike( '', $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		$db->query( $db->buildLike( '', $_GET['a'] ) );// Safe
		echo $db->buildLike( '', '', '', '', '', $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		$db->query( $db->buildLike( '', '', '', '', '', $_GET['a'] ) );// Safe
	}

	function testIDatabase( \Wikimedia\Rdbms\IDatabase $db ) {
		$db->query( $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->query( 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->select( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->select( '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->select( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->select( 'safe', 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->selectField( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectField( '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectField( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->selectField( 'safe', 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->selectFieldValues( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectFieldValues( '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectFieldValues( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->selectFieldValues( 'safe', 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->selectSQLText( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectSQLText( '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectSQLText( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->selectSQLText( 'safe', 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS
		$db->query( $db->selectSQLText( 'safe', 'safe' ) ); // Safe

		$db->selectRowCount( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectRowCount( '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectRowCount( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->selectRowCount( 'safe', 'safe' ); // Safe

		$db->selectRow( $_GET['a'], '', [] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectRow( '', $_GET['a'], [] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectRow( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->selectRow( 'safe', 'safe', [] ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->delete( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->delete( '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->delete( '', '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->select( 'safe', 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->insert( $_GET['a'], [] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->insert( '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->insert( '', [], $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->insert( '', [], '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->insert( 'safe', [] ); // Safe

		$db->update( $_GET['a'], [], [] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->update( '', [ $_GET['a'] ], [] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->update( '', [], [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->update( '', [], [], $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->update( 'safe', [], [] ); // Safe

		$identQuoted = $db->addIdentifierQuotes( $_GET['a'] );
		echo $identQuoted;// @phan-suppress-current-line SecurityCheck-XSS
		$db->query( $identQuoted );// Safe

		$quoted = $db->addQuotes( $_GET['a'] );
		echo $quoted;// @phan-suppress-current-line SecurityCheck-XSS
		$db->query( $quoted );// Safe

		// makeList is only hardcoded for the IDatabase interface
		echo $db->makeList( [ $_GET['a'] ] );// @phan-suppress-current-line SecurityCheck-XSS
		$db->query( $db->makeList( $_GET['a'] ) );// Safe
		echo $db->makeList( [] );// Safe
	}

	function testIMaintainableDatabase( \Wikimedia\Rdbms\IMaintainableDatabase $db ) {
		$db->query( $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->query( 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->select( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->select( '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->select( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->select( 'safe', 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->selectField( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectField( '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectField( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->selectField( 'safe', 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->selectFieldValues( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectFieldValues( '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectFieldValues( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->selectFieldValues( 'safe', 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->selectSQLText( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectSQLText( '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectSQLText( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->selectSQLText( 'safe', 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS
		$db->query( $db->selectSQLText( 'safe', 'safe' ) ); // Safe

		$db->selectRowCount( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectRowCount( '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectRowCount( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->selectRowCount( 'safe', 'safe' ); // Safe

		$db->selectRow( $_GET['a'], '', [] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectRow( '', $_GET['a'], [] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectRow( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->selectRow( 'safe', 'safe', [] ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->delete( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->delete( '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->delete( '', '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->select( 'safe', 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->insert( $_GET['a'], [] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->insert( '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->insert( '', [], $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->insert( '', [], '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->insert( 'safe', [] ); // Safe

		$db->update( $_GET['a'], [], [] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->update( '', [ $_GET['a'] ], [] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->update( '', [], [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->update( '', [], [], $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->update( 'safe', [], [] ); // Safe

		$identQuoted = $db->addIdentifierQuotes( $_GET['a'] );
		echo $identQuoted;// @phan-suppress-current-line SecurityCheck-XSS
		$db->query( $identQuoted );// Safe

		$quoted = $db->addQuotes( $_GET['a'] );
		echo $quoted;// @phan-suppress-current-line SecurityCheck-XSS
		$db->query( $quoted );// Safe
	}

	function testDBConnRef( \Wikimedia\Rdbms\DBConnRef $db ) {
		$db->query( $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->query( 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->select( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->select( '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->select( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->select( 'safe', 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->selectField( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectField( '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectField( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->selectField( 'safe', 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->selectFieldValues( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectFieldValues( '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectFieldValues( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->selectFieldValues( 'safe', 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->selectSQLText( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectSQLText( '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectSQLText( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->selectSQLText( 'safe', 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS
		$db->query( $db->selectSQLText( 'safe', 'safe' ) ); // Safe

		$db->selectRowCount( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectRowCount( '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectRowCount( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->selectRowCount( 'safe', 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->selectRow( $_GET['a'], '', [] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectRow( '', $_GET['a'], [] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->selectRow( '', '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->selectRow( 'safe', 'safe', [] ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->delete( $_GET['a'], '' ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->delete( '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->delete( '', '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->select( 'safe', 'safe' ); // @phan-suppress-current-line SecurityCheck-XSS

		$db->insert( $_GET['a'], [] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->insert( '', [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->insert( '', [], $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->insert( '', [], '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->insert( 'safe', [] ); // Safe

		$db->update( $_GET['a'], [], [] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->update( '', [ $_GET['a'] ], [] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->update( '', [], [ $_GET['a'] ] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		$db->update( '', [], [], $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-SQLInjection
		echo $db->update( 'safe', [], [] ); // Safe

		$identQuoted = $db->addIdentifierQuotes( $_GET['a'] );
		echo $identQuoted;// @phan-suppress-current-line SecurityCheck-XSS
		$db->query( $identQuoted );// Safe

		$quoted = $db->addQuotes( $_GET['a'] );
		echo $quoted;// @phan-suppress-current-line SecurityCheck-XSS
		$db->query( $quoted );// Safe
	}

	function testDatabaseMySQL( \Wikimedia\Rdbms\DatabaseMySQL $db ) {
		$quoted = $db->addQuotes( $_GET['a'] );
		echo $quoted;// @phan-suppress-current-line SecurityCheck-XSS
		$db->query( $quoted );// Safe

		$identQuoted = $db->addIdentifierQuotes( $_GET['a'] );
		echo $identQuoted;// @phan-suppress-current-line SecurityCheck-XSS
		$db->query( $identQuoted );// Safe
	}

	function testDatabasePostgres( \Wikimedia\Rdbms\DatabasePostgres $db ) {
		$quoted = $db->addQuotes( $_GET['a'] );
		echo $quoted;// @phan-suppress-current-line SecurityCheck-XSS
		$db->query( $quoted );// Safe
	}

	function testDatabaseSqlite( \Wikimedia\Rdbms\DatabaseSqlite $db ) {
		$quoted = $db->addQuotes( $_GET['a'] );
		echo $quoted;// @phan-suppress-current-line SecurityCheck-XSS
		$db->query( $quoted );// Safe
	}

	function testSelectQueryBuilder( SelectQueryBuilder $sqb ) {
		$sqb->table( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$sqb->table( '', $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$sqb->tables( [ $_GET['a'] ] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$sqb->from( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$sqb->from( '', $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection

		$sqb->fields( [ $_GET['a'] ] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$sqb->select( [ $_GET['a'] ] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$sqb->field( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$sqb->field( '', $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection

		$sqb->where( [ $_GET['a'] ] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$sqb->where( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$sqb->where( [ 'foo' => $_GET['a'] ] );// Safe
		$sqb->andWhere( [ $_GET['a'] ] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$sqb->andWhere( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$sqb->andWhere( [ 'foo' => $_GET['a'] ] );// Safe
		$sqb->conds( [ $_GET['a'] ] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$sqb->conds( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$sqb->conds( [ 'foo' => $_GET['a'] ] );// Safe

		$sqb->caller( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection

		echo $sqb->fetchResultSet();// @phan-suppress-current-line SecurityCheck-XSS
		echo $sqb->fetchField();// @phan-suppress-current-line SecurityCheck-XSS
		echo $sqb->fetchFieldValues();// @phan-suppress-current-line SecurityCheck-XSS
		echo $sqb->fetchRow();// @phan-suppress-current-line SecurityCheck-XSS
	}

	function testInsertQueryBuilder( InsertQueryBuilder $iqb ) {
		$iqb->table( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$iqb->insert( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$iqb->insertInto( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection

		$iqb->row( $_GET['a'] );// TODO: Unsafe
		$iqb->row( [ 'bar' => $_GET['a'] ] );// Safe
		$iqb->row( [ $_GET['a'] => 'foo' ] );// TODO: Unsafe

		$iqb->rows( $_GET['a'] );// TODO: Unsafe
		$iqb->rows( [ $_GET['a'] ] );// TODO: Unsafe
		$iqb->rows( [ $_GET['a'] => [] ] );// Safe
		$iqb->rows( [ $_GET['a'] => [ 'foo' => $_GET['a'] ] ] );// Safe
		$iqb->rows( [ $_GET['a'] => [ $_GET['a'] => 'foo' ] ] );// TODO: Unsafe

		$iqb->set( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$iqb->set( [ $_GET['a'] ] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$iqb->set( [ 'x' => $_GET['a'] ] );// Safe

		$iqb->andSet( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$iqb->andSet( [ $_GET['a'] ] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$iqb->andSet( [ 'x' => $_GET['a'] ] );// Safe

		$iqb->caller( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
	}

	function testUpdateQueryBuilder( UpdateQueryBuilder $uqb ) {
		$uqb->table( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$uqb->update( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection

		$uqb->where( [ $_GET['a'] ] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$uqb->where( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$uqb->where( [ 'foo' => $_GET['a'] ] );// Safe
		$uqb->andWhere( [ $_GET['a'] ] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$uqb->andWhere( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$uqb->andWhere( [ 'foo' => $_GET['a'] ] );// Safe
		$uqb->conds( [ $_GET['a'] ] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$uqb->conds( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$uqb->conds( [ 'foo' => $_GET['a'] ] );// Safe

		$uqb->set( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$uqb->set( [ $_GET['a'] ] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$uqb->set( [ 'x' => $_GET['a'] ] );// Safe
		$uqb->andSet( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$uqb->andSet( [ $_GET['a'] ] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$uqb->andSet( [ 'x' => $_GET['a'] ] );// Safe

		$uqb->caller( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
	}

	function testDeleteQueryBuilder( DeleteQueryBuilder $dqb ) {
		$dqb->table( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$dqb->deleteFrom( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$dqb->delete( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection

		$dqb->where( [ $_GET['a'] ] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$dqb->where( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$dqb->where( [ 'foo' => $_GET['a'] ] );// Safe
		$dqb->andWhere( [ $_GET['a'] ] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$dqb->andWhere( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$dqb->andWhere( [ 'foo' => $_GET['a'] ] );// Safe
		$dqb->conds( [ $_GET['a'] ] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$dqb->conds( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
		$dqb->conds( [ 'foo' => $_GET['a'] ] );// Safe

		$dqb->caller( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-SQLInjection
	}

	function testMessage( Message $msg ) {
		echo $msg->plain();// @phan-suppress-current-line SecurityCheck-XSS
		echo $msg->text();// @phan-suppress-current-line SecurityCheck-XSS
		echo $msg->parseAsBlock(); // Safe
		htmlspecialchars( $msg->parseAsBlock() );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
		echo $msg->parse(); // Safe
		htmlspecialchars( $msg->parse() );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
		echo $msg->escaped(); // Safe
		htmlspecialchars( $msg->escaped() );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
		echo $msg->__toString(); // Safe
		htmlspecialchars( $msg->__toString() );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
		$msg->rawParams( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		echo $msg->rawParams( '' );// Safe
		shell_exec( $msg->rawParams( '' ) );// Safe
	}

	function testStripState( StripState $ss ) {
		$ss->addNoWiki( $_GET['a'], '' );//Safe
		$ss->addNoWiki( '', $_GET['b'] );// @phan-suppress-current-line SecurityCheck-XSS
		$ss->addGeneral( $_GET['a'], '' );//Safe
		$ss->addGeneral( '', $_GET['b'] );// @phan-suppress-current-line SecurityCheck-XSS
	}

	function testShellFunctions(
		Shell $shell,
		\MediaWiki\Shell\Command $shellCmd,
		\Shellbox\Command\Command $shellboxCmd,
		Result $result, // Alias of UnboxedResult
		UnboxedResult $unboxedResult
	) {
		wfShellExec( [ $_GET['a'] ] );// Safe
		wfShellExec( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-ShellInjection
		echo wfShellExec( '' );// @phan-suppress-current-line SecurityCheck-XSS

		wfShellExecWithStderr( [ $_GET['a'] ] );// Safe
		wfShellExecWithStderr( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-ShellInjection
		echo wfShellExecWithStderr( '' );// @phan-suppress-current-line SecurityCheck-XSS

		shell_exec( wfEscapeShellArg( $_GET['a'] ) ); // Safe
		shell_exec( wfEscapeShellArg( '', '', '', '', '', $_GET['a'] ) ); // Safe
		echo wfEscapeShellArg( $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-XSS
		echo wfEscapeShellArg( '', '', '', '', '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-XSS

		shell_exec( $shell->escape( $_GET['a'] ) ); // Safe
		shell_exec( $shell->escape( '', '', '', '', '', $_GET['a'] ) ); // Safe
		echo $shell->escape( $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-XSS
		echo $shell->escape( '', '', '', '', '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-XSS

		$shellCmd->unsafeParams( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-ShellInjection
		$shellCmd->unsafeParams( '', '', '', '', $_GET['a'] );// @phan-suppress-current-line SecurityCheck-ShellInjection

		shell_exec( Shellbox::escape( $_GET['a'] ) ); // Safe
		shell_exec( Shellbox::escape( '', '', '', '', '', $_GET['a'] ) ); // Safe
		echo Shellbox::escape( $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-XSS
		echo Shellbox::escape( '', '', '', '', '', $_GET['a'] ); // @phan-suppress-current-line SecurityCheck-XSS

		$shellboxCmd->unsafeParams( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-ShellInjection
		$shellboxCmd->unsafeParams( '', '', '', '', $_GET['a'] );// @phan-suppress-current-line SecurityCheck-ShellInjection

		echo $result->getStdout();// @phan-suppress-current-line SecurityCheck-XSS
		echo $result->getStderr();// @phan-suppress-current-line SecurityCheck-XSS

		echo $unboxedResult->getStdout();// @phan-suppress-current-line SecurityCheck-XSS
		echo $unboxedResult->getStderr();// @phan-suppress-current-line SecurityCheck-XSS
	}

	function testHtml() {
		echo Html::rawElement( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		Html::rawElement( '', [ htmlspecialchars( '' ) ] );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
		echo Html::rawElement( '', $_GET['a'] );// Safe
		echo Html::rawElement( '', [], $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		echo Html::rawElement( '', [], '' );// Safe
		htmlspecialchars( Html::rawElement( '', [], '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped

		echo Html::element( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		Html::element( '', [ htmlspecialchars( '' ) ] );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
		echo Html::element( '', $_GET['a'] );// Safe
		echo Html::element( '', [], htmlspecialchars( '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
		echo Html::element( '', [], $_GET['a'] );// Safe
		echo Html::element( '', [], '' );// Safe
		htmlspecialchars( Html::element( '', [], '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped

		echo Html::encodeJsVar( $_GET['a'] );// Safe
		echo Html::encodeJsVar( htmlspecialchars( '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped

		echo Html::encodeJsCall( $_GET['a'], [] );// @phan-suppress-current-line SecurityCheck-XSS
		echo Html::encodeJsCall( '', $_GET['a'] );// Safe
		echo Html::encodeJsCall( '', [ htmlspecialchars( '' ) ] );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
	}

	/**
	 * Non-namespaced alias of the Html class.
	 */
	function testHtmlAlias() {
		echo \Html::rawElement( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		\Html::rawElement( '', [ htmlspecialchars( '' ) ] );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
		echo \Html::rawElement( '', $_GET['a'] );// Safe
		echo \Html::rawElement( '', [], $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		echo \Html::rawElement( '', [], '' );// Safe
		htmlspecialchars( \Html::rawElement( '', [], '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped

		echo \Html::element( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		\Html::element( '', [ htmlspecialchars( '' ) ] );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
		echo \Html::element( '', $_GET['a'] );// Safe
		echo \Html::element( '', [], htmlspecialchars( '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
		echo \Html::element( '', [], $_GET['a'] );// Safe
		echo \Html::element( '', [], '' );// Safe
		htmlspecialchars( \Html::element( '', [], '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
	}

	function textXml() {
		echo \Xml::tags( $_GET['a'], [], '' );// @phan-suppress-current-line SecurityCheck-XSS
		\Xml::tags( '', [ htmlspecialchars( '' ) ], '' );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
		echo \Xml::tags( '', $_GET['a'], '' );// Safe
		echo \Xml::tags( '', [], $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		echo \Xml::tags( '', [], '' );// Safe
		htmlspecialchars( \Xml::tags( '', [], '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped

		echo \Xml::element( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		\Xml::element( '', [ htmlspecialchars( '' ) ] );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
		echo \Xml::element( '', $_GET['a'] );// Safe
		echo \Xml::element( '', [], htmlspecialchars( '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
		echo \Xml::element( '', [], $_GET['a'] );// Safe
		echo \Xml::element( '', [], '' );// Safe
		htmlspecialchars( \Xml::element( '', [], '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped

		echo \Xml::encodeJsVar( $_GET['a'] );// Safe
		echo \Xml::encodeJsVar( htmlspecialchars( '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped

		echo \Xml::encodeJsCall( $_GET['a'], [] );// @phan-suppress-current-line SecurityCheck-XSS
		echo \Xml::encodeJsCall( '', $_GET['a'] );// Safe
		echo \Xml::encodeJsCall( '', [ htmlspecialchars( '' ) ] );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
	}

	function testHtmlArmor() {
		new HtmlArmor( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
	}

	function testOutputPage( \MediaWiki\Output\OutputPage $out ) {
		$out->addHeadItem( $_GET['a'], '' );// safe
		$out->addHeadItem( '', $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		$out->addHeadItems( [ 'foo' => $_GET['a'] ] );// @phan-suppress-current-line SecurityCheck-XSS

		$out->addHTML( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS

		$out->prependHTML( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS

		$out->addInlineStyle( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		$out->addSubtitle( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		$out->setSubtitle( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		$out->addScript( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		$out->addInlineScript( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		$out->setIndicators( [ 'foo' => $_GET['a'] ] );// @phan-suppress-current-line SecurityCheck-XSS
	}

	/**
	 * Non-namespaced alias of the OutputPage class.
	 */
	function testOutputPageAlias( \OutputPage $out ) {
		$out->addHeadItem( $_GET['a'], '' );// safe
		$out->addHeadItem( '', $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		$out->addHeadItems( [ 'foo' => $_GET['a'] ] );// @phan-suppress-current-line SecurityCheck-XSS

		$out->addHTML( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS

		$out->prependHTML( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS

		$out->addInlineStyle( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		$out->addSubtitle( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		$out->setSubtitle( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		$out->addScript( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		$out->addInlineScript( $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		$out->setIndicators( [ 'foo' => $_GET['a'] ] );// @phan-suppress-current-line SecurityCheck-XSS
	}

	function testSanitizer() {
		echo Sanitizer::escapeHtmlAllowEntities( $_GET['a'] );// Safe
		shell_exec( Sanitizer::escapeHtmlAllowEntities( $_GET['a'] ) );// @phan-suppress-current-line SecurityCheck-ShellInjection
		htmlspecialchars( Sanitizer::escapeHtmlAllowEntities( '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped

		echo Sanitizer::safeEncodeAttribute( $_GET['a'] );// Safe
		Sanitizer::safeEncodeAttribute( htmlspecialchars( '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
		htmlspecialchars( Sanitizer::safeEncodeAttribute( '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped

		echo Sanitizer::encodeAttribute( $_GET['a'] );// Safe
		Sanitizer::encodeAttribute( htmlspecialchars( '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
		htmlspecialchars( Sanitizer::encodeAttribute( '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
	}

	/**
	 * Non-namespaced alias of the Sanitizer class.
	 */
	function testSanitizerAlias() {
		echo \Sanitizer::escapeHtmlAllowEntities( $_GET['a'] );// Safe
		shell_exec( \Sanitizer::escapeHtmlAllowEntities( $_GET['a'] ) );// @phan-suppress-current-line SecurityCheck-ShellInjection
		htmlspecialchars( \Sanitizer::escapeHtmlAllowEntities( '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped

		echo \Sanitizer::safeEncodeAttribute( $_GET['a'] );// Safe
		\Sanitizer::safeEncodeAttribute( htmlspecialchars( '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
		htmlspecialchars( \Sanitizer::safeEncodeAttribute( '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped

		echo \Sanitizer::encodeAttribute( $_GET['a'] );// Safe
		\Sanitizer::encodeAttribute( htmlspecialchars( '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
		htmlspecialchars( \Sanitizer::encodeAttribute( '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
	}

	function testWebRequest( WebRequest $req ) {
		// @phan-suppress-next-line PhanAccessMethodPrivate
		echo $req->getGPCVal( [], '', '' );// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getRawVal( '' );// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getVal( '' );// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getArray( '' );// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getIntArray( '' );// Safe
		echo $req->getInt( '' );// Safe
		echo $req->getIntOrNull( '' );// Safe
		echo $req->getFloat( '' );// Safe
		echo $req->getBool( '' );// Safe
		echo $req->getFuzzyBool( '' );// Safe
		echo $req->getCheck( '' );// Safe
		echo $req->getText( '' );// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getValues( '' );// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getValueNames( [] );// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getQueryValues();// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getRawQueryString();// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getRawPostString();// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getRawInput();// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getCookie( '' );// @phan-suppress-current-line SecurityCheck-XSS
		echo WebRequest::getGlobalRequestURL();// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getRequestURL();// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getFullRequestURL();// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getAllHeaders();// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getHeader( '' );// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getAcceptLang();// @phan-suppress-current-line SecurityCheck-XSS
	}

	/**
	 * Non-namespaced alias of the WebRequest class.
	 */
	function testWebRequestAlias( \WebRequest $req ) {
		// @phan-suppress-next-line PhanAccessMethodPrivate
		echo $req->getGPCVal( [], '', '' );// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getRawVal( '' );// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getVal( '' );// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getArray( '' );// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getIntArray( '' );// Safe
		echo $req->getInt( '' );// Safe
		echo $req->getIntOrNull( '' );// Safe
		echo $req->getFloat( '' );// Safe
		echo $req->getBool( '' );// Safe
		echo $req->getFuzzyBool( '' );// Safe
		echo $req->getCheck( '' );// Safe
		echo $req->getText( '' );// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getValues( '' );// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getValueNames( [] );// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getQueryValues();// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getRawQueryString();// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getRawPostString();// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getRawInput();// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getCookie( '' );// @phan-suppress-current-line SecurityCheck-XSS
		echo WebRequest::getGlobalRequestURL();// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getRequestURL();// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getFullRequestURL();// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getAllHeaders();// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getHeader( '' );// @phan-suppress-current-line SecurityCheck-XSS
		echo $req->getAcceptLang();// @phan-suppress-current-line SecurityCheck-XSS
	}

	function testCommentStore( CommentStore $store, \Wikimedia\Rdbms\IDatabase $db ) {
		echo $store->insert( $db, '' );// Safe
		echo $store->getJoin( '' );// Safe
	}

	/**
	 * Non-namespaced alias of the CommentStore class.
	 */
	function testCommentStoreAlias( \CommentStore $store, \Wikimedia\Rdbms\IDatabase $db ) {
		echo $store->insert( $db, '' );// Safe
		echo $store->getJoin( '' );// Safe
	}

	function testLinker( LinkTarget $target ) {
		$unsafeTarget = $this->getUnsafeLinkTarget();
		// Make sure taint-check knows it's unsafe
		echo $unsafeTarget;// @phan-suppress-current-line SecurityCheck-XSS
		echo Linker::linkKnown( $unsafeTarget );// Safe
		echo Linker::linkKnown( $target, $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		echo Linker::linkKnown( $target, '', $_GET['a'] );// Safe
		echo Linker::linkKnown( $target, '', [], $_GET['a'] );// Safe
		echo Linker::linkKnown( $target, '', [], [], $_GET['a'] );// Safe
		htmlspecialchars( Linker::linkKnown( $target ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
	}

	/**
	 * Non-namespaced alias of the Linker class.
	 */
	function testLinkerAlias( LinkTarget $target ) {
		$unsafeTarget = $this->getUnsafeLinkTarget();
		// Make sure taint-check knows it's unsafe
		echo $unsafeTarget;// @phan-suppress-current-line SecurityCheck-XSS
		echo \Linker::linkKnown( $unsafeTarget );// Safe
		echo \Linker::linkKnown( $target, $_GET['a'] );// @phan-suppress-current-line SecurityCheck-XSS
		echo \Linker::linkKnown( $target, '', $_GET['a'] );// Safe
		echo \Linker::linkKnown( $target, '', [], $_GET['a'] );// Safe
		echo \Linker::linkKnown( $target, '', [], [], $_GET['a'] );// Safe
		htmlspecialchars( \Linker::linkKnown( $target ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
	}

	function testLinkRenderer( LinkRenderer $linkRenderer, LinkTarget $target ) {
		$unsafeTarget = $this->getUnsafeLinkTarget();
		// Make sure taint-check knows it's unsafe
		echo $unsafeTarget;// @phan-suppress-current-line SecurityCheck-XSS

		echo $linkRenderer->makeLink( $unsafeTarget );// Safe
		echo $linkRenderer->makeLink( $target, $_GET['a'] );// Safe
		$linkRenderer->makeLink( $target, htmlspecialchars( '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
		echo $linkRenderer->makeLink( $target, '', $_GET['a'] );// Safe
		echo $linkRenderer->makeLink( $target, '', [], $_GET['a'] );// Safe
		htmlspecialchars( $linkRenderer->makeLink( $target ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped

		echo $linkRenderer->makeKnownLink( $unsafeTarget );// Safe
		echo $linkRenderer->makeKnownLink( $target, $_GET['a'] );// Safe
		$linkRenderer->makeKnownLink( $target, htmlspecialchars( '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
		echo $linkRenderer->makeKnownLink( $target, '', $_GET['a'] );// Safe
		echo $linkRenderer->makeKnownLink( $target, '', [], $_GET['a'] );// Safe
		htmlspecialchars( $linkRenderer->makeKnownLink( $target ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped

		echo $linkRenderer->makePreloadedLink( $unsafeTarget );// Safe
		echo $linkRenderer->makePreloadedLink( $target, $_GET['a'] );// Safe
		$linkRenderer->makePreloadedLink( $target, htmlspecialchars( '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
		echo $linkRenderer->makePreloadedLink( $target, '', $_GET['a'] );// Safe
		echo $linkRenderer->makePreloadedLink( $target, '', '', $_GET['a'] );// Safe
		htmlspecialchars( $linkRenderer->makePreloadedLink( $target ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped

		echo $linkRenderer->makeBrokenLink( $unsafeTarget );// Safe
		echo $linkRenderer->makeBrokenLink( $target, $_GET['a'] );// Safe
		$linkRenderer->makeBrokenLink( $target, htmlspecialchars( '' ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
		echo $linkRenderer->makeBrokenLink( $target, '', $_GET['a'] );// Safe
		echo $linkRenderer->makeBrokenLink( $target, '', [], $_GET['a'] );// Safe
		htmlspecialchars( $linkRenderer->makeBrokenLink( $target ) );// @phan-suppress-current-line SecurityCheck-DoubleEscaped
	}

	/**
	 * NOTE: we can't type hint this as LinkTarget, or taint-check will think that it's safe
	 * due to __toString().
	 *
	 * @return-taint tainted
	 */
	function getUnsafeLinkTarget() {
		return $GLOBALS['unsafeLinkTarget'];
	}

	function testStatusValue() {
		echo StatusValue::newGood( $_GET['a'] );// Safe
		echo StatusValue::newGood( $_GET['a'] )->getValue();// Safe
		echo StatusValue::newGood( $_GET['a'] )->setResult( true, $_GET['a'] );// Safe
	}

	function testStatus() {
		echo Status::newGood( $_GET['a'] );// Safe
		echo Status::newGood( $_GET['a'] )->getValue();// Safe
		echo Status::newGood( $_GET['a'] )->setResult( true, $_GET['a'] );// Safe
	}

	/**
	 * Non-namespaced alias of the Status class.
	 */
	function testStatusAlias() {
		echo \Status::newGood( $_GET['a'] );// Safe
		echo \Status::newGood( $_GET['a'] )->getValue();// Safe
		echo \Status::newGood( $_GET['a'] )->setResult( true, $_GET['a'] );// Safe
	}
}
