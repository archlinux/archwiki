<?php

namespace MediaWiki\Extension\AbuseFilter\BlockedDomains;

use MediaWiki\Context\IContextSource;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleValue;
use Wikimedia\Assert\Assert;
use Wikimedia\Message\MessageSpecifier;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IDBAccessObject;

class BlockedDomainEditor {

	private IContextSource $context;
	private Title $rootTitle;
	private WANObjectCache $wanCache;
	private LinkRenderer $linkRenderer;
	private IBlockedDomainStorage $blockedDomainStorage;
	private BlockedDomainValidator $blockedDomainValidator;

	public function __construct(
		IContextSource $context,
		Title $rootTitle,
		WANObjectCache $wanCache,
		LinkRenderer $linkRenderer,
		IBlockedDomainStorage $blockedDomainStorage,
		BlockedDomainValidator $blockedDomainValidator
	) {
		$this->context = $context;
		$this->rootTitle = $rootTitle;
		$this->wanCache = $wanCache;
		$this->linkRenderer = $linkRenderer;
		$this->blockedDomainStorage = $blockedDomainStorage;
		$this->blockedDomainValidator = $blockedDomainValidator;
	}

	/**
	 * Wrapper around wfMessage that sets the current context.
	 *
	 * @param string|string[]|MessageSpecifier $key
	 * @param mixed ...$params
	 * @return Message
	 * @see wfMessage
	 */
	public function msg( $key, ...$params ) {
		return $this->context->msg( $key, ...$params );
	}

	public function execute( ?string $par ) {
		if ( !$this->context->getConfig()->get( 'AbuseFilterEnableBlockedExternalDomain' ) ) {
			throw new ErrorPageError( 'abusefilter-disabled', 'disabledspecialpage-disabled' );
		}
		$this->context->getOutput()->addHelpLink( 'Manual:BlockedExternalDomains' );

		$request = $this->context->getRequest();
		switch ( $par ) {
			case 'remove':
				$this->showRemoveForm( $request->getVal( 'domain' ) );
				break;
			case 'add':
				$this->showAddForm( $request->getVal( 'domain' ) );
				break;
			default:
				$this->showList();
				break;
		}
	}

	private function showList() {
		$out = $this->context->getOutput();
		$out->setPageTitleMsg( $this->msg( 'abusefilter-blocked-domains-title' ) );
		$out->wrapWikiMsg( "$1", 'abusefilter-blocked-domains-intro' );
		$out->addModuleStyles( 'mediawiki.codex.messagebox.styles' );

		// Direct editing of this page is blocked via EditPermissionHandler
		$userCanManage = $this->context->getAuthority()->isAllowed( 'abusefilter-modify-blocked-external-domains' );

		// Show form to add a blocked domain
		if ( $userCanManage ) {
			$fields = [
				'Domain' => [
					'type' => 'text',
					'label' => $this->msg( 'abusefilter-blocked-domains-domain' )->plain(),
					'required' => true,
				],
				'Notes' => [
					'type' => 'text',
					'maxlength' => 255,
					'label' => $this->msg( 'abusefilter-blocked-domains-notes' )->plain(),
					'size' => 250,
				],
			];

			HTMLForm::factory( 'ooui', $fields, $this->context )
				->setAction( $this->getPageTitle( 'add' )->getLocalURL() )
				->setWrapperLegendMsg( 'abusefilter-blocked-domains-add-heading' )
				->setHeaderHtml( $this->msg( 'abusefilter-blocked-domains-add-explanation' )->parseAsBlock() )
				->setSubmitCallback( [ $this, 'processAddForm' ] )
				->setSubmitTextMsg( 'abusefilter-blocked-domains-add-submit' )
				->show();

			if ( $out->getRedirect() !== '' ) {
				return;
			}
		}

		$res = $this->blockedDomainStorage->loadConfig( IDBAccessObject::READ_LATEST );
		if ( !$res->isGood() ) {
			return;
		}

		$content = Html::element( 'th', [], $this->msg( 'abusefilter-blocked-domains-domain-header' )->text() ) .
			Html::element( 'th', [], $this->msg( 'abusefilter-blocked-domains-notes-header' )->text() );
		if ( $userCanManage ) {
			$content .= Html::element(
				'th',
				[],
				$this->msg( 'abusefilter-blocked-domains-addedby-header' )->text()
			);
			$content .= Html::element(
				'th',
				[ 'class' => 'unsortable' ],
				$this->msg( 'abusefilter-blocked-domains-actions-header' )->text()
			);
		}
		$thead = Html::rawElement( 'tr', [], $content );

		// Parsing each row is expensive, put it behind WAN cache
		// with md5 checksum, we make sure changes to the domain list
		// invalidate the cache
		$cacheKey = $this->wanCache->makeKey(
			'abusefilter-blockeddomains-rows',
			md5( json_encode( $res->getValue() ) ),
			(int)$userCanManage
		);
		$tbody = $this->wanCache->getWithSetCallback(
			$cacheKey,
			WANObjectCache::TTL_DAY,
			function () use ( $res, $userCanManage ) {
				$tbody = '';
				foreach ( $res->getValue() as $domain ) {
					$tbody .= $this->doDomainRow( $domain, $userCanManage );
				}
				return $tbody;
			}
		);

		$out->addModuleStyles( [ 'jquery.tablesorter.styles', 'mediawiki.pager.styles' ] );
		$out->addModules( 'jquery.tablesorter' );
		$out->addHTML( Html::rawElement(
			'table',
			[ 'class' => 'mw-datatable sortable' ],
			Html::rawElement( 'thead', [], $thead ) .
			Html::rawElement( 'tbody', [], $tbody )
		) );
	}

