<?php

namespace MediaWiki\Extension\DiscussionTools\Tests;

use Language;
use MediaWiki\Extension\DiscussionTools\CommentFormatter;
use MediaWiki\Extension\DiscussionTools\CommentParser;
use MediaWiki\User\UserIdentity;
use MWTimestamp;

class MockCommentFormatter extends CommentFormatter {

	public static CommentParser $parser;

	/**
	 * @return CommentParser
	 */
	protected static function getParser(): CommentParser {
		return static::$parser;
	}

	/**
	 * @inheritDoc
	 */
	public static function getSignatureRelativeTime(
		MWTimestamp $timestamp, Language $lang, UserIdentity $user
	): string {
		// Relative times can't be used in tests, so just return a plain timestamp
		return $timestamp->getTimestamp();
	}
}
