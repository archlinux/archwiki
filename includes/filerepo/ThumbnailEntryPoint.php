<?php
/**
 * Entry point implementation for retrieving media thumbnails, created by a MediaHandler
 * subclass or proxy request if FileRepo::getThumbProxyUrl is configured.
 *
 * This also supports resizing an image on-demand, if it isn't found in the
 * configured FileBackend storage.
 *
 * @see /thumb.php The web entry point.
 *
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
 * @ingroup entrypoint
 * @ingroup Media
 */

namespace MediaWiki\FileRepo;

use Exception;
use File;
use InvalidArgumentException;
use MediaTransformInvalidParametersException;
use MediaTransformOutput;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiEntryPoint;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionStatus;
use MediaWiki\PoolCounter\PoolCounterWorkViaCallback;
use MediaWiki\Profiler\ProfilingContext;
use MediaWiki\Request\HeaderCallback;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MessageSpecifier;
use MWException;
use ObjectCache;
use UnregisteredLocalFile;
use Wikimedia\AtEase\AtEase;

class ThumbnailEntryPoint extends MediaWikiEntryPoint {

	/**
	 * Main entry point
	 */
	public function execute() {
		global $wgTrivialMimeDetection;

		ProfilingContext::singleton()->init(
			MW_ENTRY_POINT,
			'stream'
		);

		// Don't use fancy MIME detection, just check the file extension for jpg/gif/png.
		// NOTE: This only works as long as to StreamFile::contentTypeFromPath
		//       get this setting from global state. When StreamFile gets refactored,
		//       we need to find a better way.
		$wgTrivialMimeDetection = true;

		$this->handleRequest();
	}

	protected function doPrepareForOutput() {
		// No-op.
		// Do not call parent::doPrepareForOutput() to avoid
		// commitMainTransaction() getting called.
	}

	protected function handleRequest() {
		$this->streamThumb( $this->getRequest()->getQueryValuesOnly() );
	}

