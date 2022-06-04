<?php

namespace MediaWiki\Extension\AbuseFilter\View;

use Diff;
use DifferenceEngine;
use IContextSource;
use Linker;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\Filter\ClosestFilterVersionNotFoundException;
use MediaWiki\Extension\AbuseFilter\Filter\FilterNotFoundException;
use MediaWiki\Extension\AbuseFilter\Filter\FilterVersionNotFoundException;
use MediaWiki\Extension\AbuseFilter\Filter\HistoryFilter;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWiki\Extension\AbuseFilter\SpecsFormatter;
use MediaWiki\Extension\AbuseFilter\TableDiffFormatterFullContext;
use MediaWiki\Linker\LinkRenderer;
use OOUI;
use TextContent;
use Xml;

class AbuseFilterViewDiff extends AbuseFilterView {
	/**
	 * @var HistoryFilter|null The old version of the filter
	 */
	public $oldVersion;
	/**
	 * @var HistoryFilter|null The new version of the filter
	 */
	public $newVersion;
	/**
	 * @var int|null The history ID of the next version, if any
	 */
	public $nextHistoryId;
	/**
	 * @var int|null The ID of the filter
	 */
	private $filter;
	/**
	 * @var SpecsFormatter
	 */
	private $specsFormatter;
	/**
	 * @var FilterLookup
	 */
	private $filterLookup;

	/**
	 * @param AbuseFilterPermissionManager $afPermManager
	 * @param SpecsFormatter $specsFormatter
	 * @param FilterLookup $filterLookup
	 * @param IContextSource $context
	 * @param LinkRenderer $linkRenderer
	 * @param string $basePageName
	 * @param array $params
	 */
	public function __construct(
		AbuseFilterPermissionManager $afPermManager,
		SpecsFormatter $specsFormatter,
		FilterLookup $filterLookup,
		IContextSource $context,
		LinkRenderer $linkRenderer,
		string $basePageName,
		array $params
	) {
		parent::__construct( $afPermManager, $context, $linkRenderer, $basePageName, $params );
		$this->specsFormatter = $specsFormatter;
		$this->specsFormatter->setMessageLocalizer( $this->getContext() );
		$this->filterLookup = $filterLookup;
	}

	/**
	 * Shows the page
	 */
	public function show() {
		$show = $this->loadData();
		$out = $this->getOutput();
		$out->enableOOUI();
		$out->addModuleStyles( [ 'oojs-ui.styles.icons-movement' ] );

		$links = [];
		if ( $this->filter ) {
			$links['abusefilter-history-backedit'] = $this->getTitle( $this->filter )->getFullURL();
			$links['abusefilter-diff-backhistory'] = $this->getTitle( "history/$this->filter" )->getFullURL();
		}

		foreach ( $links as $msg => $href ) {
			$links[$msg] = new OOUI\ButtonWidget( [
				'label' => $this->msg( $msg )->text(),
				'href' => $href
			] );
		}

		$backlinks = new OOUI\HorizontalLayout( [ 'items' => array_values( $links ) ] );
		$out->addHTML( $backlinks );

		if ( $show ) {
			$out->addHTML( $this->formatDiff() );
			// Next and previous change links
			$buttons = [];
			$oldHistoryID = $this->oldVersion->getHistoryID();
			if ( $this->filterLookup->getFirstFilterVersionID( $this->filter ) !== $oldHistoryID ) {
				// Create a "previous change" link if this isn't the first change of the given filter
				$href = $this->getTitle( "history/$this->filter/diff/prev/$oldHistoryID" )->getFullURL();
				$buttons[] = new OOUI\ButtonWidget( [
					'label' => $this->msg( 'abusefilter-diff-prev' )->text(),
					'href' => $href,
					'icon' => 'previous'
				] );
			}

			if ( $this->nextHistoryId !== null ) {
				// Create a "next change" link if this isn't the last change of the given filter
				$href = $this->getTitle( "history/$this->filter/diff/prev/$this->nextHistoryId" )->getFullURL();
				$buttons[] = new OOUI\ButtonWidget( [
					'label' => $this->msg( 'abusefilter-diff-next' )->text(),
					'href' => $href,
					'icon' => 'next'
				] );
			}

			if ( count( $buttons ) > 0 ) {
				$buttons = new OOUI\HorizontalLayout( [
					'items' => $buttons,
					'classes' => [ 'mw-abusefilter-history-buttons' ]
				] );
				$out->addHTML( $buttons );
			}
		}
	}

