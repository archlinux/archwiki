<?php

namespace MediaWiki\Rest;

/**
 * Central definition of HTTP response headers and their OpenAPI schemas
 * for REST Handlers.
 */
class ResponseHeaders {

	/**
	 * Header name constants
	 */

	public const CACHE_CONTROL = 'Cache-Control';
	public const CONTENT_LANGUAGE = 'Content-Language';
	public const CONTENT_TYPE = 'Content-Type';
	public const DEPRECATION = 'Deprecation';
	public const ETAG = 'ETag';
	public const EXPIRES = 'Expires';
	public const LAST_MODIFIED = 'Last-Modified';
	public const LINK = 'Link';
	public const LOCATION = 'Location';
	public const MEDIAWIKI_REVISION_ID = 'X-MediaWiki-Revision-Id';
	public const REQUEST_ID = 'X-Request-Id';
	public const VARY = 'Vary';

	/**
	 * OpenAPI response header definitions for descriptions and schemas
	 */
	public const RESPONSE_HEADER_DEFINITIONS = [
		self::CACHE_CONTROL => [
			'messageKey' => 'rest-responseheader-desc-cachecontrol',
			'schema' => [
				'type' => 'string'
			]
		],
		self::CONTENT_LANGUAGE => [
			'messageKey' => 'rest-responseheader-desc-contentlanguage',
			'schema' => [
				'type' => 'string'
			]
		],
		self::CONTENT_TYPE => [
			'messageKey' => 'rest-responseheader-desc-contenttype',
			'schema' => [
				'type' => 'string'
			]
		],
		self::DEPRECATION => [
			'messageKey' => 'rest-responseheader-desc-deprecation',
			'schema' => [
				'type' => 'string'
			]
		],
		self::ETAG => [
			'messageKey' => 'rest-responseheader-desc-etag',
			'schema' => [
				'type' => 'string'
			]
		],
		self::EXPIRES => [
			'messageKey' => 'rest-responseheader-desc-expires',
			'schema' => [
				'type' => 'string',
				'format' => 'date-time'
			]
		],
		self::LAST_MODIFIED => [
			'messageKey' => 'rest-responseheader-desc-lastmodified',
			'schema' => [
				'type' => 'string',
				'format' => 'date-time'
			]
		],
		self::LINK => [
			'messageKey' => 'rest-responseheader-desc-link',
			'schema' => [
				'type' => 'string',
			]
		],
		self::LOCATION => [
			'messageKey' => 'rest-responseheader-desc-location',
			'schema' => [
				'type' => 'string',
			]
		],
		self::MEDIAWIKI_REVISION_ID => [
			'messageKey' => 'rest-responseheader-desc-mediawikirevisionid',
			'schema' => [
				'type' => 'string'
			]
		],
		self::REQUEST_ID => [
			'messageKey' => 'rest-responseheader-desc-requestid',
			'schema' => [
				'type' => 'string'
			]
		],
		self::VARY => [
			'messageKey' => 'rest-responseheader-desc-vary',
			'schema' => [
				'type' => 'string'
			]
		]
	];

	private function __construct() {
	}
}
