<?php

namespace MediaWiki\Extension\DiscussionTools;

use IContextSource;
use InvalidArgumentException;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Linker\LinkRenderer;
use OOUI;
use TablePager;
use Title;

class TopicSubscriptionsPager extends TablePager {

	/**
	 * Map of our field names (see ::getFieldNames()) to the column names actually used for
	 * pagination. This is needed to ensure that the values are unique, and that pagination
	 * won't get "stuck" when e.g. 50 subscriptions are all created within a second.
	 */
	private const INDEX_FIELDS = [
		// The auto-increment ID will almost always have the same order as sub_created
		// and the field already has an index.
		'_topic' => [ 'sub_id' ],
		'sub_created' => [ 'sub_id' ],
		// TODO Add indexes that cover these fields to enable sorting by them
		// 'sub_state' => [ 'sub_state', 'sub_item' ],
		// 'sub_created' => [ 'sub_created', 'sub_item' ],
		// 'sub_notified' => [ 'sub_notified', 'sub_item' ],
	];

	private LinkBatchFactory $linkBatchFactory;

	public function __construct(
		IContextSource $context,
		LinkRenderer $linkRenderer,
		LinkBatchFactory $linkBatchFactory
	) {
		parent::__construct( $context, $linkRenderer );
		$this->linkBatchFactory = $linkBatchFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function preprocessResults( $result ) {
		$lb = $this->linkBatchFactory->newLinkBatch();
		foreach ( $result as $row ) {
			$lb->add( $row->sub_namespace, $row->sub_title );
		}
		$lb->execute();
	}

	/**
	 * @inheritDoc
	 */
	protected function getFieldNames() {
		return [
			'_topic' => $this->msg( 'discussiontools-topicsubscription-pager-topic' )->text(),
			'_page' => $this->msg( 'discussiontools-topicsubscription-pager-page' )->text(),
			'sub_created' => $this->msg( 'discussiontools-topicsubscription-pager-created' )->text(),
			'sub_notified' => $this->msg( 'discussiontools-topicsubscription-pager-notified' )->text(),
			'_unsubscribe' => $this->msg( 'discussiontools-topicsubscription-pager-actions' )->text(),
		];
	}

	/**
	 * @inheritDoc
	 */
	public function formatValue( $field, $value ) {
		/** @var stdClass $row */
		$row = $this->mCurrentRow;
		$linkRenderer = $this->getLinkRenderer();

		switch ( $field ) {
			case '_topic':
				$titleSection = Title::makeTitleSafe( $row->sub_namespace, $row->sub_title, $row->sub_section );
				return $linkRenderer->makeLink( $titleSection, $row->sub_section );

			case '_page':
				$title = Title::makeTitleSafe( $row->sub_namespace, $row->sub_title );
				return $linkRenderer->makeLink( $title, $title->getPrefixedText() );

			case 'sub_created':
				return htmlspecialchars( $this->getLanguage()->userTimeAndDate( $value, $this->getUser() ) );

			case 'sub_notified':
				return $value ?
					htmlspecialchars( $this->getLanguage()->userTimeAndDate( $value, $this->getUser() ) ) :
					$this->msg( 'discussiontools-topicsubscription-pager-notified-never' )->escaped();

			case '_unsubscribe':
				$title = Title::makeTitleSafe( $row->sub_namespace, $row->sub_title );
				return (string)new OOUI\ButtonWidget( [
					'label' => $this->msg( 'discussiontools-topicsubscription-pager-unsubscribe-button' )->text(),
					'classes' => [ 'ext-discussiontools-special-unsubscribe-button' ],
					'framed' => false,
					'flags' => [ 'destructive' ],
					'data' => [
						'item' => $row->sub_item,
						'title' => $title->getPrefixedText(),
					],
					'href' => $title->getLinkURL( [
						'action' => 'dtunsubscribe',
						'commentname' => $row->sub_item,
					] ),
					'infusable' => true,
				] );

			default:
				throw new InvalidArgumentException( "Unknown field '$field'" );
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function getCellAttrs( $field, $value ) {
		$attrs = parent::getCellAttrs( $field, $value );
		if ( $field === '_unsubscribe' ) {
			$attrs['style'] = 'text-align: center;';
		}
		return $attrs;
	}

	/**
	 * @inheritDoc
	 */
	public function getQueryInfo() {
		return [
			'tables' => [
				'discussiontools_subscription',
			],
			'fields' => [
				'sub_id',
				'sub_item',
				'sub_namespace',
				'sub_title',
				'sub_section',
				'sub_created',
				'sub_notified',
			],
			'conds' => [
				'sub_user' => $this->getUser()->getId(),
				'sub_state != ' . SubscriptionStore::STATE_UNSUBSCRIBED,
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getDefaultSort() {
		return 'sub_created';
	}

	/**
	 * @inheritDoc
	 */
	public function getDefaultDirections() {
		return static::DIR_DESCENDING;
	}

	/**
	 * @inheritDoc
	 */
	public function getIndexField() {
		return [ static::INDEX_FIELDS[$this->mSort] ];
	}

	/**
	 * @inheritDoc
	 */
	protected function isFieldSortable( $field ) {
		// Hide the sort button for "Topic" as it is more accurately shown as "Created"
		return isset( static::INDEX_FIELDS[$field] ) && $field !== '_topic';
	}
}
