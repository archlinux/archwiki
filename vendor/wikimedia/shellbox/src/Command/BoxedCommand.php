<?php

namespace Shellbox\Command;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Shellbox\Shellbox;
use Shellbox\ShellboxError;

/**
 * A command with input and output files in an otherwise empty working directory.
 */
class BoxedCommand extends Command {
	/** @var string|null */
	private $routeName;
	/** @var InputFile[] */
	private $inputFiles = [];
	/** @var OutputFile[] */
	private $outputFiles = [];
	/** @var OutputGlob[] */
	private $outputGlobs = [];
	/** @var BoxedExecutor */
	private $executor;

	/**
	 * @internal Use BoxedExecutor::createCommand()
	 * @param BoxedExecutor $boxedExecutor
	 */
	public function __construct( BoxedExecutor $boxedExecutor ) {
		$this->executor = $boxedExecutor;
	}

	/**
	 * Set the route name. This should be a short string used by system
	 * administrators to identify the command being run, in order to route it
	 * to the correct container.
	 *
	 * @param string $routeName
	 * @return $this
	 */
	public function routeName( string $routeName ) {
		$this->routeName = $routeName;
		return $this;
	}

	/**
	 * Add an input file, with the contents given by a string.
	 *
	 * @param string $boxedName The file name relative to the working directory
	 * @param string $contents The file contents
	 * @return $this
	 */
	public function inputFileFromString( string $boxedName, string $contents ) {
		return $this->inputFile( $boxedName, $this->newInputFileFromString( $contents ) );
	}

	/**
	 * Add an input file, with the contents copied from another file.
	 *
	 * @param string $boxedName The destination file name relative to the
	 *   working directory
	 * @param string $sourcePath The path of the source file
	 * @return $this
	 */
	public function inputFileFromFile( string $boxedName, string $sourcePath ) {
		return $this->inputFile( $boxedName, $this->newInputFileFromFile( $sourcePath ) );
	}

	/**
	 * Add an input file, with the contents copied from a stream.
	 *
	 * @param string $boxedName The destination file name relative to the
	 *   working directory
	 * @param StreamInterface $stream The source stream
	 * @return $this
	 */
	public function inputFileFromStream( string $boxedName, StreamInterface $stream ) {
		return $this->inputFile( $boxedName, $this->newInputFileFromStream( $stream ) );
	}

	/**
	 * Add an input file, with the contents fetched from a URL. The fetch may
	 * be done on the remote side.
	 *
	 * If you need to set options such as headers, use methods on the object
	 * returned from newInputFileFromUrl().
	 *
	 * @since 4.1.0
	 * @param string $boxedName The destination file name relative to the
	 *   working directory
	 * @param UriInterface|string $url
	 * @return $this
	 */
	public function inputFileFromUrl( string $boxedName, $url ) {
		return $this->inputFile( $boxedName, $this->newInputFileFromUrl( $url ) );
	}

	/**
	 * Add an input file of any type. Use a factory function to create an
	 * InputFile object.
	 *
	 * @see newInputFileFromString
	 * @see newInputFileFromFile
	 * @see newInputFileFromStream
	 * @see newInputFileFromUrl
	 *
	 * @since 4.1.0
	 * @param string $boxedName The destination file name relative to the
	 *   working directory
	 * @param InputFile $file
	 * @return $this
	 */
	public function inputFile( string $boxedName, InputFile $file ) {
		$boxedName = $this->normalizeBoxedPath( $boxedName );
		$this->inputFiles[$boxedName] = $file;
		return $this;
	}

	/**
	 * @see inputFileFromString
	 * @since 4.1.0
	 * @param string $contents
	 * @return InputFileFromString
	 */
	public function newInputFileFromString( string $contents ) {
		return new InputFileFromString( $contents );
	}

