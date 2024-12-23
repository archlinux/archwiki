/*
 * This file is part of the MediaWiki extension MediaViewer.
 *
 * MediaViewer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MediaViewer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MediaViewer.  If not, see <http://www.gnu.org/licenses/>.
 */

const DownloadDialog = require( './mmv.ui.download.dialog.js' );
const ReuseDialog = require( './mmv.ui.reuse.dialog.js' );
const Download = require( './mmv.ui.download.pane.js' );
const Embed = require( './mmv.ui.reuse.embed.js' );
const EmbedFileFormatter = require( './mmv.EmbedFileFormatter.js' );
const Share = require( './mmv.ui.reuse.share.js' );
const Utils = require( './mmv.ui.utils.js' );

module.exports = { DownloadDialog, ReuseDialog, Download, Embed, EmbedFileFormatter, Share, Utils };