	/**
	 * Stream a thumbnail specified by parameters
	 *
	 * @param array $params List of thumbnailing parameters. In addition to parameters
	 *  passed to the MediaHandler, this may also includes the keys:
	 *   f (for filename), archived (if archived file), temp (if temp file),
	 *   w (alias for width), p (alias for page), r (ignored; historical),
	 *   rel404 (path for render on 404 to verify hash path correct),
	 *   thumbName (thumbnail name to potentially extract more parameters from
	 *   e.g. 'lossy-page1-120px-Foo.tiff' would add page, lossy and width
	 *   to the parameters)
	 * @return void
	 */
	protected function streamThumb( array $params ) {
		$varyOnXFP = $this->getConfig( MainConfigNames::VaryOnXFP );

		$headers = []; // HTTP headers to send

		$fileName = $params['f'] ?? '';

		// Backwards compatibility parameters
		if ( isset( $params['w'] ) ) {
			$params['width'] = $params['w'];
			unset( $params['w'] );
		}
		if ( isset( $params['width'] ) && substr( $params['width'], -2 ) == 'px' ) {
			// strip the px (pixel) suffix, if found
			$params['width'] = substr( $params['width'], 0, -2 );
		}
		if ( isset( $params['p'] ) ) {
			$params['page'] = $params['p'];
		}

		// Is this a thumb of an archived file?
		$isOld = ( isset( $params['archived'] ) && $params['archived'] );
		unset( $params['archived'] ); // handlers don't care

		// Is this a thumb of a temp file?
		$isTemp = ( isset( $params['temp'] ) && $params['temp'] );
		unset( $params['temp'] ); // handlers don't care

		$services = MediaWikiServices::getInstance();

		// Some basic input validation
		$fileName = strtr( $fileName, '\\/', '__' );
		$localRepo = $services->getRepoGroup()->getLocalRepo();
		$archiveTimestamp = null;

		// Actually fetch the image. Method depends on whether it is archived or not.
		if ( $isTemp ) {
			$repo = $localRepo->getTempRepo();
			$img = new UnregisteredLocalFile( false, $repo,
				# Temp files are hashed based on the name without the timestamp.
				# The thumbnails will be hashed based on the entire name however.
				# @todo fix this convention to actually be reasonable.
				$repo->getZonePath( 'public' ) . '/' . $repo->getTempHashPath( $fileName ) . $fileName
			);
		} elseif ( $isOld ) {
			// Format is <timestamp>!<name>
			$bits = explode( '!', $fileName, 2 );
			if ( count( $bits ) != 2 ) {
				$this->thumbError( 404, $this->getContext()->msg( 'badtitletext' )->parse() );
				return;
			}
			$archiveTimestamp = $bits[0];
			$title = Title::makeTitleSafe( NS_FILE, $bits[1] );
			if ( !$title ) {
				$this->thumbError( 404, $this->getContext()->msg( 'badtitletext' )->parse() );
				return;
			}
			$img = $localRepo->newFromArchiveName( $title, $fileName );
		} else {
			$img = $localRepo->newFile( $fileName );
		}

		// Check the source file title
		if ( !$img ) {
			$this->thumbError( 404, $this->getContext()->msg( 'badtitletext' )->parse() );
			return;
		}

		// Check permissions if there are read restrictions
		$varyHeader = [];
		if ( !$services->getGroupPermissionsLookup()->groupHasPermission( '*', 'read' ) ) {
			$authority = $this->getContext()->getAuthority();
			$imgTitle = $img->getTitle();

			if ( !$imgTitle || !$authority->authorizeRead( 'read', $imgTitle ) ) {
				$this->thumbErrorText( 403, 'Access denied. You do not have permission to access ' .
					'the source file.' );
				return;
			}
			$headers[] = 'Cache-Control: private';
			$varyHeader[] = 'Cookie';
		}

		// Check if the file is hidden
		if ( $img->isDeleted( File::DELETED_FILE ) ) {
			$this->thumbErrorText( 404, "The source file '$fileName' does not exist." );
			return;
		}

		// Do rendering parameters extraction from thumbnail name.
		if ( isset( $params['thumbName'] ) ) {
			$params = $this->extractThumbParams( $img, $params );
		}
		if ( $params == null ) {
			$this->thumbErrorText( 400, 'The specified thumbnail parameters are not recognized.' );
			return;
		}

		// Check the source file storage path
		if ( !$img->exists() ) {
			$redirectedLocation = false;
			if ( !$isTemp ) {
				// Check for file redirect
				// Since redirects are associated with pages, not versions of files,
				// we look for the most current version to see if its a redirect.
				$possRedirFile = $localRepo->findFile( $img->getName() );
				if ( $possRedirFile && $possRedirFile->getRedirected() !== null ) {
					$redirTarget = $possRedirFile->getName();
					$targetFile = $localRepo->newFile( Title::makeTitleSafe( NS_FILE, $redirTarget ) );
					if ( $targetFile->exists() ) {
						$newThumbName = $targetFile->thumbName( $params );
						if ( $isOld ) {
							$newThumbUrl = $targetFile->getArchiveThumbUrl(
								$archiveTimestamp . '!' . $targetFile->getName(), $newThumbName );
						} else {
							$newThumbUrl = $targetFile->getThumbUrl( $newThumbName );
						}
						$redirectedLocation = wfExpandUrl( $newThumbUrl, PROTO_CURRENT );
					}
				}
			}

			if ( $redirectedLocation ) {
				// File has been moved. Give redirect.
				$response = $this->getResponse();
				$response->statusHeader( 302 );
				$response->header( 'Location: ' . $redirectedLocation );
				$response->header( 'Expires: ' .
					gmdate( 'D, d M Y H:i:s', time() + 12 * 3600 ) . ' GMT' );
				if ( $varyOnXFP ) {
					$varyHeader[] = 'X-Forwarded-Proto';
				}
				if ( count( $varyHeader ) ) {
					$response->header( 'Vary: ' . implode( ', ', $varyHeader ) );
				}
				$response->header( 'Content-Length: 0' );
				return;
			}

			// If it's not a redirect that has a target as a local file, give 404.
			$this->thumbErrorText( 404, "The source file '$fileName' does not exist." );
			return;
		} elseif ( $img->getPath() === false ) {
			$this->thumbErrorText( 400, "The source file '$fileName' is not locally accessible." );
			return;
		}

		// Check IMS against the source file
		// This means that clients can keep a cached copy even after it has been deleted on the server
		if ( $this->getServerInfo( 'HTTP_IF_MODIFIED_SINCE', '' ) !== '' ) {
			// Fix IE brokenness
			$imsString = preg_replace(
				'/;.*$/',
				'',
				$this->getServerInfo( 'HTTP_IF_MODIFIED_SINCE' ) ?? ''
			);
			// Calculate time
			AtEase::suppressWarnings();
			$imsUnix = strtotime( $imsString );
			AtEase::restoreWarnings();
			if ( wfTimestamp( TS_UNIX, $img->getTimestamp() ) <= $imsUnix ) {
				$this->status( 304 );
				return;
			}
		}

		$rel404 = $params['rel404'] ?? null;
		unset( $params['r'] ); // ignore 'r' because we unconditionally pass File::RENDER
		unset( $params['f'] ); // We're done with 'f' parameter.
		unset( $params['rel404'] ); // moved to $rel404

		// Get the normalized thumbnail name from the parameters...
		try {
			$thumbName = $img->thumbName( $params );
			if ( !strlen( $thumbName ?? '' ) ) { // invalid params?
				throw new MediaTransformInvalidParametersException(
					'Empty return from File::thumbName'
				);
			}
			$thumbName2 = $img->thumbName( $params, File::THUMB_FULL_NAME ); // b/c; "long" style
		} catch ( MediaTransformInvalidParametersException $e ) {
			$this->thumbErrorText(
				400,
				'The specified thumbnail parameters are not valid: ' . $e->getMessage()
			);
			return;
		} catch ( MWException $e ) {
			$this->thumbError( 500, $e->getHTML(), 'Exception caught while extracting thumb name',
				[ 'exception' => $e ] );
			return;
		}

		// For 404 handled thumbnails, we only use the base name of the URI
		// for the thumb params and the parent directory for the source file name.
		// Check that the zone relative path matches up so CDN caches won't pick
		// up thumbs that would not be purged on source file deletion (T36231).
		if ( $rel404 !== null ) { // thumbnail was handled via 404
			if ( rawurldecode( $rel404 ) === $img->getThumbRel( $thumbName ) ) {
				// Request for the canonical thumbnail name
			} elseif ( rawurldecode( $rel404 ) === $img->getThumbRel( $thumbName2 ) ) {
				// Request for the "long" thumbnail name; redirect to canonical name
				$this->status( 301 );
				$this->header( 'Location: ' .
					wfExpandUrl( $img->getThumbUrl( $thumbName ), PROTO_CURRENT ) );
				$this->header( 'Expires: ' .
					gmdate( 'D, d M Y H:i:s', time() + 7 * 86400 ) . ' GMT' );
				if ( $varyOnXFP ) {
					$varyHeader[] = 'X-Forwarded-Proto';
				}
				if ( count( $varyHeader ) ) {
					$this->header( 'Vary: ' . implode( ', ', $varyHeader ) );
				}
				return;
			} else {
				$this->thumbErrorText( 404, "The given path of the specified thumbnail is incorrect;
				expected '" . $img->getThumbRel( $thumbName ) . "' but got '" .
					rawurldecode( $rel404 ) . "'." );
				return;
			}
		}

		$dispositionType = isset( $params['download'] ) ? 'attachment' : 'inline';

		// Suggest a good name for users downloading this thumbnail
		$headers[] =
			'Content-Disposition: ' . $img->getThumbDisposition( $thumbName, $dispositionType );

		if ( count( $varyHeader ) ) {
			$headers[] = 'Vary: ' . implode( ', ', $varyHeader );
		}

		// Stream the file if it exists already...
		$thumbPath = $img->getThumbPath( $thumbName );
		if ( $img->getRepo()->fileExists( $thumbPath ) ) {
			$starttime = microtime( true );
			$status = $img->getRepo()->streamFileWithStatus( $thumbPath, $headers );
			$streamtime = microtime( true ) - $starttime;

			if ( $status->isOK() ) {
				$services->getStatsdDataFactory()->timing(
					'media.thumbnail.stream',
					$streamtime
				);
			} else {
				$this->thumbError(
					500,
					'Could not stream the file',
					$status->getWikiText( false, false, 'en' ),
					[
						'file' => $thumbName,
						'path' => $thumbPath,
						'error' => $status->getWikiText( false, false, 'en' ),
					]
				);
			}
			return;
		}

		$authority = $this->getContext()->getAuthority();
		$status = PermissionStatus::newEmpty();
		if ( !wfThumbIsStandard( $img, $params )
			&& !$authority->authorizeAction( 'renderfile-nonstandard', $status )
		) {
			$statusFormatter = $services->getFormatterFactory()
				->getStatusFormatter( $this->getContext() );

			$this->thumbError( 429, $statusFormatter->getHTML( $status ) );
			return;
		} elseif ( !$authority->authorizeAction( 'renderfile', $status ) ) {
			$statusFormatter = $services->getFormatterFactory()
				->getStatusFormatter( $this->getContext() );

			$this->thumbError( 429, $statusFormatter->getHTML( $status ) );
			return;
		}

		$thumbProxyUrl = $img->getRepo()->getThumbProxyUrl();

		if ( strlen( $thumbProxyUrl ?? '' ) ) {
			$this->proxyThumbnailRequest( $img, $thumbName );
			// No local fallback when in proxy mode
			return;
		} else {
			// Generate the thumbnail locally
			[ $thumb, $errorMsg ] = $this->generateThumbnail( $img, $params, $thumbName, $thumbPath );
		}

		/** @var MediaTransformOutput|false $thumb */

		// Check for thumbnail generation errors...
		$msg = $this->getContext()->msg( 'thumbnail_error' );
		$errorCode = 500;

		if ( !$thumb ) {
			$errorMsg = $errorMsg ?: $msg->rawParams( 'File::transform() returned false' )->escaped();
			if ( $errorMsg instanceof MessageSpecifier &&
				$errorMsg->getKey() === 'thumbnail_image-failure-limit'
			) {
				$errorCode = 429;
			}
		} elseif ( $thumb->isError() ) {
			$errorMsg = $thumb->getHtmlMsg();
			$errorCode = $thumb->getHttpStatusCode();
		} elseif ( !$thumb->hasFile() ) {
			$errorMsg = $msg->rawParams( 'No path supplied in thumbnail object' )->escaped();
		} elseif ( $thumb->fileIsSource() ) {
			$errorMsg = $msg
				->rawParams( 'Image was not scaled, is the requested width bigger than the source?' )
				->escaped();
			$errorCode = 400;
		}

		$this->prepareForOutput();

		if ( $errorMsg !== false ) {
			$this->thumbError( $errorCode, $errorMsg, null, [ 'file' => $thumbName, 'path' => $thumbPath ] );
		} else {
			// Stream the file if there were no errors
			'@phan-var MediaTransformOutput $thumb';
			$status = $thumb->streamFileWithStatus( $headers );
			if ( !$status->isOK() ) {
				$this->thumbError( 500, 'Could not stream the file', $status->getWikiText( false, false, 'en' ), [
					'file' => $thumbName, 'path' => $thumbPath,
					'error' => $status->getWikiText( false, false, 'en' ) ] );
			}
		}
	}

