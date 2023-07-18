<?php

namespace PageImages\Hooks;

use DerivativeContext;
use Exception;
use File;
use FormatMetadata;
use MediaWiki\Hook\ParserAfterTidyHook;
use MediaWiki\Hook\ParserModifyImageHTML;
use MediaWiki\Hook\ParserTestGlobalsHook;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Page\PageReference;
use PageImages\PageImageCandidate;
use PageImages\PageImages;
use Parser;
use ParserOutput;
use RepoGroup;
use RuntimeException;
use Title;
use WANObjectCache;

/**
 * Handlers for parser hooks.
 *
 * The ParserModifyImageHTML hook handler collects candidate images, and marks
 * them with a temporary HTML comment in the parser output.
 *
 * The ParserAfterTidy hook handler processes the candidate images, identifying
 * the best image and the best free image. If $wgPageImagesLeadSectionOnly is
 * set, images following the first section header are discarded. It removes the
 * temporary comments and saves the resulting best images to page_props.
 *
 * The various query interfaces will retrieve the lead image from page_props.
 *
 * @license WTFPL
 * @author Max Semenik
 * @author Thiemo Kreuz
 */
class ParserFileProcessingHookHandlers implements
	ParserAfterTidyHook,
	ParserModifyImageHTML,
	ParserTestGlobalsHook
{
	private const CANDIDATE_REGEX = '/<!--MW-PAGEIMAGES-CANDIDATE-([0-9]+)-->/';

	/** @var RepoGroup */
	private $repoGroup;

	/** @var WANObjectCache */
	private $mainWANObjectCache;

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	/**
	 * @param RepoGroup $repoGroup
	 * @param WANObjectCache $mainWANObjectCache
	 * @param HttpRequestFactory $httpRequestFactory
	 */
	public function __construct(
		RepoGroup $repoGroup,
		WANObjectCache $mainWANObjectCache,
		HttpRequestFactory $httpRequestFactory
	) {
		$this->repoGroup = $repoGroup;
		$this->mainWANObjectCache = $mainWANObjectCache;
		$this->httpRequestFactory = $httpRequestFactory;
	}

	/**
	 * @param array &$globals
	 */
	public function onParserTestGlobals( &$globals ) {
		$globals += [
			'wgPageImagesScores' => [
				'width' => [
					200 => 10,
					1000 => 20
				],
				'position' => [],
				'ratio' => [],
				'galleryImageWidth' => []
			],
			'wgPageImagesLeadSectionOnly' => true
		];
	}

	/**
	 * ParserModifyImageHTML hook. Save candidate images, and mark them with a
	 * comment so that we can later tell if they were in the lead section.
	 *
	 * @param Parser $parser
	 * @param File $file
	 * @param array $params
	 * @param string &$html
	 */
	public function onParserModifyImageHTML(
		Parser $parser,
		File $file,
		array $params,
		string &$html
	): void {
		$page = $parser->getPage();
		if ( !$page || !$this->processThisTitle( $page ) ) {
			return;
		}

		$this->calcWidth( $params, $file );

		$index = $this->addPageImageCandidateToParserOutput(
			PageImageCandidate::newFromFileAndParams( $file, $params ),
			$parser->getOutput()
		);
		$html .= "<!--MW-PAGEIMAGES-CANDIDATE-$index-->";
	}

	/**
	 * ParserAfterTidy hook handler. Remove candidate images which were not in
	 * the lead section.
	 *
	 * @param Parser $parser
	 * @param string &$text
	 */
	public function onParserAfterTidy( $parser, &$text ) {
		global $wgPageImagesLeadSectionOnly;
		$parserOutput = $parser->getOutput();
		$allImages = $parserOutput->getExtensionData( 'pageImages' );
		if ( !$allImages ) {
			return;
		}

		// Find and remove our special comments
		$images = [];
		if ( $wgPageImagesLeadSectionOnly ) {
			$leadEndPos = strpos( $text, '<mw:editsection' );
		} else {
			$leadEndPos = false;
		}
		$text = preg_replace_callback(
			self::CANDIDATE_REGEX,
			static function ( $m ) use ( $allImages, &$images, $leadEndPos ) {
				$offset = $m[0][1];
				$id = intval( $m[1][0] );
				$inLead = $leadEndPos === false || $offset < $leadEndPos;
				if ( $inLead && isset( $allImages[$id] ) ) {
					$images[] = PageImageCandidate::newFromArray( $allImages[$id] );
				}
				return '';
			},
			$text, -1, $count, PREG_OFFSET_CAPTURE
		);

		list( $bestImageName, $freeImageName ) = $this->findBestImages( $images );

		if ( $freeImageName ) {
			$parserOutput->setPageProperty( PageImages::getPropName( true ), $freeImageName );
		}

		// Only store the image if it's not free. Free image (if any) has already been stored above.
		if ( $bestImageName && $bestImageName !== $freeImageName ) {
			$parserOutput->setPageProperty( PageImages::getPropName( false ), $bestImageName );
		}

		// Strip comments from indicators (T298930)
		foreach ( $parserOutput->getIndicators() as $id => $value ) {
			$stripped = preg_replace( self::CANDIDATE_REGEX, '', $value );
			if ( $stripped !== $value ) {
				$parserOutput->setIndicator( $id, $stripped );
			}
		}
	}

	/**
	 * Find the best images out of an array of candidates
	 *
	 * @param PageImageCandidate[] $images
	 * @return array The best image, and the best free image
	 */
	private function findBestImages( array $images ) {
		if ( !count( $images ) ) {
			return [ false, false ];
		}

		// Determine the image scores

		$scores = [];
		$counter = 0;

		foreach ( $images as $image ) {
			$fileName = $image->getFileName();

			if ( !isset( $scores[$fileName] ) ) {
				$scores[$fileName] = -1;
			}

			$scores[$fileName] = max( $scores[$fileName], $this->getScore( $image, $counter++ ) );
		}

		$bestImageName = false;
		$freeImageName = false;

		foreach ( $scores as $name => $score ) {
			if ( $score > 0 ) {
				if ( !$bestImageName || $score > $scores[$bestImageName] ) {
					$bestImageName = $name;
				}
				if ( ( !$freeImageName || $score > $scores[$freeImageName] ) && $this->isImageFree( $name ) ) {
					$freeImageName = $name;
				}
			}
		}
		return [ $bestImageName, $freeImageName ];
	}

	/**
	 * Adds $image to $parserOutput extension data.
	 *
	 * @param PageImageCandidate $image
	 * @param ParserOutput $parserOutput
	 * @return int
	 */
	private function addPageImageCandidateToParserOutput(
		PageImageCandidate $image,
		ParserOutput $parserOutput
	) {
		$images = $parserOutput->getExtensionData( 'pageImages' ) ?: [];
		$images[] = $image->jsonSerialize();
		$parserOutput->setExtensionData( 'pageImages', $images );
		return count( $images ) - 1;
	}

	/**
	 * Returns true if data for this title should be saved
	 *
	 * @param PageReference $pageReference
	 *
	 * @return bool
	 */
	private function processThisTitle( PageReference $pageReference ) {
		global $wgPageImagesNamespaces;
		static $flipped = false;

		if ( $flipped === false ) {
			$flipped = array_flip( $wgPageImagesNamespaces );
		}

		return isset( $flipped[$pageReference->getNamespace()] );
	}

	/**
	 * Estimates image size as displayed if not explicitly provided. We don't follow the core size
	 * calculation algorithm precisely because it's not required and editor's intentions are more
	 * important than the precise number.
	 *
	 * @param array[] &$params
	 * @param File $file
	 */
	private function calcWidth( array &$params, File $file ) {
		global $wgThumbLimits, $wgDefaultUserOptions;

		if ( isset( $params['handler']['width'] ) ) {
			return;
		}

		if ( isset( $params['handler']['height'] ) && $file->getHeight() > 0 ) {
			$params['handler']['width'] =
				$file->getWidth() * ( $params['handler']['height'] / $file->getHeight() );
		} elseif ( isset( $params['frame']['thumbnail'] )
			|| isset( $params['frame']['thumb'] )
			|| isset( $params['frame']['frameless'] )
		) {
			$params['handler']['width'] = $wgThumbLimits[$wgDefaultUserOptions['thumbsize']]
				?? 250;
		} else {
			$params['handler']['width'] = $file->getWidth();
		}
	}

	/**
	 * Returns score for image, the more the better, if it is less than zero,
	 * the image shouldn't be used for anything
	 *
	 * @param PageImageCandidate $image Associative array describing an image
	 * @param int $position Image order on page
	 *
	 * @return float
	 */
	protected function getScore( PageImageCandidate $image, $position ) {
		global $wgPageImagesScores;

		$classes = preg_split( '/\s+/', $image->getFrameClass(), -1, PREG_SPLIT_NO_EMPTY );
		if ( in_array( 'notpageimage', $classes ) ) {
			// Exclude images with class=nopageimage
			return -1000;
		}

		if ( $image->getHandlerWidth() ) {
			// Standalone image
			$score = $this->scoreFromTable( $image->getHandlerWidth(), $wgPageImagesScores['width'] );
		} else {
			// From gallery
			$score = $this->scoreFromTable( $image->getFullWidth(), $wgPageImagesScores['galleryImageWidth'] );
		}

		if ( isset( $wgPageImagesScores['position'][$position] ) ) {
			$score += $wgPageImagesScores['position'][$position];
		}

		$ratio = intval( $this->getRatio( $image ) * 10 );
		$score += $this->scoreFromTable( $ratio, $wgPageImagesScores['ratio'] );

		$denylist = $this->getDenylist();
		if ( isset( $denylist[$image->getFileName()] ) ) {
			$score = -1000;
		}

		return $score;
	}

	/**
	 * Returns score based on table of ranges
	 *
	 * @param int $value The number that the various bounds are compared against
	 * to calculate the score
	 * @param float[] $scores Table of scores for different ranges of $value
	 *
	 * @return float
	 */
	protected function scoreFromTable( $value, array $scores ) {
		$lastScore = 0;

		// The loop stops at the *first* match, and therefore *requires* the input array keys to be
		// in increasing order.
		ksort( $scores, SORT_NUMERIC );
		foreach ( $scores as $upperBoundary => $score ) {
			$lastScore = $score;

			if ( $value <= $upperBoundary ) {
				break;
			}
		}

		if ( !is_numeric( $lastScore ) ) {
			wfLogWarning( 'The PageImagesScores setting must only contain numeric values!' );
		}

		return (float)$lastScore;
	}

	/**
	 * Check whether image's copyright allows it to be used freely.
	 *
	 * @param string $fileName Name of the image file
	 * @return bool
	 */
	protected function isImageFree( $fileName ) {
		$file = $this->repoGroup->findFile( $fileName );
		if ( $file ) {
			// Process copyright metadata from CommonsMetadata, if present.
			// Image is considered free if the value is '0' or unset.
			return empty( $this->fetchFileMetadata( $file )['NonFree']['value'] );
		}
		return true;
	}

	/**
	 * Fetch file metadata
	 *
	 * @param File $file File to fetch metadata from
	 * @return array
	 */
	protected function fetchFileMetadata( $file ) {
		$format = new FormatMetadata;
		$context = new DerivativeContext( $format->getContext() );
		// we don't care about the language, and specifying singleLanguage is slightly faster
		$format->setSingleLanguage( true );
		// we don't care about the language, so avoid splitting the cache by selecting English
		$context->setLanguage( 'en' );
		$format->setContext( $context );
		return $format->fetchExtendedMetadata( $file );
	}

	/**
	 * Returns width/height ratio of an image as displayed or 0 is not available
	 *
	 * @param PageImageCandidate $image Array representing the image to get the aspect ratio from
	 *
	 * @return float|int
	 */
	protected function getRatio( PageImageCandidate $image ) {
		$width = $image->getFullWidth();
		$height = $image->getFullHeight();

		if ( !$width || !$height ) {
			return 0;
		}

		return $width / $height;
	}

	/**
	 * Returns a list of images denylisted from influencing this extension's output
	 *
	 * @return int[] Flipped associative array in format "image BDB key" => int
	 * @throws Exception
	 */
	protected function getDenylist() {
		global $wgPageImagesDenylistExpiry;

		return $this->mainWANObjectCache->getWithSetCallback(
			$this->mainWANObjectCache->makeKey( 'pageimages-denylist' ),
			$wgPageImagesDenylistExpiry,
			function () {
				global $wgPageImagesDenylist;

				$list = [];
				foreach ( $wgPageImagesDenylist as $source ) {
					switch ( $source['type'] ) {
						case 'db':
							$list = array_merge(
								$list,
								$this->getDbDenylist( $source['db'], $source['page'] )
							);
							break;
						case 'url':
							$list = array_merge(
								$list,
								$this->getUrlDenylist( $source['url'] )
							);
							break;
						default:
							throw new RuntimeException(
								"unrecognized image denylist type '{$source['type']}'"
							);
					}
				}

				return array_flip( $list );
			}
		);
	}

	/**
	 * Returns list of images linked by the given denylist page
	 *
	 * @param string|bool $dbName Database name or false for current database
	 * @param string $page
	 *
	 * @return string[]
	 */
	private function getDbDenylist( $dbName, $page ) {
		$dbr = wfGetDB( DB_REPLICA, [], $dbName );
		$title = Title::newFromText( $page );
		$list = [];

		$id = $dbr->selectField(
			'page',
			'page_id',
			[ 'page_namespace' => $title->getNamespace(), 'page_title' => $title->getDBkey() ],
			__METHOD__
		);

		if ( $id ) {
			$res = $dbr->select( 'pagelinks',
				'pl_title',
				[ 'pl_from' => $id, 'pl_namespace' => NS_FILE ],
				__METHOD__
			);
			foreach ( $res as $row ) {
				$list[] = $row->pl_title;
			}
		}

		return $list;
	}

	/**
	 * Returns list of images on given remote denylist page.
	 * Not quite 100% bulletproof due to localised namespaces and so on.
	 * Though if you beat people if they add bad entries to the list... :)
	 *
	 * @param string $url
	 *
	 * @return string[]
	 */
	private function getUrlDenylist( $url ) {
		global $wgFileExtensions;

		$list = [];
		$text = $this->httpRequestFactory->get( $url, [ 'timeout' => 3 ], __METHOD__ );
		$regex = '/\[\[:([^|\#]*?\.(?:' . implode( '|', $wgFileExtensions ) . '))/i';

		if ( $text && preg_match_all( $regex, $text, $matches ) ) {
			foreach ( $matches[1] as $s ) {
				$t = Title::makeTitleSafe( NS_FILE, $s );

				if ( $t ) {
					$list[] = $t->getDBkey();
				}
			}
		}

		return $list;
	}

}