	/**
	 * @return bool
	 */
	public function loadData() {
		$oldSpec = $this->mParams[3];
		$newSpec = $this->mParams[4];

		if ( !is_numeric( $this->mParams[1] ) ) {
			$this->getOutput()->addWikiMsg( 'abusefilter-diff-invalid' );
			return false;
		}
		$this->filter = (int)$this->mParams[1];

		$this->oldVersion = $this->loadSpec( $oldSpec, $newSpec );
		$this->newVersion = $this->loadSpec( $newSpec, $oldSpec );

		if ( $this->oldVersion === null || $this->newVersion === null ) {
			$this->getOutput()->addWikiMsg( 'abusefilter-diff-invalid' );
			return false;
		}

		if ( !$this->afPermManager->canViewPrivateFilters( $this->getUser() ) &&
			( $this->oldVersion->isHidden() || $this->newVersion->isHidden() )
		) {
			$this->getOutput()->addWikiMsg( 'abusefilter-history-error-hidden' );
			return false;
		}

		try {
			$this->nextHistoryId = $this->filterLookup->getClosestVersion(
				$this->newVersion->getHistoryID(),
				$this->filter,
				FilterLookup::DIR_NEXT
			)->getHistoryID();
		} catch ( ClosestFilterVersionNotFoundException $_ ) {
			$this->nextHistoryId = null;
		}

		return true;
	}

	/**
	 * @param string $spec
	 * @param string $otherSpec
	 * @return HistoryFilter|null
	 */
	public function loadSpec( $spec, $otherSpec ): ?HistoryFilter {
		static $dependentSpecs = [ 'prev', 'next' ];
		static $cache = [];

		if ( isset( $cache[$spec] ) ) {
			return $cache[$spec];
		}

		$filterObj = null;
		if ( ( $spec === 'prev' || $spec === 'next' ) && !in_array( $otherSpec, $dependentSpecs ) ) {
			$other = $this->loadSpec( $otherSpec, $spec );

			if ( !$other ) {
				return null;
			}

			$dir = $spec === 'prev' ? FilterLookup::DIR_PREV : FilterLookup::DIR_NEXT;
			try {
				$filterObj = $this->filterLookup->getClosestVersion( $other->getHistoryID(), $this->filter, $dir );
			} catch ( ClosestFilterVersionNotFoundException $_ ) {
				$t = $this->getTitle( "history/$this->filter/item/" . $other->getHistoryID() );
				$this->getOutput()->redirect( $t->getFullURL() );
				return null;
			}
		}

		if ( $filterObj === null ) {
			try {
				if ( is_numeric( $spec ) ) {
					$filterObj = $this->filterLookup->getFilterVersion( (int)$spec );
				} elseif ( $spec === 'cur' ) {
					$filterObj = $this->filterLookup->getLastHistoryVersion( $this->filter );
				}
			} catch ( FilterNotFoundException | FilterVersionNotFoundException $_ ) {
			}
		}

		$cache[$spec] = $filterObj;
		return $cache[$spec];
	}

	/**
	 * @param string $timestamp
	 * @param int $history_id
	 * @return string
	 */
	public function formatVersionLink( $timestamp, $history_id ) {
		$text = $this->getLanguage()->userTimeAndDate( $timestamp, $this->getUser() );
		$title = $this->getTitle( "history/$this->filter/item/$history_id" );

		return $this->linkRenderer->makeLink( $title, $text );
	}

