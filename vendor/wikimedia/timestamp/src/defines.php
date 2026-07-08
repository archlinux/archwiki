<?php
/**
 * Timestamp
 *
 * Copyright (C) 2012 Tyler Romeo <tylerromeo@gmail.com>
 *
 * @license GPL-2.0-or-later
 * @file
 * @author Tyler Romeo <tylerromeo@gmail.com>
 */

// @codeCoverageIgnoreStart
use Wikimedia\Timestamp\TimestampFormat;

// These global constants should be considered deprecated, and use in
// new code is discouraged.  Use the TimestampFormat enumeration
// instead.

/**
 * Unix time - the number of seconds since 1970-01-01 00:00:00 UTC
 * @deprecated Use TimestampFormat::UNIX instead.
 */
define( 'TS_UNIX', TimestampFormat::UNIX->value );

/**
 * MediaWiki concatenated string timestamp (YYYYMMDDHHMMSS)
 * @deprecated Use TimestampFormat::MW instead.
 */
define( 'TS_MW', TimestampFormat::MW->value );

/**
 * MySQL DATETIME (YYYY-MM-DD HH:MM:SS)
 * @deprecated Use TimestampFormat::DB instead.
 */
define( 'TS_DB', TimestampFormat::DB->value );

/**
 * RFC 2822 format, for E-mail and HTTP headers
 * @deprecated Use TimestampFormat::RFC2822 instead.
 */
define( 'TS_RFC2822', TimestampFormat::RFC2822->value );

/**
 * ISO 8601 format with no timezone: 1986-02-09T20:00:00Z
 * @deprecated Use TimestampFormat::ISO_8601 instead.
 */
define( 'TS_ISO_8601', TimestampFormat::ISO_8601->value );

/**
 * An Exif timestamp (YYYY:MM:DD HH:MM:SS)
 *
 * @see http://exif.org/Exif2-2.PDF The Exif 2.2 spec, see page 28 for the
 *       DateTime tag and page 36 for the DateTimeOriginal and
 *       DateTimeDigitized tags.
 * @deprecated Use TimestampFormat::EXIF instead.
 */
define( 'TS_EXIF', TimestampFormat::EXIF->value );

/**
 * Oracle format time.
 * @deprecated Use TimestampFormat::ORACLE instead.
 */
define( 'TS_ORACLE', TimestampFormat::ORACLE->value );

/**
 * Postgres format time.
 * @deprecated Use TimestampFormat::POSTGRES instead.
 */
define( 'TS_POSTGRES', TimestampFormat::POSTGRES->value );

/**
 * ISO 8601 basic format with no timezone: 19860209T200000Z.
 * @deprecated Use TimestampFormat::ISO_8601_BASIC instead.
 */
define( 'TS_ISO_8601_BASIC', TimestampFormat::ISO_8601_BASIC->value );

/**
 * UNIX time with microseconds
 * @deprecated Use TimestampFormat::UNIX_MICRO instead.
 */
define( 'TS_UNIX_MICRO', TimestampFormat::UNIX_MICRO->value );

// @codeCoverageIgnoreEnd