	/**
	 * Show the row in the table
	 *
	 * @param array $domain domain data
	 * @param bool $showManageActions whether to add manage actions
	 * @return string HTML for the row
	 */
	private function doDomainRow( $domain, $showManageActions ) {
		$newRow = Html::rawElement( 'td', [], Html::element( 'code', [], $domain['domain'] ) );

		$newRow .= Html::rawElement( 'td', [],
			$this->context->getOutput()->parseInlineAsInterface( $domain['notes'] )
		);

		if ( $showManageActions ) {
			if ( isset( $domain['addedBy'] ) ) {
				$addedBy = $this->linkRenderer->makeLink(
					new TitleValue( 3, $domain['addedBy'] ),
					$domain['addedBy']
				);
			} else {
				$addedBy = '';
			}
			$newRow .= Html::rawElement( 'td', [], $addedBy );

			$actionLink = $this->linkRenderer->makeKnownLink(
				$this->getPageTitle( 'remove' ),
				$this->msg( 'abusefilter-blocked-domains-remove' )->text(),
				[],
				[ 'domain' => $domain['domain'] ]
			);
			$newRow .= Html::rawElement( 'td', [], $actionLink );
		}

		return Html::rawElement( 'tr', [], $newRow ) . "\n";
	}

	/**
	 * Show form for removing a domain from the blocked list
	 *
	 * @param string $domain
	 * @return void
	 */
	private function showRemoveForm( $domain ) {
		if ( !$this->context->getAuthority()->isAllowed( 'editsitejson' ) ) {
			throw new PermissionsError( 'editsitejson' );
		}

		$out = $this->context->getOutput();
		$out->setPageTitleMsg( $this->msg( 'abusefilter-blocked-domains-remove-title' ) );
		$out->addBacklinkSubtitle( $this->getPageTitle() );

		$preText = $this->msg( 'abusefilter-blocked-domains-remove-explanation-initial', $domain )->parseAsBlock();

		$fields = [
			'Domain' => [
				'type' => 'text',
				'label' => $this->msg( 'abusefilter-blocked-domains-domain' )->plain(),
				'required' => true,
				'default' => $domain,
			],
			'Notes' => [
				'type' => 'text',
				'maxlength' => 255,
				'label' => $this->msg( 'abusefilter-blocked-domains-notes' )->plain(),
				'size' => 250,
			],
		];

		HTMLForm::factory( 'ooui', $fields, $this->context )
			->setAction( $this->getPageTitle( 'remove' )->getLocalURL() )
			->setSubmitCallback( function ( $data, $form ) {
				return $this->processRemoveForm( $data, $form );
			} )
			->setSubmitTextMsg( 'abusefilter-blocked-domains-remove-submit' )
			->setSubmitDestructive()
			->addPreHtml( $preText )
			->show();
	}

