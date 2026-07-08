<?php

namespace MediaWiki\Extension\AbuseFilter\Hooks\Handlers;

use MediaWiki\Actions\Hook\HistoryPageToolLinksHook;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\Special\SpecialAbuseLog;
use MediaWiki\Linker\Hook\HtmlPageLinkRendererEndHook;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Skin\Hook\UndeletePageToolLinksHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Specials\Hook\ContributionsToolLinksHook;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleValue;

class ToolLinksHandler implements
	ContributionsToolLinksHook,
	HistoryPageToolLinksHook,
	UndeletePageToolLinksHook,
	HtmlPageLinkRendererEndHook
{
	public function __construct( private readonly AbuseFilterPermissionManager $afPermManager ) {
	}

	/**
	 * @param int $id
	 * @param Title $nt
	 * @param array &$tools
	 * @param SpecialPage $sp for context
	 */
	public function onContributionsToolLinks( $id, Title $nt, array &$tools, SpecialPage $sp ) {
		$username = $nt->getText();

		if ( $this->afPermManager->canViewAbuseLog( $sp->getAuthority() ) ) {
			$linkRenderer = $sp->getLinkRenderer();
			$tools['abuselog'] = $linkRenderer->makeLink(
				$this->getSpecialPageTitle(),
				$sp->msg( 'abusefilter-log-linkoncontribs' )->text(),
				[ 'title' => $sp->msg( 'abusefilter-log-linkoncontribs-text',
					$username )->text(), 'class' => 'mw-contributions-link-abuse-log' ],
				[ 'wpSearchUser' => $username ]
			);
		}
	}

	/**
	 * @param IContextSource $context
	 * @param LinkRenderer $linkRenderer
	 * @param string[] &$links
	 */
	public function onHistoryPageToolLinks( IContextSource $context, LinkRenderer $linkRenderer, array &$links ) {
		if ( $this->afPermManager->canViewAbuseLog( $context->getAuthority() ) ) {
			$links[] = $linkRenderer->makeLink(
				$this->getSpecialPageTitle(),
				$context->msg( 'abusefilter-log-linkonhistory' )->text(),
				[ 'title' => $context->msg( 'abusefilter-log-linkonhistory-text' )->text() ],
				[ 'wpSearchTitle' => $context->getTitle()->getPrefixedText() ]
			);
		}
	}

	/**
	 * @param IContextSource $context
	 * @param LinkRenderer $linkRenderer
	 * @param string[] &$links
	 */
	public function onUndeletePageToolLinks( IContextSource $context, LinkRenderer $linkRenderer, array &$links ) {
		$show = $this->afPermManager->canViewAbuseLog( $context->getAuthority() );
		$action = $context->getRequest()->getVal( 'action', 'view' );

		// For 'history action', the link would be added by HistoryPageToolLinks hook.
		if ( $show && $action !== 'history' ) {
			$links[] = $linkRenderer->makeLink(
				$this->getSpecialPageTitle(),
				$context->msg( 'abusefilter-log-linkonundelete' )->text(),
				[ 'title' => $context->msg( 'abusefilter-log-linkonundelete-text' )->text() ],
				[ 'wpSearchTitle' => $context->getTitle()->getPrefixedText() ]
			);
		}
	}

	/**
	 * @codeCoverageIgnore Helper for tests
	 * @return LinkTarget
	 */
	private function getSpecialPageTitle(): LinkTarget {
		return defined( 'MW_PHPUNIT_TEST' )
			? new TitleValue( NS_SPECIAL, SpecialAbuseLog::PAGE_NAME )
			: SpecialPage::getTitleFor( SpecialAbuseLog::PAGE_NAME );
	}

	/** @inheritDoc */
	public function onHtmlPageLinkRendererEnd( $linkRenderer, $target, $isKnown, &$text, &$attribs, &$ret ) {
		if ( str_contains( $attribs['class'], 'mw-abusefilter-log-missinguserlink' ) ) {
			$attribs['title'] = wfMessage(
				'abusefilter-log-missinguserlink-title',
				Title::newFromLinkTarget( $target )->getPrefixedText()
			)->inUserLanguage()->text();
		}
		return true;
	}

}