	/**
	 * Proxies thumbnail request to a service that handles thumbnailing
	 *
	 * @param File $img
	 * @param string $thumbName
	 */
	private function proxyThumbnailRequest( $img, $thumbName ) {
		$thumbProxyUrl = $img->getRepo()->getThumbProxyUrl();

		// Instead of generating the thumbnail ourselves, we proxy the request to another service
		$thumbProxiedUrl = $thumbProxyUrl . $img->getThumbRel( $thumbName );

		$req = MediaWikiServices::getInstance()->getHttpRequestFactory()->create( $thumbProxiedUrl );
		$secret = $img->getRepo()->getThumbProxySecret();

		// Pass a secret key shared with the proxied service if any
		if ( strlen( $secret ?? '' ) ) {
			$req->setHeader( 'X-Swift-Secret', $secret );
		}

		// Send request to proxied service
		$req->execute();

		HeaderCallback::warnIfHeadersSent();

		// Simply serve the response from the proxied service as-is
		$this->header( 'HTTP/1.1 ' . $req->getStatus() );

		$headers = $req->getResponseHeaders();

		foreach ( $headers as $key => $values ) {
			foreach ( $values as $value ) {
				$this->header( $key . ': ' . $value, false );
			}
		}

		$this->print( $req->getContent() );
	}

