<?php

class FancyCaptcha extends SimpleCaptcha {
	/**
	 * @return FileBackend
	 */
	public function getBackend() {
		global $wgCaptchaFileBackend, $wgCaptchaDirectory;

		if ( $wgCaptchaFileBackend ) {
			return FileBackendGroup::singleton()->get( $wgCaptchaFileBackend );
		} else {
			static $backend = null;
			if ( !$backend ) {
				$backend = new FSFileBackend( array(
					'name'           => 'captcha-backend',
					'wikiId'	 => wfWikiId(),
					'lockManager'    => new NullLockManager( array() ),
					'containerPaths' => array( 'captcha-render' => $wgCaptchaDirectory ),
					'fileMode'       => 777
				) );
			}
			return $backend;
		}
	}

	/**
	 * @return integer Estimate of the number of captchas files
	 */
	public function estimateCaptchaCount() {
		global $wgCaptchaDirectoryLevels;

		$factor = 1;
		$sampleDir = $this->getBackend()->getRootStoragePath() . '/captcha-render';
		if ( $wgCaptchaDirectoryLevels >= 1 ) { // 1/16 sample if 16 shards
			$sampleDir .= '/' . dechex( mt_rand( 0, 15 ) );
			$factor = 16;
		}
		if ( $wgCaptchaDirectoryLevels >= 3 ) { // 1/256 sample if 4096 shards
			$sampleDir .= '/' . dechex( mt_rand( 0, 15 ) );
			$factor = 256;
		}

		$count = 0;
		foreach ( $this->getBackend()->getFileList( array( 'dir' => $sampleDir ) ) as $file ) {
			++$count;
		}

		return ( $count * $factor );
	}

	/**
	 * Check if the submitted form matches the captcha session data provided
	 * by the plugin when the form was generated.
	 *
	 * @param string $answer
	 * @param array $info
	 * @return bool
	 */
	function keyMatch( $answer, $info ) {
		global $wgCaptchaSecret;

		$digest = $wgCaptchaSecret . $info['salt'] . $answer . $wgCaptchaSecret . $info['salt'];
		$answerHash = substr( md5( $digest ), 0, 16 );

		if ( $answerHash == $info['hash'] ) {
			wfDebug( "FancyCaptcha: answer hash matches expected {$info['hash']}\n" );
			return true;
		} else {
			wfDebug( "FancyCaptcha: answer hashes to $answerHash, expected {$info['hash']}\n" );
			return false;
		}
	}

	function addCaptchaAPI( &$resultArr ) {
		$info = $this->pickImage();
		if ( !$info ) {
			$resultArr['captcha']['error'] = 'Out of images';
			return;
		}
		$index = $this->storeCaptcha( $info );
		$title = SpecialPage::getTitleFor( 'Captcha', 'image' );
		$resultArr['captcha']['type'] = 'image';
		$resultArr['captcha']['mime'] = 'image/png';
		$resultArr['captcha']['id'] = $index;
		$resultArr['captcha']['url'] = $title->getLocalUrl( 'wpCaptchaId=' . urlencode( $index ) );
	}

	/**
	 * Insert the captcha prompt into the edit form.
	 */
	function getForm() {
		global $wgOut, $wgExtensionAssetsPath, $wgEnableAPI;

		// Uses addModuleStyles so it is loaded when JS is disabled.
		$wgOut->addModuleStyles( 'ext.confirmEdit.fancyCaptcha.styles' );

		$title = SpecialPage::getTitleFor( 'Captcha', 'image' );
		$index = $this->getCaptchaIndex();

		if ( $wgEnableAPI ) {
			// Loaded only if JS is enabled
			$wgOut->addModules( 'ext.confirmEdit.fancyCaptcha' );

			$captchaReload = Html::element(
				'small',
				array(
					'class' => 'confirmedit-captcha-reload fancycaptcha-reload'
				),
				wfMessage( 'fancycaptcha-reload-text' )->text()
			);
		} else {
			$captchaReload = '';
		}

		return "<div class='fancycaptcha-wrapper'><div class='fancycaptcha-image-container'>" .
			Html::element( 'img', array(
					'class'  => 'fancycaptcha-image',
					'src'    => $title->getLocalUrl( 'wpCaptchaId=' . urlencode( $index ) ),
					'alt'    => ''
				)
			) .
			$captchaReload .
			"</div>\n" .
			'<p>' .
			Html::element( 'label', array(
					'for' => 'wpCaptchaWord',
				),
				parent::getMessage( 'label' ) . wfMessage( 'colon-separator' )->text()
			) .
			Html::element( 'input', array(
					'name' => 'wpCaptchaWord',
					'id'   => 'wpCaptchaWord',
					'type' => 'text',
					'size' => '12',  // max_length in captcha.py plus fudge factor
					'autocomplete' => 'off',
					'autocorrect' => 'off',
					'autocapitalize' => 'off',
					'required' => 'required',
					'tabindex' => 1
				)
			) . // tab in before the edit textarea
			Html::element( 'input', array(
					'type'  => 'hidden',
					'name'  => 'wpCaptchaId',
					'id'    => 'wpCaptchaId',
					'value' => $index
				)
			) .
			"</p>\n" .
			"</div>\n";;
	}

