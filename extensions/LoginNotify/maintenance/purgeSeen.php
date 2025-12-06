<?php

namespace LoginNotify\Maintenance;

use LoginNotify\LoginNotify;
use MediaWiki\Maintenance\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class PurgeSeen extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Purge expired user IP address information stored by LoginNotify' );
	}

	public function execute() {
		$loginNotify = $this->getLoginNotify();
		$minId = $loginNotify->getMinExpiredId();
		for ( ; $minId !== null; $this->waitForReplication() ) {
			$minId = $loginNotify->purgeSeen( $minId );
		}
	}

	private function getLoginNotify(): LoginNotify {
		return $this->getServiceContainer()->getService( 'LoginNotify.LoginNotify' );
	}
}

// @codeCoverageIgnoreStart
$maintClass = PurgeSeen::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
