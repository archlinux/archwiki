<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Specials;

use MediaWiki\Block\Block;
use MediaWiki\Block\BlockTarget;
use MediaWiki\Block\BlockTargetFactory;
use MediaWiki\Block\BlockTargetWithUserPage;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Block\DatabaseBlockStore;
use MediaWiki\Block\UnblockUserFactory;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Logging\LogEventsList;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\WebRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserNamePrefixSearch;
use MediaWiki\User\UserNameUtils;
use MediaWiki\Watchlist\WatchlistManager;

/**
 * A special page for unblocking users
 *
 * @ingroup SpecialPage
 */
class SpecialUnblock extends SpecialPage {

	/** @var BlockTarget|null */
	protected $target;

	/** @var DatabaseBlock|null */
	protected $block;

	private UnblockUserFactory $unblockUserFactory;
	private BlockTargetFactory $blockTargetFactory;
	private DatabaseBlockStore $blockStore;
	private UserNameUtils $userNameUtils;
	private UserNamePrefixSearch $userNamePrefixSearch;
	private WatchlistManager $watchlistManager;

	protected bool $useCodex = false;

	public function __construct(
		UnblockUserFactory $unblockUserFactory,
		BlockTargetFactory $blockTargetFactory,
		DatabaseBlockStore $blockStore,
		UserNameUtils $userNameUtils,
		UserNamePrefixSearch $userNamePrefixSearch,
		WatchlistManager $watchlistManager
	) {
		parent::__construct( 'Unblock', 'block' );
		$this->unblockUserFactory = $unblockUserFactory;
		$this->blockTargetFactory = $blockTargetFactory;
		$this->blockStore = $blockStore;
		$this->userNameUtils = $userNameUtils;
		$this->userNamePrefixSearch = $userNamePrefixSearch;
		$this->watchlistManager = $watchlistManager;
		$this->useCodex = $this->getConfig()->get( MainConfigNames::UseCodexSpecialBlock ) ||
			$this->getRequest()->getBool( 'usecodex' );
	}

	public function doesWrites() {
		return true;
	}

	public function execute( $par ) {
		$this->checkPermissions();
		$this->checkReadOnly();

		$this->target = $this->getTargetFromRequest( $par, $this->getRequest() );

		// T382539
		if ( $this->useCodex ) {
			// If target is null, redirect to Special:Block
			if ( $this->target === null ) {
				// Use 301 (Moved Permanently) as this is a deprecation
				$this->getOutput()->redirect(
					SpecialPage::getTitleFor( 'Block' )->getFullURL( 'redirected=1' ),
					'301'
				);
				return;
			}
		}

		$this->block = $this->blockStore->newFromTarget( $this->target );
		if ( $this->target instanceof BlockTargetWithUserPage ) {
			// Set the 'relevant user' in the skin, so it displays links like Contributions,
			// User logs, UserRights, etc.
			$this->getSkin()->setRelevantUser( $this->target->getUserIdentity() );
		}

		$this->setHeaders();
		$this->outputHeader();
		$this->addHelpLink( 'Help:Blocking users' );

		$out = $this->getOutput();
		$out->setPageTitleMsg( $this->msg( 'unblock-target' ) );
		$out->addModules( [ 'mediawiki.userSuggest', 'mediawiki.special.block' ] );

		$form = HTMLForm::factory( 'ooui', $this->getFields(), $this->getContext() )
			->setWrapperLegendMsg( 'unblock-target' )
			->setSubmitCallback( function ( array $data, HTMLForm $form ) {
				if ( $this->target instanceof BlockTargetWithUserPage && $data['Watch'] ) {
					$this->watchlistManager->addWatchIgnoringRights(
						$form->getUser(),
						Title::newFromPageReference( $this->target->getUserPage() )
					);
				}
				$status = $this->unblockUserFactory->newUnblockUser(
					$this->target,
					$form->getContext()->getAuthority(),
					$data['Reason'],
					$data['Tags'] ?? []
				)->unblock();

				if ( $status->hasMessage( 'ipb_cant_unblock_multiple_blocks' ) ) {
					// Add additional message sending users to [[Special:Block/Username]]
					$status->error( 'unblock-error-multiblocks', $this->target );
				}
				return $status;
			} )
			->setSubmitTextMsg( 'ipusubmit' )
			->addPreHtml( $this->msg( 'unblockiptext' )->parseAsBlock() );

		if ( $this->target ) {
			$userPage = $this->target->getLogPage();
			$targetName = (string)$this->target;
			// Get relevant extracts from the block and suppression logs, if possible
			$logExtract = '';
			LogEventsList::showLogExtract(
				$logExtract,
				'block',
				$userPage,
				'',
				[
					'lim' => 10,
					'msgKey' => [
						'unblocklog-showlog',
						$targetName,
					],
					'showIfEmpty' => false
				]
			);
			if ( $logExtract !== '' ) {
				$form->addPostHtml( $logExtract );
			}

			// Add suppression block entries if allowed
			if ( $this->getAuthority()->isAllowed( 'suppressionlog' ) ) {
				$logExtract = '';
				LogEventsList::showLogExtract(
					$logExtract,
					'suppress',
					$userPage,
					'',
					[
						'lim' => 10,
						'conds' => [ 'log_action' => [ 'block', 'reblock', 'unblock' ] ],
						'msgKey' => [
							'unblocklog-showsuppresslog',
							$targetName,
						],
						'showIfEmpty' => false
					]
				);
				if ( $logExtract !== '' ) {
					$form->addPostHtml( $logExtract );
				}
			}
		}

		if ( $form->show() ) {
			$msgsByType = [
				Block::TYPE_IP => 'unblocked-ip',
				Block::TYPE_USER => 'unblocked',
				Block::TYPE_RANGE => 'unblocked-range',
				Block::TYPE_AUTO => 'unblocked-id'
			];
			$out->addWikiMsg(
				$msgsByType[$this->target->getType()],
				wfEscapeWikiText( (string)$this->target )
			);
		}
	}