	/**
	 * Get captcha index key
	 * @return string captcha ID key
	 */
	function getCaptchaIndex() {
		$info = $this->pickImage();
		if ( !$info ) {
			throw new MWException( "Ran out of captcha images" );
		}

		// Generate a random key for use of this captcha image in this session.
		// This is needed so multiple edits in separate tabs or windows can
		// go through without extra pain.
		$index = $this->storeCaptcha( $info );

		return $index;
	}

	/**
	 * Select a previously generated captcha image from the queue.
	 * @return mixed tuple of (salt key, text hash) or false if no image to find
	 */
	protected function pickImage() {
		global $wgCaptchaDirectoryLevels;

		$lockouts = 0; // number of times another process claimed a file before this one
		$baseDir = $this->getBackend()->getRootStoragePath() . '/captcha-render';
		return $this->pickImageDir( $baseDir, $wgCaptchaDirectoryLevels, $lockouts );
	}

	/**
	 * @param $directory string
	 * @param $levels integer
	 * @param $lockouts integer
	 * @return Array|bool
	 */
	protected function pickImageDir( $directory, $levels, &$lockouts ) {
		global $wgMemc;

		if ( $levels <= 0 ) { // $directory has regular files
			return $this->pickImageFromDir( $directory, $lockouts );
		}

		$backend = $this->getBackend();

		$key  = "fancycaptcha:dirlist:{$backend->getWikiId()}:" . sha1( $directory );
		$dirs = $wgMemc->get( $key ); // check cache
		if ( !is_array( $dirs ) || !count( $dirs ) ) { // cache miss
			$dirs = array(); // subdirs actually present...
			foreach ( $backend->getTopDirectoryList( array( 'dir' => $directory ) ) as $entry ) {
				if ( ctype_xdigit( $entry ) && strlen( $entry ) == 1 ) {
					$dirs[] = $entry;
				}
			}
			wfDebug( "Cache miss for $directory subdirectory listing.\n" );
			if ( count( $dirs ) ) {
				$wgMemc->set( $key, $dirs, 86400 );
			}
		}

		if ( !count( $dirs ) ) {
			// Remove this directory if empty so callers don't keep looking here
			$backend->clean( array( 'dir' => $directory ) );
			return false; // none found
		}

		$place = mt_rand( 0, count( $dirs ) - 1 ); // pick a random subdir
		// In case all dirs are not filled, cycle through next digits...
		for ( $j = 0; $j < count( $dirs ); $j++ ) {
			$char = $dirs[( $place + $j ) % count( $dirs )];
			$info = $this->pickImageDir( "$directory/$char", $levels - 1, $lockouts );
			if ( $info ) {
				return $info; // found a captcha
			} else {
				wfDebug( "Could not find captcha in $directory.\n" );
				$wgMemc->delete( $key ); // files changed on disk?
			}
		}

		return false; // didn't find any images in this directory... empty?
	}

	/**
	 * @param $directory string
	 * @param $lockouts integer
	 * @return Array|bool
	 */
	protected function pickImageFromDir( $directory, &$lockouts ) {
		global $wgMemc;

		$backend = $this->getBackend();

		$key   = "fancycaptcha:filelist:{$backend->getWikiId()}:" . sha1( $directory );
		$files = $wgMemc->get( $key ); // check cache
		if ( !is_array( $files ) || !count( $files ) ) { // cache miss
			$files = array(); // captcha files
			foreach ( $backend->getTopFileList( array( 'dir' => $directory ) ) as $entry ) {
				$files[] = $entry;
				if ( count( $files ) >= 500 ) { // sanity
					wfDebug( 'Skipping some captchas; $wgCaptchaDirectoryLevels set too low?.' );
					break;
				}
			}
			if ( count( $files ) ) {
				$wgMemc->set( $key, $files, 86400 );
			}
			wfDebug( "Cache miss for $directory captcha listing.\n" );
		}

		if ( !count( $files ) ) {
			// Remove this directory if empty so callers don't keep looking here
			$backend->clean( array( 'dir' => $directory ) );
			return false;
		}

		$info = $this->pickImageFromList( $directory, $files, $lockouts );
		if ( !$info ) {
			wfDebug( "Could not find captcha in $directory.\n" );
			$wgMemc->delete( $key ); // files changed on disk?
		}

		return $info;
	}

