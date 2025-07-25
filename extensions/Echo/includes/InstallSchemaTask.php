<?php

namespace MediaWiki\Extension\Notifications;

use MediaWiki\Installer\Task\Task;
use MediaWiki\Status\Status;
use Wikimedia\Rdbms\DatabaseDomain;

/**
 * TODO: replace this with virtual domains
 *
 * This is a temporary hack to support WMF production wiki creation, with
 * database creation in an external cluster. Core knows how to do this for
 * virtual domains, but this extension is not yet using a virtual domain. (T348573)
 */
class InstallSchemaTask extends Task {
	/** @inheritDoc */
	public function getName() {
		return 'echo-schema';
	}

	/** @inheritDoc */
	public function getDescription() {
		return 'Installing Echo tables';
	}

	/** @inheritDoc */
	public function getDependencies() {
		return [ 'services', 'schema' ];
	}

	/** @inheritDoc */
	public function getAliases() {
		// Group with extension tables so that things that depend on
		// extension tables will have this
		return 'extension-tables';
	}

	public function execute(): Status {
		$status = Status::newGood();
		$cluster = $this->getConfigVar( 'EchoCluster' );
		if ( !$cluster ) {
			// This case is adequately handled by LoadExtensionSchemaUpdates
			return $status;
		}

		// Get the load balancer
		$lbFactory = $this->getServices()->getDBLoadBalancerFactory();
		$databaseCreator = $this->getDatabaseCreator();
		$domainId = $lbFactory->getLocalDomainID();
		$database = DatabaseDomain::newFromId( $domainId )->getDatabase();
		$echoLB = $lbFactory->getExternalLB( $cluster );

		// Create database
		if ( !$databaseCreator->existsInLoadBalancer( $echoLB, $database ) ) {
			$databaseCreator->createInLoadBalancer( $echoLB, $database );
		}

		// Create tables
		$dbw = $echoLB->getMaintenanceConnectionRef( DB_PRIMARY );
		$dbw->setSchemaVars( $this->getContext()->getSchemaVars() );
		if ( !$dbw->tableExists( 'echo_event' ) ) {
			$status = $this->applySourceFile( $dbw, 'tables-generated.sql' );
		}
		return $status;
	}

}