	/**
	 * @return string
	 */
	public function formatDiff() {
		$oldVersion = $this->oldVersion;
		$newVersion = $this->newVersion;

		// headings
		$oldLink = $this->formatVersionLink( $oldVersion->getTimestamp(), $oldVersion->getHistoryID() );
		$newLink = $this->formatVersionLink( $newVersion->getTimestamp(), $newVersion->getHistoryID() );

		$oldUserLink = Linker::userLink( $oldVersion->getUserID(), $oldVersion->getUserName() );
		$newUserLink = Linker::userLink( $newVersion->getUserID(), $newVersion->getUserName() );

		$headings = Xml::tags( 'th', null, $this->msg( 'abusefilter-diff-item' )->parse() );
		$headings .= Xml::tags( 'th', null,
			$this->msg( 'abusefilter-diff-version' )
				->rawParams( $oldLink, $oldUserLink )
				->params( $newVersion->getUserName() )
				->parse()
		);
		$headings .= Xml::tags( 'th', null,
			$this->msg( 'abusefilter-diff-version' )
				->rawParams( $newLink, $newUserLink )
				->params( $newVersion->getUserName() )
				->parse()
		);

		$headings = Xml::tags( 'tr', null, $headings );

		$body = '';
		// Basic info
		$info = $this->getDiffRow( 'abusefilter-edit-description', $oldVersion->getName(), $newVersion->getName() );
		$info .= $this->getDiffRow(
			'abusefilter-edit-group',
			$this->specsFormatter->nameGroup( $oldVersion->getGroup() ),
			$this->specsFormatter->nameGroup( $newVersion->getGroup() )
		);
		$info .= $this->getDiffRow(
			'abusefilter-edit-flags',
			$this->specsFormatter->formatFilterFlags( $oldVersion, $this->getLanguage() ),
			$this->specsFormatter->formatFilterFlags( $newVersion, $this->getLanguage() )
		);

		$info .= $this->getDiffRow( 'abusefilter-edit-notes', $oldVersion->getComments(), $newVersion->getComments() );

		if ( $info !== '' ) {
			$body .= $this->getHeaderRow( 'abusefilter-diff-info' ) . $info;
		}

		$pattern = $this->getDiffRow( 'abusefilter-edit-rules', $oldVersion->getRules(), $newVersion->getRules() );

		if ( $pattern !== '' ) {
			$body .= $this->getHeaderRow( 'abusefilter-diff-pattern' ) . $pattern;
		}

		$actions = $this->getDiffRow(
			'abusefilter-edit-consequences',
			$this->stringifyActions( $oldVersion->getActions() ) ?: [ '' ],
			$this->stringifyActions( $newVersion->getActions() ) ?: [ '' ]
		);

		if ( $actions !== '' ) {
			$body .= $this->getHeaderRow( 'abusefilter-edit-consequences' ) . $actions;
		}

		$html = "<table class='wikitable'>
			<thead>$headings</thead>
			<tbody>$body</tbody>
		</table>";

		$html = Xml::tags( 'h2', null, $this->msg( 'abusefilter-diff-title' )->parse() ) . $html;

		return $html;
	}

	/**
	 * @param string[][] $actions
	 * @return string[]
	 */
	private function stringifyActions( array $actions ): array {
		$lines = [];

		ksort( $actions );
		foreach ( $actions as $action => $parameters ) {
			$lines[] = $this->specsFormatter->formatAction( $action, $parameters, $this->getLanguage() );
		}

		return $lines;
	}

	/**
	 * @param string $msg
	 * @return string
	 */
	public function getHeaderRow( $msg ) {
		$html = $this->msg( $msg )->parse();
		$html = Xml::tags( 'th', [ 'colspan' => 3 ], $html );
		return Xml::tags( 'tr', [ 'class' => 'mw-abusefilter-diff-header' ], $html );
	}

	/**
	 * @param string $msg
	 * @param array|string $old
	 * @param array|string $new
	 * @return string
	 */
	public function getDiffRow( $msg, $old, $new ) {
		if ( !is_array( $old ) ) {
			$old = explode( "\n", TextContent::normalizeLineEndings( $old ) );
		}
		if ( !is_array( $new ) ) {
			$new = explode( "\n", TextContent::normalizeLineEndings( $new ) );
		}

		if ( $old === $new ) {
			return '';
		}

		$diffEngine = new DifferenceEngine( $this->getContext() );

		$diffEngine->showDiffStyle();

		$diff = new Diff( $old, $new );
		$formatter = new TableDiffFormatterFullContext();
		$formattedDiff = $diffEngine->addHeader( $formatter->format( $diff ), '', '' );

		return Xml::tags( 'tr', null,
			Xml::tags( 'th', null, $this->msg( $msg )->parse() ) .
			Xml::tags( 'td', [ 'colspan' => 2 ], $formattedDiff )
		) . "\n";
	}
}
