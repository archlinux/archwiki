<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CheckUser\CheckUser\Pagers\AbstractCheckUserPager;
use MediaWiki\Extension\CheckUser\CheckUser\Pagers\CheckUsernameResultInterface;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use OOUI\HtmlSnippet as OOUIHtmlSnippet;
use OOUI\MessageWidget;
use Wikimedia\Codex\Component\HtmlSnippet as CodexHtmlSnippet;
use Wikimedia\Codex\Utility\Codex;

/**
 * Renders message components for Suggested Investigations
 *
 * @since 1.46
 */
class SuggestedInvestigationsMessageRenderer {

	/**
	 * Apache's maximum GET URL limit is circa 8k
	 */
	private const MAX_GET_URL_LENGTH = 8000;

	public function __construct(
		private readonly SuggestedInvestigationsCaseLookupService $caseLookupService,
		private readonly Codex $codex,
	) {
	}

	public function getOpenCasesNotice(
		AbstractCheckUserPager $pager,
		IContextSource $context,
		LinkRenderer $linkRenderer,
	): string {
		if ( !$pager instanceof CheckUsernameResultInterface ) {
			return '';
		}

		$userNames = $this->getUserNamesWithOpenCases( $pager );
		if ( $userNames === [] ) {
			return '';
		}

		$link = $this->buildLinkOrForm( $userNames, $context, $linkRenderer );
		$message = $context->msg( 'checkuser-ip-results-suggestedinvestigations-notice' )
			->rawParams( $link )
			->parse();

		return ( new MessageWidget( [
			'type' => 'notice',
			'label' => new OOUIHtmlSnippet( $message ),
		] ) )->toString();
	}

	private function getUserNamesWithOpenCases( CheckUsernameResultInterface $pager ): array {
		if ( !$this->caseLookupService->areSuggestedInvestigationsEnabled() ) {
			return [];
		}

		$userIdToName = $pager->getResultUsernameMap();
		if ( $userIdToName === [] ) {
			return [];
		}

		$usersWithCases = $this->caseLookupService
			->getUserIdsWithCases( array_keys( $userIdToName ), [ CaseStatus::Open ] );

		return array_values( array_intersect_key( $userIdToName, array_flip( $usersWithCases ) ) );
	}

	private function buildLinkOrForm( array $userNames, IContextSource $context, LinkRenderer $linkRenderer ): string {
		$title = SpecialPage::getTitleFor( 'SuggestedInvestigations' );
		$label = $context->msg( 'checkuser-ip-results-suggestedinvestigations-notice-link' )->text();

		if ( strlen( $title->getLocalURL( [ 'username' => $userNames ] ) ) <= self::MAX_GET_URL_LENGTH ) {
			return $linkRenderer->makeKnownLink( $title, $label, [], [ 'username' => $userNames ] );
		}

		return $this->buildForm( $title, $label, $userNames, $context );
	}

	/**
	 * Renders a POST form with hidden username[] inputs and a submit button styled as a link.
	 */
	private function buildForm( Title $title, string $label, array $userNames, IContextSource $context ): string {
		$csrfToken = Html::hidden( 'wpEditToken', $context->getCsrfTokenSet()->getToken()->toString() );

		$hiddenInputs = implode( '', array_map(
			static fn ( string $name ) => Html::hidden( 'username[]', $name ),
			$userNames
		) );

		$submitButton = Html::element( 'button', [ 'type' => 'submit' ], $label );

		return Html::rawElement(
			'form',
			[
				'method' => 'post',
				'action' => $title->getLocalURL(),
				'class' => 'mw-checkuser-suggestedinvestigations-link-form',
			],
			$csrfToken . $hiddenInputs . $submitButton
		);
	}

	/**
	 * Returns the HTML for a Codex warning message with the provided content that can be
	 * dismissed by the user.
	 *
	 * @return string HTML
	 */
	public function getUserDismissableWarning(
		string $warningMessageHtml,
		string $warningClass
	): string {
		// Make a message component that has the dismiss button, which we implement using our
		// own JS because we cannot infuse CSS-only components into Vue components.
		$warningMessageHtml .= $this->codex->button()
			->setIconOnly( true )
			->setIconClass( 'mw-checkuser-suggestedinvestigations-icon--close' )
			->setWeight( 'quiet' )
			->setAttributes( [
				'class' => 'cdx-message__dismiss-button ' .
					'ext-checkuser-suggestedinvestigations-warning-dismiss',
			] )
			->build()
			->getHtml();

		return $this->codex->message()
			->setType( 'warning' )
			->setContentHtml( new CodexHtmlSnippet( $warningMessageHtml, [] ) )
			->setAttributes( [
				'class' => "$warningClass cdx-message--user-dismissable "
					. 'ext-checkuser-suggestedinvestigations-dismissable-warning',
			] )
			->build()
			->getHtml();
	}
}