	/**
	 * Actually try to generate a new thumbnail
	 *
	 * @param File $file
	 * @param array $params
	 * @param string $thumbName
	 * @param string $thumbPath
	 * @return array (MediaTransformOutput|bool, string|bool error message HTML)
	 */
	protected function generateThumbnail( File $file, array $params, $thumbName, $thumbPath ) {
		$attemptFailureEpoch = $this->getConfig( MainConfigNames::AttemptFailureEpoch );

		$cache = ObjectCache::getLocalClusterInstance();
		$key = $cache->makeKey(
			'attempt-failures',
			$attemptFailureEpoch,
			$file->getRepo()->getName(),
			$file->getSha1(),
			md5( $thumbName )
		);

		// Check if this file keeps failing to render
		if ( $cache->get( $key ) >= 4 ) {
			return [ false, $this->getContext()->msg( 'thumbnail_image-failure-limit', 4 ) ];
		}

		$done = false;
		// Record failures on PHP fatals in addition to caching exceptions
		register_shutdown_function( static function () use ( $cache, &$done, $key ) {
			if ( !$done ) { // transform() gave a fatal
				// Randomize TTL to reduce stampedes
				$cache->incrWithInit( $key, $cache::TTL_HOUR + mt_rand( 0, 300 ) );
			}
		} );

		$thumb = false;
		$errorHtml = false;

		// guard thumbnail rendering with PoolCounter to avoid stampedes
		// expensive files use a separate PoolCounter config so it is possible
		// to set up a global limit on them
		if ( $file->isExpensiveToThumbnail() ) {
			$poolCounterType = 'FileRenderExpensive';
		} else {
			$poolCounterType = 'FileRender';
		}

		// Thumbnail isn't already there, so create the new thumbnail...
		try {
			$work = new PoolCounterWorkViaCallback( $poolCounterType, sha1( $file->getName() ),
				[
					'doWork' => static function () use ( $file, $params ) {
						return $file->transform( $params, File::RENDER_NOW );
					},
					'doCachedWork' => static function () use ( $file, $params, $thumbPath ) {
						// If the worker that finished made this thumbnail then use it.
						// Otherwise, it probably made a different thumbnail for this file.
						return $file->getRepo()->fileExists( $thumbPath )
							? $file->transform( $params, File::RENDER_NOW )
							: false; // retry once more in exclusive mode
					},
					'error' => function ( Status $status ) {
						return $this->getContext()->msg( 'generic-pool-error' )->parse() . '<hr>' . $status->getHTML();
					}
				]
			);
			$result = $work->execute();
			if ( $result instanceof MediaTransformOutput ) {
				$thumb = $result;
			} elseif ( is_string( $result ) ) { // error
				$errorHtml = $result;
			}
		} catch ( Exception $e ) {
			// Tried to select a page on a non-paged file?
		}

		/** @noinspection PhpUnusedLocalVariableInspection */
		$done = true; // no PHP fatal occurred

		if ( !$thumb || $thumb->isError() ) {
			// Randomize TTL to reduce stampedes
			$cache->incrWithInit( $key, $cache::TTL_HOUR + mt_rand( 0, 300 ) );
		}

		return [ $thumb, $errorHtml ];
	}