	/**
	 * @see inputFileFromFile
	 * @since 4.1.0
	 * @param string $sourcePath
	 * @return InputFileFromFile
	 */
	public function newInputFileFromFile( string $sourcePath ) {
		return new InputFileFromFile( $sourcePath );
	}

	/**
	 * @see inputFileFromStream
	 * @since 4.1.0
	 * @param StreamInterface $stream
	 * @return InputFileFromStream
	 */
	public function newInputFileFromStream( StreamInterface $stream ) {
		return new InputFileFromStream( $stream );
	}

	/**
	 * @see inputFileFromUrl
	 * @since 4.1.0
	 * @param string|UriInterface $url
	 * @return InputFileFromUrl
	 */
	public function newInputFileFromUrl( $url ) {
		if ( $this->areUrlFilesAllowed() ) {
			return new InputFileFromUrl( $url );
		} else {
			throw new ShellboxError( "Download from URL is disabled" );
		}
	}

	/**
	 * Register an output file. If the command creates it, the contents will
	 * be read into memory.
	 *
	 * @param string $boxedName The expected location of the file relative to
	 *   the working directory.
	 * @return $this
	 */
	public function outputFileToString( string $boxedName ) {
		return $this->outputFile( $boxedName, $this->newOutputFileToString() );
	}

	/**
	 * Register an output file. If the command creates it, the contents will
	 * be copied to a specified location.
	 *
	 * @param string $boxedName The expected location of the file relative to
	 *   the working directory.
	 * @param string $destPath The place where the file will be copied to
	 * @return $this
	 */
	public function outputFileToFile( string $boxedName, string $destPath ) {
		return $this->outputFile( $boxedName, $this->newOutputFileToFile( $destPath ) );
	}

	/**
	 * Register an output file. If the command creates it, the contents will be
	 * copied to a stream.
	 *
	 * @param string $boxedName The expected location of the file relative to
	 *   the working directory.
	 * @param StreamInterface $stream
	 * @return $this
	 */
	public function outputFileToStream( string $boxedName, StreamInterface $stream ) {
		return $this->outputFile( $boxedName, $this->newOutputFileToStream( $stream ) );
	}

	/**
	 * Add an output file. If the command creates it, it will be sent to the
	 * given URL as a PUT request.
	 *
	 * If you need to set options such as headers, use methods on the object
	 * returned from newOutputFileToUrl().
	 *
	 * @since 4.1.0
	 * @param string $boxedName
	 * @param UriInterface|string $url
	 * @return $this
	 */
	public function outputFileToUrl( string $boxedName, $url ) {
		return $this->outputFile( $boxedName, $this->newOutputFileToUrl( $url ) );
	}

	/**
	 * Add an output file of any type. Use a factory function to create an
	 * OutputFile.
	 *
	 * @see newOutputFileToString
	 * @see newOutputFileToFile
	 * @see newOutputFileToStream
	 * @see newOutputFileToUrl
	 *
	 * @param string $boxedName The expected location of the file relative to
	 *   the working directory.
	 * @param OutputFile $file
	 * @return $this
	 */
	public function outputFile( string $boxedName, OutputFile $file ) {
		$boxedName = $this->normalizeBoxedPath( $boxedName );
		$this->outputFiles[$boxedName] = $file;
		return $this;
	}

	/**
	 * @see outputFileToString
	 * @since 4.1.0
	 * @return OutputFileToString
	 */
	public function newOutputFileToString() {
		return new OutputFileToString;
	}

	/**
	 * @see outputFileToFile
	 * @since 4.1.0
	 * @param string $destPath
	 * @return OutputFileToFile
	 */
	public function newOutputFileToFile( string $destPath ) {
		return new OutputFileToFile( $destPath );
	}

	/**
	 * @see outputFileToStream
	 * @since 4.1.0
	 * @param StreamInterface $stream
	 * @return OutputFileToStream
	 */
	public function newOutputFileToStream( StreamInterface $stream ) {
		return new OutputFileToStream( $stream );
	}

