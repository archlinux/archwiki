<?php

/**
 * Copyright (C) 2011-2020 Wikimedia Foundation and others.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace MediaWiki\Rest\Handler;

use MediaWiki\Rest\Handler\Helper\ParsoidFormatHelper;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Parsoid\Parsoid;

/**
 * Handler for transforming content given in the request.
 * - /v1/transform/{from}/to/{format}
 * - /v1/transform/{from}/to/{format}/{title}
 * - /v1/transform/{from}/to/{format}/{title}/{revision}
 *
 * @see https://www.mediawiki.org/wiki/Parsoid/API#POST
 */
class TransformHandler extends ParsoidHandler {

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'from' => [ self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true, ],
			'format' => [ self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true, ],
			'title' => [ self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false, ],
			'revision' => [ self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false, ], ];
	}

	public function checkPreconditions() {
		// NOTE: disable all precondition checks.
		// If-(not)-Modified-Since is not supported by the /transform/ handler.
		// If-None-Match is not supported by the /transform/ handler.
		// If-Match for wt2html is handled in getRequestAttributes.
	}

	protected function &getRequestAttributes(): array {
		$attribs =& parent::getRequestAttributes();

		$request = $this->getRequest();

		// NOTE: If there is more than one ETag, this will break.
		//       We don't have a good way to test multiple ETag to see if one of them is a working stash key.
		$ifMatch = $request->getHeaderLine( 'If-Match' );

		if ( $ifMatch ) {
			$attribs['opts']['original']['etag'] = $ifMatch;
		}

		return $attribs;
	}

	/**
	 * Transform content given in the request from or to wikitext.
	 *
	 * @return Response
	 * @throws HttpException
	 */
	public function execute(): Response {
		$request = $this->getRequest();
		$from = $request->getPathParam( 'from' );
		$format = $request->getPathParam( 'format' );

		// XXX: Fallback to the default valid transforms in case the request is
		//      coming from a legacy client (restbase) that supports everything
		//      in the default valid transforms.
		$validTransformations = $this->getConfig()['transformations'] ?? ParsoidFormatHelper::VALID_TRANSFORM;

		if ( !isset( $validTransformations[$from] ) || !in_array( $format,
				$validTransformations[$from],
				true ) ) {
			throw new HttpException( "Invalid transform: {$from}/to/{$format}",
				404 );
		}
		$attribs = &$this->getRequestAttributes();
		if ( !$this->acceptable( $attribs ) ) { // mutates $attribs
			throw new HttpException( 'Not acceptable',
				406 );
		}
		if ( $from === ParsoidFormatHelper::FORMAT_WIKITEXT ) {
			// Accept wikitext as a string or object{body,headers}
			$wikitext = $attribs['opts']['wikitext'] ?? null;
			if ( is_array( $wikitext ) ) {
				$wikitext = $wikitext['body'];
				// We've been given a pagelanguage for this page.
				if ( isset( $attribs['opts']['wikitext']['headers']['content-language'] ) ) {
					$attribs['pagelanguage'] = $attribs['opts']['wikitext']['headers']['content-language'];
				}
			}
			// We've been given source for this page
			if ( $wikitext === null && isset( $attribs['opts']['original']['wikitext'] ) ) {
				$wikitext = $attribs['opts']['original']['wikitext']['body'];
				// We've been given a pagelanguage for this page.
				if ( isset( $attribs['opts']['original']['wikitext']['headers']['content-language'] ) ) {
					$attribs['pagelanguage'] = $attribs['opts']['original']['wikitext']['headers']['content-language'];
				}
			}
			// Abort if no wikitext or title.
			if ( $wikitext === null && $attribs['titleMissing'] ) {
				throw new HttpException( 'No title or wikitext was provided.',
					400 );
			}
			$pageConfig = $this->tryToCreatePageConfig( $attribs,
				$wikitext );

			return $this->wt2html( $pageConfig,
				$attribs,
				$wikitext );
		} elseif ( $format === ParsoidFormatHelper::FORMAT_WIKITEXT ) {
			$html = $attribs['opts']['html'] ?? null;
			// Accept html as a string or object{body,headers}
			if ( is_array( $html ) ) {
				$html = $html['body'];
			}
			if ( $html === null ) {
				throw new HttpException( 'No html was supplied.',
					400 );
			}

			// TODO: use ETag from If-Match header, for compat!

			$page = $this->tryToCreatePageIdentity( $attribs );

			return $this->html2wt(
				$page,
				$attribs,
				$html
			);
		} else {
			return $this->pb2pb( $attribs );
		}
	}
}