	/**
	 * Convert a thumbnail name (122px-foo.png) to parameters, using
	 * file handler.
	 *
	 * @param File $file File object for file in question
	 * @param array $params Array of parameters so far
	 * @return array|null Parameters array with more parameters, or null
	 */
	private function extractThumbParams( $file, $params ) {
		if ( !isset( $params['thumbName'] ) ) {
			throw new InvalidArgumentException( "No thumbnail name passed to extractThumbParams" );
		}

		$thumbname = $params['thumbName'];
		unset( $params['thumbName'] );

		// FIXME: Files in the temp zone don't set a MIME type, which means
		// they don't have a handler. Which means we can't parse the param
		// string. However, not a big issue as what good is a param string
		// if you have no handler to make use of the param string and
		// actually generate the thumbnail.
		$handler = $file->getHandler();

		// Based on UploadStash::parseKey
		$fileNamePos = strrpos( $thumbname, $params['f'] );
		if ( $fileNamePos === false ) {
			// Maybe using a short filename? (see FileRepo::nameForThumb)
			$fileNamePos = strrpos( $thumbname, 'thumbnail' );
		}

		if ( $handler && $fileNamePos !== false ) {
			$paramString = substr( $thumbname, 0, $fileNamePos - 1 );
			$extraParams = $handler->parseParamString( $paramString );
			if ( $extraParams !== false ) {
				return $params + $extraParams;
			}
		}

		// As a last ditch fallback, use the traditional common parameters
		if ( preg_match( '!^(page(\d*)-)*(\d*)px-[^/]*$!', $thumbname, $matches ) ) {
			[ /* all */, /* pagefull */, $pagenum, $size ] = $matches;
			$params['width'] = $size;
			if ( $pagenum ) {
				$params['page'] = $pagenum;
			}
			return $params; // valid thumbnail URL
		}
		return null;
	}