	/**
	 * @see outputFileToUrl
	 * @since 4.1.0
	 * @param string|UriInterface $url
	 * @return OutputFileToUrl
	 */
	public function newOutputFileToUrl( $url ) {
		if ( $this->areUrlFilesAllowed() ) {
			return new OutputFileToUrl( $url );
		} else {
			throw new ShellboxError( "Upload to URL is disabled" );
		}
	}

	/**
	 * Register a series of expected output files identified by the pattern
	 *   <prefix>*.<extension>
	 *
	 * Each file that appears in the working directory which matches the
	 * specified pattern will be read into memory.
	 *
	 * @param string $prefix The prefix, potentially including a subdirectory
	 *   relative to the working directory.
	 * @param string $extension The file extension, not including the dot.
	 * @return $this
	 */
	public function outputGlobToString( string $prefix, string $extension ) {
		return $this->outputGlob( $this->newOutputGlobToString( $prefix, $extension ) );
	}

	/**
	 * Register a series of expected output files identified by the pattern
	 *   <prefix>*.<extension>
	 *
	 * Each file that appears in the working directory which matches the
	 * specified pattern will be copied to the specified destination directory
	 *
	 * @param string $prefix The prefix, potentially including a subdirectory
	 *   relative to the working directory.
	 * @param string $extension The file extension, not including the dot.
	 * @param string $destDir The destination directory, which must already
	 *   exist.
	 * @return $this
	 */
	public function outputGlobToFile( string $prefix, string $extension, string $destDir ) {
		return $this->outputGlob( $this->newOutputGlobToFile( $prefix, $extension, $destDir ) );
	}

	/**
	 * Register a series of expected output files identified by a pattern
	 *   <prefix>*.<extension>
	 *
	 * Each file that appears in the working directory which matches the
	 * specified pattern will be sent to the specified URL with a PUT request.
	 * The base name of the matched file will be appended to the path part of
	 * the URL.
	 *
	 * @param string $prefix The prefix, potentially including a subdirectory
	 *    relative to the working directory.
	 * @param string $extension The file extension, not including the dot.
	 * @param string|UriInterface $destUrl The destination directory as a URL.
	 *   The URL should end with a slash or some other application-dependent
	 *   separator, such as %2F.
	 * @return $this
	 */
	public function outputGlobToUrl( string $prefix, string $extension, $destUrl ) {
		return $this->outputGlob( $this->newOutputGlobToUrl( $prefix, $extension, $destUrl ) );
	}

	/**
	 * Register a previously constructed output glob.
	 *
	 * @since 4.1.0
	 * @param OutputGlob $glob
	 * @return $this
	 */
	public function outputGlob( OutputGlob $glob ) {
		$this->outputGlobs[$glob->getId()] = $glob;
		return $this;
	}

	/**
	 * @see outputGlobToString
	 * @param string $prefix
	 * @param string $extension
	 * @return OutputGlobToString
	 */
	public function newOutputGlobToString( string $prefix, string $extension ) {
		return new OutputGlobToString( $prefix, $extension );
	}

	/**
	 * @see outputGlobToFile
	 * @since 4.1.0
	 * @param string $prefix
	 * @param string $extension
	 * @param string $destDir
	 * @return OutputGlobToFile
	 */
	public function newOutputGlobToFile( string $prefix, string $extension, string $destDir ) {
		return new OutputGlobToFile( $prefix, $extension, $destDir );
	}

	/**
	 * @see outputGlobToUrl
	 * @since 4.1.0
	 * @param string $prefix
	 * @param string $extension
	 * @param string|UriInterface $destUrl
	 * @return OutputGlobToUrl
	 */
	public function newOutputGlobToUrl( string $prefix, string $extension, $destUrl ) {
		if ( $this->areUrlFilesAllowed() ) {
			return new OutputGlobToUrl( $prefix, $extension, $destUrl );
		} else {
			throw new ShellboxError( "Upload to URL is disabled" );
		}
	}