	/**
	 * @param $directory string
	 * @param $files array
	 * @param $lockouts integer
	 * @return boolean
	 */
	protected function pickImageFromList( $directory, array $files, &$lockouts ) {
		global $wgMemc, $wgCaptchaDeleteOnSolve;

		if ( !count( $files ) ) {
			return false; // none found
		}

		$backend  = $this->getBackend();
		$place    = mt_rand( 0, count( $files ) - 1 ); // pick a random file
		$misses   = 0; // number of files in listing that don't actually exist
		for ( $j = 0; $j < count( $files ); $j++ ) {
			$entry = $files[( $place + $j ) % count( $files )];
			if ( preg_match( '/^image_([0-9a-f]+)_([0-9a-f]+)\\.png$/', $entry, $matches ) ) {
				if ( $wgCaptchaDeleteOnSolve ) { // captcha will be deleted when solved
					$key = "fancycaptcha:filelock:{$backend->getWikiId()}:" . sha1( $entry );
					// Try to claim this captcha for 10 minutes (for the user to solve)...
					if ( ++$lockouts <= 10 && !$wgMemc->add( $key, '1', 600 ) ) {
						continue; // could not acquire (skip it to avoid race conditions)
					}
				}
				if ( !$backend->fileExists( array( 'src' => "$directory/$entry" ) ) ) {
					if ( ++$misses >= 5 ) { // too many files in the listing don't exist
						break; // listing cache too stale? break out so it will be cleared
					}
					continue; // try next file
				}
				return array(
					'salt'   => $matches[1],
					'hash'   => $matches[2],
					'viewed' => false,
				);
			}
		}

		return false; // none found
	}

	function showImage() {
		global $wgOut;

		$wgOut->disable();

		$info = $this->retrieveCaptcha();
		if ( $info ) {
			$timestamp = new MWTimestamp();
			$info['viewed'] = $timestamp->getTimestamp();
			$this->storeCaptcha( $info );

			$salt = $info['salt'];
			$hash = $info['hash'];

			return $this->getBackend()->streamFile( array(
				'src'     => $this->imagePath( $salt, $hash ),
				'headers' => array( "Cache-Control: private, s-maxage=0, max-age=3600" )
			) )->isOK();
		}

		wfHttpError( 500, 'Internal Error', 'Requested bogus captcha image' );
		return false;
	}

	/**
	 * @param $salt string
	 * @param $hash string
	 * @return string
	 */
	public function imagePath( $salt, $hash ) {
		global $wgCaptchaDirectoryLevels;

		$file = $this->getBackend()->getRootStoragePath() . '/captcha-render/';
		for ( $i = 0; $i < $wgCaptchaDirectoryLevels; $i++ ) {
			$file .= $hash{ $i } . '/';
		}
		$file .= "image_{$salt}_{$hash}.png";

		return $file;
	}

	/**
	 * @param $basename string
	 * @return Array (salt, hash)
	 * @throws MWException
	 */
	public function hashFromImageName( $basename ) {
		if ( preg_match( '/^image_([0-9a-f]+)_([0-9a-f]+)\\.png$/', $basename, $matches ) ) {
			return array( $matches[1], $matches[2] );
		} else {
			throw new MWException( "Invalid filename '$basename'.\n" );
		}
	}

	/**
	 * Show a message asking the user to enter a captcha on edit
	 * The result will be treated as wiki text
	 *
	 * @param $action string Action being performed
	 * @return string
	 */
	function getMessage( $action ) {
		$name = 'fancycaptcha-' . $action;
		$text = wfMessage( $name )->text();
		# Obtain a more tailored message, if possible, otherwise, fall back to
		# the default for edits
		return wfMessage( $name, $text )->isDisabled() ?
			wfMessage( 'fancycaptcha-edit' )->text() : $text;
	}

	/**
	 * Delete a solved captcha image, if $wgCaptchaDeleteOnSolve is true.
	 */
	function passCaptcha() {
		global $wgCaptchaDeleteOnSolve;

		$info = $this->retrieveCaptcha(); // get the captcha info before it gets deleted
		$pass = parent::passCaptcha();

		if ( $pass && $wgCaptchaDeleteOnSolve ) {
			$this->getBackend()->quickDelete( array(
				'src' => $this->imagePath( $info['salt'], $info['hash'] )
			) );
		}

		return $pass;
	}
}