	/**
	 * Output a thumbnail generation error message
	 *
	 * @param int $status
	 * @param string $msgText Plain text (will be html escaped)
	 * @return void
	 */
	protected function thumbErrorText( $status, $msgText ) {
		$this->thumbError( $status, htmlspecialchars( $msgText, ENT_NOQUOTES ) );
	}

	/**
	 * Output a thumbnail generation error message
	 *
	 * @param int $status
	 * @param string $msgHtml HTML
	 * @param string|null $msgText Short error description, for internal logging. Defaults to $msgHtml.
	 *   Only used for HTTP 500 errors.
	 * @param array $context Error context, for internal logging. Only used for HTTP 500 errors.
	 * @return void
	 */
	protected function thumbError( $status, $msgHtml, $msgText = null, $context = [] ) {
		$showHostnames = $this->getConfig( MainConfigNames::ShowHostnames );

		HeaderCallback::warnIfHeadersSent();

		if ( $this->getResponse()->headersSent() ) {
			LoggerFactory::getInstance( 'thumbnail' )->error(
				'Error after output had been started. Output may be corrupt or truncated. ' .
				'Original error: ' . ( $msgText ?: $msgHtml ) . " (Status $status)",
				$context
			);
			return;
		}

		$this->header( 'Cache-Control: no-cache' );
		$this->header( 'Content-Type: text/html; charset=utf-8' );
		if ( $status == 400 || $status == 404 || $status == 429 ) {
			$this->status( $status );
		} elseif ( $status == 403 ) {
			$this->status( 403 );
			$this->header( 'Vary: Cookie' );
		} else {
			LoggerFactory::getInstance( 'thumbnail' )->error( $msgText ?: $msgHtml, $context );
			$this->status( 500 );
		}
		if ( $showHostnames ) {
			$this->header( 'X-MW-Thumbnail-Renderer: ' . wfHostname() );
			$url = htmlspecialchars(
				$this->getServerInfo( 'REQUEST_URI' ) ?? '',
				ENT_NOQUOTES
			);
			$hostname = htmlspecialchars( wfHostname(), ENT_NOQUOTES );
			$debug = "<!-- $url -->\n<!-- $hostname -->\n";
		} else {
			$debug = '';
		}
		$content = <<<EOT
<!DOCTYPE html>
<html><head>
<meta charset="UTF-8" />
<title>Error generating thumbnail</title>
</head>
<body>
<h1>Error generating thumbnail</h1>
<p>
$msgHtml
</p>
$debug
</body>
</html>

EOT;
		$this->header( 'Content-Length: ' . strlen( $content ) );
		$this->print( $content );
	}

}