	/**
	 * @since 4.1.0
	 * Determine whether URL input/output files are supported by the executor.
	 * @return bool
	 */
	public function areUrlFilesAllowed() {
		return $this->executor->areUrlFilesAllowed();
	}

	/**
	 * Execute the command
	 * @return BoxedResult
	 */
	public function execute(): BoxedResult {
		if ( !$this->executor ) {
			throw new ShellboxError( __METHOD__ .
				' cannot be called unless the executor is set' );
		}
		if ( $this->routeName === null ) {
			throw new ShellboxError( __CLASS__ . ': the route name must be set' );
		}
		return $this->executor->execute( $this );
	}

	/**
	 * Get command parameters for JSON serialization by the client.
	 *
	 * @internal
	 * @return array
	 */
	public function getClientData() {
		$inputFiles = [];
		foreach ( $this->inputFiles as $name => $file ) {
			$inputFiles[$name] = $file->getClientData();
		}
		$outputFiles = [];
		foreach ( $this->outputFiles as $name => $file ) {
			$outputFiles[$name] = $file->getClientData();
		}
		$outputGlobs = [];
		foreach ( $this->outputGlobs as $name => $glob ) {
			$outputGlobs[$name] = $glob->getClientData();
		}
		// phpcs:ignore Generic.WhiteSpace.LanguageConstructSpacing.IncorrectSingle
		return
			[
				'routeName' => $this->routeName,
				'inputFiles' => $inputFiles,
				'outputFiles' => $outputFiles,
				'outputGlobs' => $outputGlobs
			] + parent::getClientData();
	}

	/**
	 * Set command parameters using a data array created by getClientData()
	 *
	 * @internal
	 * @param array $data
	 */
	public function setClientData( $data ) {
		foreach ( $data as $name => $value ) {
			switch ( $name ) {
				case 'routeName':
					$this->routeName = $value;
					break;

				case 'inputFiles':
					$this->inputFiles = [];
					foreach ( $value as $fileName => $fileData ) {
						$this->inputFiles[$fileName] =
							InputFile::newFromClientData( $fileData );
					}
					break;

				case 'outputFiles':
					$this->outputFiles = [];
					foreach ( $value as $fileName => $fileData ) {
						$this->outputFiles[$fileName] =
							OutputFile::newFromClientData( $fileData );
					}
					break;

				case 'outputGlobs':
					$this->outputGlobs = [];
					foreach ( $value as $fileName => $globData ) {
						$this->outputGlobs[$fileName] =
							OutputGlob::newFromClientData( $globData );
					}
					break;
			}
		}
		parent::setClientData( $data );
	}

	/**
	 * Validate a path within the working directory for path traversal safety
	 * and cross-platform file name compliance. Under Windows, the path may
	 * contain backslashes, which will be replaced with slashes.
	 *
	 * @param string $path
	 * @return string
	 * @throws ShellboxError
	 */
	private function normalizeBoxedPath( $path ) {
		return Shellbox::normalizePath( $path );
	}

	/**
	 * Get the route name
	 *
	 * @return string|null
	 */
	public function getRouteName() {
		return $this->routeName;
	}

	/**
	 * Get InputFile objects describing the registered input files.
	 *
	 * @internal
	 * @return InputFile[]
	 */
	public function getInputFiles() {
		return $this->inputFiles;
	}

	/**
	 * Get OutputFile objects describing the registered output files.
	 *
	 * @internal
	 * @return OutputFile[]
	 */
	public function getOutputFiles() {
		return $this->outputFiles;
	}

	/**
	 * Get OutputGlob objects describing the registered output globs.
	 *
	 * @internal
	 * @return OutputGlob[]
	 */
	public function getOutputGlobs() {
		return $this->outputGlobs;
	}
}
