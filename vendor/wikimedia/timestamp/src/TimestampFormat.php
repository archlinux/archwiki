<?php
/**
 * Timestamp format enumeration.
 *
 * Copyright (C) 2025 C. Scott Ananian <cananian@wikimedia.org>
 *
 * @license GPL-2.0-or-later
 * @file
 * @author C. Scott Ananian <cananian@wikimedia.org>
 */

namespace Wikimedia\Timestamp;

enum TimestampFormat: int {
	/**
	 * Unix time - the number of seconds since 1970-01-01 00:00:00 UTC
	 */
	case UNIX = 0;

	/**
	 * MediaWiki concatenated string timestamp (YYYYMMDDHHMMSS)
	 */
	case MW = 1;

	/**
	 * MySQL DATETIME (YYYY-MM-DD HH:MM:SS)
	 */
	case DB = 2;

	/**
	 * RFC 2822 format, for E-mail and HTTP headers
	 */
	case RFC2822 = 3;

	/**
	 * ISO 8601 format with no timezone: 1986-02-09T20:00:00Z
	 */
	case ISO_8601 = 4;

	/**
	 * An Exif timestamp (YYYY:MM:DD HH:MM:SS)
	 *
	 * @see http://exif.org/Exif2-2.PDF The Exif 2.2 spec, see page 28 for the
	 *       DateTime tag and page 36 for the DateTimeOriginal and
	 *       DateTimeDigitized tags.
	 */
	case EXIF = 5;

	/**
	 * Oracle format time.
	 */
	case ORACLE = 6;

	/**
	 * Postgres format time.
	 */
	case POSTGRES = 7;

	/**
	 * ISO 8601 basic format with no timezone: 19860209T200000Z.
	 */
	case ISO_8601_BASIC = 9;

	/**
	 * UNIX time with microseconds
	 */
	case UNIX_MICRO = 10;
}