	/**
	 * Process the form for removing a domain from the blocked list
	 *
	 * @param array $data request data
	 * @param HTMLForm $form
	 * @return bool whether the action was successful or not
	 */
	public function processRemoveForm( array $data, HTMLForm $form ) {
		$out = $form->getContext()->getOutput();
		$domain = $this->blockedDomainValidator->validateDomain( $data['Domain'] );
		if ( $domain === false ) {
			$out->addHTML( Html::errorBox(
				$this->msg( 'http-invalid-url' )->plaintextParams( $data['Domain'] )->parse()
			) );
			return false;
		}

		$status = $this->blockedDomainStorage->removeDomain(
			$domain,
			$data['Notes'] ?? '',
			$this->context->getAuthority()
		);

		if ( !$status->isGood() ) {
			foreach ( $status->getMessages() as $msg ) {
				$out->addHTML( Html::errorBox( $this->msg( $msg )->parse() ) );
			}
			return false;
		}

		$out->redirect( $this->getPageTitle()->getLocalURL() );
		return true;
	}

	/**
	 * Show form for adding a domain to the blocked list
	 *
	 * @param string $domain
	 * @return void
	 */
	private function showAddForm( $domain ) {
		if ( !$this->context->getAuthority()->isAllowed( 'editsitejson' ) ) {
			throw new PermissionsError( 'editsitejson' );
		}

		$out = $this->context->getOutput();
		$out->setPageTitleMsg( $this->msg( "abusefilter-blocked-domains-add-heading" ) );
		$out->addBacklinkSubtitle( $this->getPageTitle() );

		$preText = $this->msg( "abusefilter-blocked-domains-add-explanation", $domain )->parseAsBlock();

		$fields = [
			'Domain' => [
				'type' => 'text',
				'label' => $this->msg( 'abusefilter-blocked-domains-domain' )->plain(),
				'required' => true,
				'default' => $domain,
			],
			'Notes' => [
				'type' => 'text',
				'maxlength' => 255,
				'label' => $this->msg( 'abusefilter-blocked-domains-notes' )->plain(),
				'size' => 250,
			],
		];

		HTMLForm::factory( 'ooui', $fields, $this->context )
			->setAction( $this->getPageTitle( 'add' )->getLocalURL() )
			->setSubmitCallback( function ( $data, $form ) {
				return $this->processAddForm( $data, $form );
			} )
			->setSubmitTextMsg( "abusefilter-blocked-domains-add-submit" )
			->addPreHtml( $preText )
			->show();
	}

	/**
	 * Process the form for adding a domain to the blocked list
	 *
	 * @param array $data request data
	 * @param HTMLForm $form
	 * @return bool whether the action was successful or not
	 */
	private function processAddForm( array $data, HTMLForm $form ) {
		$out = $form->getContext()->getOutput();

		$domain = $this->blockedDomainValidator->validateDomain( $data['Domain'] );
		if ( $domain === false ) {
			$out->addHTML( Html::errorBox(
				$this->msg( 'http-invalid-url' )->plaintextParams( $data['Domain'] )->parse()
			) );
			return false;
		}
		$status = $this->blockedDomainStorage->addDomain(
			$domain,
			$data['Notes'] ?? '',
			$this->context->getAuthority()
		);

		if ( !$status->isGood() ) {
			foreach ( $status->getMessages() as $msg ) {
				$out->addHTML( Html::errorBox( $this->msg( $msg )->parse() ) );
			}
			return false;
		}

		$out->redirect( $this->getPageTitle()->getLocalURL() );
		return true;
	}

	private function getPageTitle( ?string $subpage = null ): Title {
		$title = $this->rootTitle;
		if ( $subpage !== null ) {
			$title = $title->getSubpage( $subpage );
		}
		Assert::invariant( $title !== null, 'A valid title is expected' );
		return $title;
	}
}
