<?php

namespace MediaWiki\Extension\DiscussionTools\Maintenance;

use Exception;
use ForeignResourceManager;
use Maintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ManageForeignResources extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'DiscussionTools' );
	}

	public function execute() {
		$frm = new ForeignResourceManager(
			__DIR__ . '/../modules/lib/foreign-resources.yaml',
			__DIR__ . '/../modules/lib'
		);

		try {
			return $frm->run( 'update', 'all' );
		} catch ( Exception $e ) {
			$this->fatalError( "Error: {$e->getMessage()}" );
		}
	}
}

$maintClass = ManageForeignResources::class;
require_once RUN_MAINTENANCE_IF_MAIN;