	/**
	 * Get the target and type, given the request and the subpage parameter.
	 * Several parameters are handled for backwards compatability. 'wpTarget' is
	 * prioritized, since it matches the HTML form.
	 *
	 * @param string|null $par Subpage parameter
	 * @param WebRequest $request
	 * @return BlockTarget|null
	 */
	private function getTargetFromRequest( ?string $par, WebRequest $request ) {
		$possibleTargets = [
			$request->getVal( 'wpTarget', null ),
			$par,
			$request->getVal( 'ip', null ),
			// B/C @since 1.18
			$request->getVal( 'wpBlockAddress', null ),
		];
		foreach ( $possibleTargets as $possibleTarget ) {
			$target = $this->blockTargetFactory->newFromString( $possibleTarget );
			// If type is not null then target is valid
			if ( $target ) {
				break;
			}
		}
		return $target;
	}

	protected function getFields() {
		$fields = [
			'Target' => [
				'type' => 'text',
				'label-message' => 'unblock-target-label',
				'autofocus' => true,
				'size' => '45',
				'required' => true,
				'cssclass' => 'mw-autocomplete-user', // used by mediawiki.userSuggest
			],
			'Name' => [
				'type' => 'info',
				'label-message' => 'unblock-target-label',
			],
			'Reason' => [
				'type' => 'text',
				'label-message' => 'ipbreason',
			]
		];

		if ( $this->block instanceof Block ) {
			$type = $this->block->getType();
			$targetName = $this->block->getTargetName();

			// Autoblocks are logged as "autoblock #123 because the IP was recently used by
			// User:Foo, and we've just got any block, auto or not, that applies to a target
			// the user has specified.  Someone could be fishing to connect IPs to autoblocks,
			// so don't show any distinction between unblocked IPs and autoblocked IPs
			if ( $type == Block::TYPE_AUTO && $this->target->getType() == Block::TYPE_IP ) {
				$fields['Target']['default'] = (string)$this->target;
				unset( $fields['Name'] );
			} else {
				$fields['Target']['default'] = $targetName;
				$fields['Target']['type'] = 'hidden';
				switch ( $type ) {
					case Block::TYPE_IP:
						$fields['Name']['default'] = $this->getLinkRenderer()->makeKnownLink(
							$this->getSpecialPageFactory()->getTitleForAlias( 'Contributions/' . $targetName ),
							$targetName
						);
						$fields['Name']['raw'] = true;
						break;
					case Block::TYPE_USER:
						$fields['Name']['default'] = $this->getLinkRenderer()->makeLink(
							new TitleValue( NS_USER, $targetName ),
							$targetName
						);
						$fields['Name']['raw'] = true;
						break;

					case Block::TYPE_RANGE:
						$fields['Name']['default'] = $targetName;
						break;

					case Block::TYPE_AUTO:
						// Don't expose the real target of the autoblock
						$fields['Name']['default'] = $this->block->getRedactedName();
						$fields['Name']['raw'] = true;
						$fields['Target']['default'] = $this->block->getRedactedTarget()->toString();
						break;
				}
				// Target is hidden, so the reason is the first element
				$fields['Target']['autofocus'] = false;
				$fields['Reason']['autofocus'] = true;
			}
		} else {
			$fields['Target']['default'] = $this->target;
			unset( $fields['Name'] );
		}
		// Watchlist their user page? (Only if user is logged in)
		if ( $this->getUser()->isRegistered() ) {
			$fields['Watch'] = [
				'type' => 'check',
				'label-message' => 'ipbwatchuser',
			];
		}

		return $fields;
	}

	/**
	 * Return an array of subpages beginning with $search that this special page will accept.
	 *
	 * @param string $search Prefix to search for
	 * @param int $limit Maximum number of results to return (usually 10)
	 * @param int $offset Number of results to skip (usually 0)
	 * @return string[] Matching subpages
	 */
	public function prefixSearchSubpages( $search, $limit, $offset ) {
		$search = $this->userNameUtils->getCanonical( $search );
		if ( !$search ) {
			// No prefix suggestion for invalid user
			return [];
		}
		// Autocomplete subpage as user list - public to allow caching
		return $this->userNamePrefixSearch
			->search( UserNamePrefixSearch::AUDIENCE_PUBLIC, $search, $limit, $offset );
	}

	protected function getGroupName() {
		return 'users';
	}
}

/**
 * Retain the old class name for backwards compatibility.
 * @deprecated since 1.41
 */
class_alias( SpecialUnblock::class, 'SpecialUnblock' );
