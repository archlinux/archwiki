<?php
namespace MediaWiki\Extension\CheckUser\Services;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Output\OutputPage;
use MediaWiki\User\TempUser\TempUserConfig;

/**
 * Service for managing IP reveal functionality.
 */
class CheckUserIPRevealManager {
	/**
	 * @internal For use by ServiceWiring
	 */
	public const CONSTRUCTOR_OPTIONS = [ 'CheckUserSpecialPagesWithoutIPRevealButtons' ];

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly TempUserConfig $tempUserConfig,
		private readonly CheckUserPermissionManager $checkUserPermissionManager,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Check whether IP reveal buttons should be added to a given page for a
	 * given user.
	 *
	 * @param OutputPage $out
	 * @return bool
	 */
	public function shouldAddIPRevealButtons( OutputPage $out ): bool {
		if ( !$this->tempUserConfig->isKnown() ) {
			return false;
		}

		$action = $out->getRequest()->getVal( 'action', 'view' );
		if (
			$action !== 'view' &&
			$action !== 'history' &&
			$action !== 'info' &&
			$out->getRequest()->getRawVal( 'diff' ) === null &&
			$out->getRequest()->getRawVal( 'oldid' ) === null &&
			!( $out->getTitle() && $out->getTitle()->isSpecialPage() )
		) {
			return false;
		}

		$title = $out->getTitle();
		if ( $title && $title->isSpecialPage() ) {
			$excludePages = $this->options->get( 'CheckUserSpecialPagesWithoutIPRevealButtons' );
			foreach ( $excludePages as $excludePage ) {
				if ( $title->isSpecial( $excludePage ) ) {
					return false;
				}
			}
		}

		// Note we also add IP reveal buttons if the user is blocked
		// so that we can render the UI in a disabled state (T345639).
		$permStatus = $this->checkUserPermissionManager->canAccessTemporaryAccountIPAddresses(
			$out->getAuthority()
		);
		if ( !$permStatus->isGood() && !$permStatus->getBlock() ) {
			return false;
		}

		return true;
	}
}
