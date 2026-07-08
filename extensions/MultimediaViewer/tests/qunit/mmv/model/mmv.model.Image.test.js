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

const { ImageModel } = require( 'mmv' );
const { fixtures } = require( '../mmv.testhelpers.js' );

QUnit.module( 'mmv.model.Image', QUnit.newMwEnvironment() );

QUnit.test( 'newFromImageInfo', ( assert ) => {
	const title = mw.Title.newFromText( 'File:Foo bar.jpg' );

	const pageID = 42;
	const repo = 'wikimediacommons';

	const size = 100;
	const width = 10;
	const height = 15;
	const mime = 'image/jpeg';
	const url = 'https://upload.wikimedia.org/wikipedia/commons/3/3a/Foobar.jpg';
	const descriptionurl = 'https://commons.wikimedia.org/wiki/File:Foobar.jpg';
	const descriptionshorturl = '';
	const datetime = '2011-07-04T23:31:14Z';
	const origdatetime = '2010-07-04T23:31:14Z';
	const description = 'This is a test file.';
	const source = 'WMF';
	const author = 'Ryan Kaldari';
	const authorCount = 1;
	const permission = 'only use for good, not evil';
	const deletionReason = 'poor quality';
	const attribution = 'Created by my cats on a winter morning';

	const imageData = ImageModel.newFromImageInfo(
		title,
		{
			...fixtures.imageinfoApi.makeBasic( {
				size,
				width,
				height,
				mime,
				url,
				descriptionurl,
				descriptionshorturl,
				extmetadata: {
					DateTime: { value: datetime },
					DateTimeOriginal: { value: origdatetime },
					ImageDescription: { value: description },
					Credit: { value: source },
					Artist: { value: author },
					AuthorCount: { value: authorCount },
					LicenseShortName: { value: 'cc0' },
					UsageTerms: { value: 'Creative Commons Zero' },
					AttributionRequired: { value: 'false' },
					Permission: { value: permission },
					Attribution: { value: attribution },
					DeletionReason: { value: deletionReason },
					GPSLatitude: { value: '39.12381283' },
					GPSLongitude: { value: '100.983829' },
					Restrictions: { value: 'trademarked' }
				}
			} ),
			pageid: pageID,
			imagerepository: repo
		}
	);

	assert.strictEqual( imageData.title, title, 'Title' );
	assert.propContains( imageData, {
		name: 'Foo bar',
		size,
		width,
		height,
		mimeType: mime,
		url,
		descriptionUrl: descriptionurl,
		descriptionShortUrl: descriptionshorturl,
		pageID,
		repo,
		uploadDateTime: datetime,
		anonymizedUploadDateTime: '20110704000000',
		creationDateTime: origdatetime,
		description,
		source,
		author,
		authorCount,
		permission,
		attribution,
		deletionReason,
		latitude: 39.12381283,
		longitude: 100.983829,
		restrictions: [ 'trademarked' ]
	}, 'ImageModel object' );

	assert.propContains( imageData.license, {
		shortName: 'cc0',
		longName: 'Creative Commons Zero',
		attributionRequired: false
	}, 'License object' );
	assert.true( $.isPlainObject( imageData.thumbUrls ), 'Thumb URL cache is set up properly' );
} );

QUnit.test( 'parseExtmeta()', ( assert ) => {
	const stringData = { value: 'foo' };
	const plaintextData = { value: 'fo<b>o</b>' };
	const integerData = { value: 3 };
	const integerStringData = { value: '3' };
	const zeroPrefixedIntegerStringData = { value: '03' };
	const floatData = { value: 1.23 };
	const floatStringData = { value: '1.23' };
	const booleanData = { value: 'yes' };
	const wrongBooleanData = { value: 'blah' };
	const listDataEmpty = { value: '' };
	const listDataSingle = { value: 'foo' };
	const listDataMultiple = { value: 'foo|bar|baz' };
	const missingData = undefined;

	assert.strictEqual( ImageModel.parseExtmeta( stringData, 'string' ), 'foo',
		'Extmeta string parsed correctly.' );
	assert.strictEqual( ImageModel.parseExtmeta( plaintextData, 'plaintext' ), 'foo',
		'Extmeta plaintext parsed correctly.' );
	assert.strictEqual( ImageModel.parseExtmeta( floatData, 'float' ), 1.23,
		'Extmeta float parsed correctly.' );
	assert.strictEqual( ImageModel.parseExtmeta( floatStringData, 'float' ), 1.23,
		'Extmeta float string parsed correctly.' );
	assert.strictEqual( ImageModel.parseExtmeta( booleanData, 'boolean' ), true,
		'Extmeta boolean string parsed correctly.' );
	assert.strictEqual( ImageModel.parseExtmeta( wrongBooleanData, 'boolean' ), undefined,
		'Extmeta boolean string with error ignored.' );
	assert.strictEqual( ImageModel.parseExtmeta( integerData, 'integer' ), 3,
		'Extmeta integer parsed correctly.' );
	assert.strictEqual( ImageModel.parseExtmeta( integerStringData, 'integer' ), 3,
		'Extmeta integer string parsed correctly.' );
	assert.strictEqual( ImageModel.parseExtmeta( zeroPrefixedIntegerStringData, 'integer' ), 3,
		'Extmeta zero-prefixed integer string parsed correctly.' );
	assert.deepEqual( ImageModel.parseExtmeta( listDataEmpty, 'list' ), [],
		'Extmeta empty list parsed correctly.' );
	assert.deepEqual( ImageModel.parseExtmeta( listDataSingle, 'list' ), [ 'foo' ],
		'Extmeta list with single element parsed correctly.' );
	assert.deepEqual( ImageModel.parseExtmeta( listDataMultiple, 'list' ), [ 'foo', 'bar', 'baz' ],
		'Extmeta list with multiple elements parsed correctly.' );
	assert.strictEqual( ImageModel.parseExtmeta( missingData, 'string' ), undefined,
		'Extmeta missing data parsed correctly.' );

	assert.strictEqual( ImageModel.parseExtmeta( { value: '1960-03-14' }, 'datetime' ), '1960-03-14',
		'Extmeta date is parsed correctly.' );
	assert.strictEqual( ImageModel.parseExtmeta( { value: '1960' }, 'datetime' ), '1960',
		'Extmeta year is parsed correctly.' );
	assert.strictEqual( ImageModel.parseExtmeta( { value: '1926<div style="display: none;">date QS:P571,+1926-00-00T00:00:00Z/9</div>' }, 'datetime' ), '1926',
		'Extmeta year is extracted from hidden div.' );

	assert.throws( () => {
		ImageModel.parseExtmeta( stringData, 'strong' );
	}, 'Exception is thrown on invalid argument' );
} );
