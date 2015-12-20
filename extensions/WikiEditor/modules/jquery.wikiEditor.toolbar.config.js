/**
 * Configuration of Toolbar module for wikiEditor
 */
/*jshint camelcase:false, quotmark:false */
( function ( $, mw ) { $.wikiEditor.modules.toolbar.config = {

getDefaultConfig: function () {
	var fileNamespace = mw.config.get( 'wgFormattedNamespaces' )[6];
	var result = { 'toolbar': {
		// Main section
		'main': {
			'type': 'toolbar',
			'groups': {
				'format': {
					'tools': {
						'bold': {
							'labelMsg': 'wikieditor-toolbar-tool-bold',
							'type': 'button',
							'offset': {
								'default': [2, -574],
								'en': [2, -142],
								'cs': [2, -142],
								'de': [2, -214],
								'fa': [2, -142],
								'fr': [2, -286],
								'gl': [2, -358],
								'es': [2, -358],
								'he': [2, -142],
								'hu': [2, -214],
								'it': [2, -286],
								'nl': [2, -502],
								'pt': [2, -358],
								'pl': [2, -142],
								'ml': [2, -142]
							},
							'icon': {
								'default': 'format-bold.png',
								'en': 'format-bold-B.png',
								'cs': 'format-bold-B.png',
								'de': 'format-bold-F.png',
								'fa': 'format-bold-B.png',
								'fr': 'format-bold-G.png',
								'gl': 'format-bold-N.png',
								'es': 'format-bold-N.png',
								'eu': 'format-bold-L.png',
								'he': 'format-bold-B.png',
								'hu': 'format-bold-F.png',
								'hy': 'format-bold-hy.png',
								'it': 'format-bold-G.png',
								'ka': 'format-bold-ka.png',
								'ky': 'format-bold-ru.png',
								'nl': 'format-bold-V.png',
								'os': 'format-bold-os.png',
								'pt': 'format-bold-N.png',
								'pl': 'format-bold-B.png',
								'ru': 'format-bold-ru.png',
								'ml': 'format-bold-B.png'
							},
							'action': {
								'type': 'encapsulate',
								'options': {
									'pre': "'''",
									'periMsg': 'wikieditor-toolbar-tool-bold-example',
									'post': "'''"
								}
							}
						},
						'italic': {
							'section': 'main',
							'group': 'format',
							'id': 'italic',
							'labelMsg': 'wikieditor-toolbar-tool-italic',
							'type': 'button',
							'offset': {
								'default': [2, -718],
								'en': [2, -862],
								'cs': [2, -862],
								'de': [2, -934],
								'fa': [2, -862],
								'fr': [2, -862],
								'gl': [2, -790],
								'es': [2, -790],
								'he': [2, -862],
								'it': [2, -790],
								'ky': [2, -934],
								'nl': [2, -790],
								'os': [2, -934],
								'pt': [2, -862],
								'pl': [2, -862],
								'ru': [2, -934],
								'ml': [2, -862]
							},
							'icon': {
								'default': 'format-italic.png',
								'en': 'format-italic-I.png',
								'cs': 'format-italic-I.png',
								'de': 'format-italic-K.png',
								'fa': 'format-italic-I.png',
								'fr': 'format-italic-I.png',
								'gl': 'format-italic-C.png',
								'es': 'format-italic-C.png',
								'eu': 'format-italic-E.png',
								'he': 'format-italic-I.png',
								'hu': 'format-italic-D.png',
								'hy': 'format-italic-hy.png',
								'it': 'format-italic-C.png',
								'ka': 'format-italic-ka.png',
								'ky': 'format-italic-K.png',
								'nl': 'format-italic-C.png',
								'os': 'format-italic-K.png',
								'pt': 'format-italic-I.png',
								'pl': 'format-italic-I.png',
								'ru': 'format-italic-K.png',
								'ml': 'format-italic-I.png'
							},
							'action': {
								'type': 'encapsulate',
								'options': {
									'pre': "''",
									'periMsg': 'wikieditor-toolbar-tool-italic-example',
									'post': "''"
								}
							}
						}
					}
				},
				'insert': {
					'tools': {
						'xlink': {
							'labelMsg': 'wikieditor-toolbar-tool-xlink',
							'type': 'button',
							'icon': 'insert-xlink.png',
							'offset': [-70, 2],
							'action': {
								'type': 'encapsulate',
								'options': {
									'pre': "[",
									'periMsg': 'wikieditor-toolbar-tool-xlink-example',
									'post': "]"
								}
							}
						},
						'ilink': {
							'labelMsg': 'wikieditor-toolbar-tool-ilink',
							'type': 'button',
							'icon': 'insert-ilink.png',
							'offset': [2, -1582],
							'action': {
								'type': 'encapsulate',
								'options': {
									'pre': "[[",
									'periMsg': 'wikieditor-toolbar-tool-ilink-example',
									'post': "]]"
								}
							}
						},
						'file': {
							'labelMsg': 'wikieditor-toolbar-tool-file',
							'type': 'button',
							'icon': 'insert-file.png',
							'offset': [2, -1438],
							'action': {
								'type': 'encapsulate',
								'options': {
									'pre': '[[' + fileNamespace + ':',
									'periMsg': 'wikieditor-toolbar-tool-file-example',
									'post': "|" + mw.config.get( 'wgWikiEditorMagicWords' ).img_thumbnail + "]]"
								}
							}
						},
						'reference': {
							'labelMsg': 'wikieditor-toolbar-tool-reference',
							'filters': [ 'body.ns-subject' ],
							'type': 'button',
							'offset': [2, -1798],
							'icon': 'insert-reference.png',
							'action': {
								'type': 'encapsulate',
								'options': {
									'pre': "<ref>",
									'periMsg': 'wikieditor-toolbar-tool-reference-example',
									'post': "</ref>"
								}
							}
						},
						'signature': {
							'labelMsg': 'wikieditor-toolbar-tool-signature',
							'type': 'button',
							'offset': [2, -1870],
							'icon': 'insert-signature.png',
							'action': {
								'type': 'encapsulate',
								'options': {
									'pre': "--~~~~"
								}
							}
						}
					}
				}
			}
		},
		// Format section
		'advanced': {
			'labelMsg': 'wikieditor-toolbar-section-advanced',
			'type': 'toolbar',
			'groups': {
				'heading': {
					'tools': {
						'heading': {
							'labelMsg': 'wikieditor-toolbar-tool-heading',
							'type': 'select',
							'list': {
								'heading-2': {
									'labelMsg': 'wikieditor-toolbar-tool-heading-2',
									'action': {
										'type': 'encapsulate',
										'options': {
											'pre': '== ',
											'periMsg': 'wikieditor-toolbar-tool-heading-example',
											'post': ' ==',
											'regex': /^(\s*)(={1,6})(.*?)\2(\s*)$/,
											'regexReplace': "$1==$3==$4",
											'ownline': true
										}
									}
								},
								'heading-3': {
									'labelMsg': 'wikieditor-toolbar-tool-heading-3',
									'action': {
										'type': 'encapsulate',
										'options': {
											'pre': '=== ',
											'periMsg': 'wikieditor-toolbar-tool-heading-example',
											'post': ' ===',
											'regex': /^(\s*)(={1,6})(.*?)\2(\s*)$/,
											'regexReplace': "$1===$3===$4",
											'ownline': true
										}
									}
								},
								'heading-4': {
									'labelMsg': 'wikieditor-toolbar-tool-heading-4',
									'action': {
										'type': 'encapsulate',
										'options': {
											'pre': '==== ',
											'periMsg': 'wikieditor-toolbar-tool-heading-example',
											'post': ' ====',
											'regex': /^(\s*)(={1,6})(.*?)\2(\s*)$/,
											'regexReplace': "$1====$3====$4",
											'ownline': true
										}
									}
								},
								'heading-5': {
									'labelMsg': 'wikieditor-toolbar-tool-heading-5',
									'action': {
										'type': 'encapsulate',
										'options': {
											'pre': '===== ',
											'periMsg': 'wikieditor-toolbar-tool-heading-example',
											'post': ' =====',
											'regex': /^(\s*)(={1,6})(.*?)\2(\s*)$/,
											'regexReplace': "$1=====$3=====$4",
											'ownline': true
										}
									}
								}
							}
						}
					}
				},
				'format': {
					'labelMsg': 'wikieditor-toolbar-group-format',
					'tools': {
						'ulist': {
							'labelMsg': 'wikieditor-toolbar-tool-ulist',
							'type': 'button',
							'icon': {
								'default': 'format-ulist.png',
								'default-rtl': 'format-ulist-rtl.png'
							},
							'offset': {
								'default': [2, -1366],
								'default-rtl': [-70, -286]
							},
							'action': {
								'type': 'encapsulate',
								'options': {
									'pre': "* ",
									'periMsg': 'wikieditor-toolbar-tool-ulist-example',
									'post': "",
									'ownline': true,
									'splitlines': true
								}
							}
						},
						'olist': {
							'labelMsg': 'wikieditor-toolbar-tool-olist',
							'type': 'button',
							'icon': {
								'default': 'format-olist.png',
								'default-rtl': 'format-olist-rtl.png'
							},
							'offset': {
								'default': [2, -1078],
								'default-rtl': [-70, -358]
							},
							'action': {
								'type': 'encapsulate',
								'options': {
									'pre': "# ",
									'periMsg': 'wikieditor-toolbar-tool-olist-example',
									'post': "",
									'ownline': true,
									'splitlines': true
								}
							}
						},
						'nowiki': {
							'labelMsg': 'wikieditor-toolbar-tool-nowiki',
							'type': 'button',
							'icon': 'insert-nowiki.png',
							'offset': [-70, -70],
							'action': {
								'type': 'encapsulate',
								'options': {
									'pre': "<nowiki>",
									'periMsg': 'wikieditor-toolbar-tool-nowiki-example',
									'post': "</nowiki>"
								}
							}
						},
						'newline': {
							'labelMsg': 'wikieditor-toolbar-tool-newline',
							'type': 'button',
							'icon': 'insert-newline.png',
							'offset': [2, -1726],
							'action': {
								'type': 'encapsulate',
								'options': {
									'pre': "<br />\n"
								}
							}
						}
					}
				},
				'size': {
					'tools': {
						'big': {
							'labelMsg': 'wikieditor-toolbar-tool-big',
							'type': 'button',
							'icon': 'format-big.png',
							'offset': [2, 2],
							'action': {
								'type': 'encapsulate',
								'options': {
									'pre': "<big>",
									'periMsg': 'wikieditor-toolbar-tool-big-example',
									'post': "</big>"
								}
							}
						},
						'small': {
							'labelMsg': 'wikieditor-toolbar-tool-small',
							'type': 'button',
							'icon': 'format-small.png',
							'offset': [2, -1150],
							'action': {
								'type': 'encapsulate',
								'options': {
									'pre': "<small>",
									'periMsg': 'wikieditor-toolbar-tool-small-example',
									'post': "</small>"
								}
							}
						},
						'superscript': {
							'labelMsg': 'wikieditor-toolbar-tool-superscript',
							'type': 'button',
							'icon': 'format-superscript.png',
							'offset': [2, -1294],
							'action': {
								'type': 'encapsulate',
								'options': {
									'pre': "<sup>",
									'periMsg': 'wikieditor-toolbar-tool-superscript-example',
									'post': "</sup>"
								}
							}
						},
						'subscript': {
							'labelMsg': 'wikieditor-toolbar-tool-subscript',
							'type': 'button',
							'icon': 'format-subscript.png',
							'offset': [2, -1222],
							'action': {
								'type': 'encapsulate',
								'options': {
									'pre': "<sub>",
									'periMsg': 'wikieditor-toolbar-tool-subscript-example',
									'post': "</sub>"
								}
							}
						}
					}
				},
				'insert': {
					'labelMsg': 'wikieditor-toolbar-group-insert',
					'tools': {
						'gallery': {
							'labelMsg': 'wikieditor-toolbar-tool-gallery',
							'type': 'button',
							'icon': 'insert-gallery.png',
							'offset': [2, -1510],
							'action': {
								'type': 'encapsulate',
								'options': {
									'pre': "<gallery>\n",
									'periMsg': [
										'wikieditor-toolbar-tool-gallery-example', fileNamespace
									],
									'post': "\n</gallery>",
									'ownline': true
								}
							}
						},
						'table': {
							'labelMsg': 'wikieditor-toolbar-tool-table',
							'type': 'button',
							'icon': 'insert-table.png',
							'offset': [2, -1942],
							'filters': [ '#wpTextbox1:not(.toolbar-dialogs)' ],
							'action': {
								'type': 'encapsulate',
								'options': {
									'pre': "{| class=\"wikitable\" border=\"1\"\n|",
									'periMsg': 'wikieditor-toolbar-tool-table-example-old',
									'post': "\n|}",
									'ownline': true
								}
							}
						},
						'redirect': {
							'labelMsg': 'wikieditor-toolbar-tool-redirect',
							'type': 'button',
							'icon': {
								'default': 'insert-redirect.png',
								'default-rtl': 'insert-redirect-rtl.png'
							},
							'offset': {
								'default': [-70, -142],
								'default-rtl': [-70, -502]
							},
							'action': {
								'type': 'encapsulate',
								'options': {
									'pre': mw.config.get( 'wgWikiEditorMagicWords' ).redirect + ' [[',
									'periMsg': 'wikieditor-toolbar-tool-redirect-example',
									'post': "]]",
									'ownline': true
								}
							}
						}
					}
				}
			}
		},
		'characters': {
			'labelMsg': 'wikieditor-toolbar-section-characters',
			'type': 'booklet',
			'deferLoad': true,
			'pages': {
				'latin': {
					'labelMsg': 'special-characters-group-latin',
					'layout': 'characters',
					'characters': mw.language.specialCharacters.latin
				},
				'latinextended': {
					'labelMsg': 'special-characters-group-latinextended',
					'layout': 'characters',
					'characters': mw.language.specialCharacters.latinextended
				},
				'ipa': {
					'labelMsg': 'special-characters-group-ipa',
					'layout': 'characters',
					'characters': mw.language.specialCharacters.ipa
				},
				'symbols': {
					'labelMsg': 'special-characters-group-symbols',
					'layout': 'characters',
					'characters': mw.language.specialCharacters.symbols
				},
				'greek': {
					'labelMsg': 'special-characters-group-greek',
					'layout': 'characters',
					'language': 'el',
					'characters': mw.language.specialCharacters.greek
				},
				'cyrillic': {
					'labelMsg': 'special-characters-group-cyrillic',
					'layout': 'characters',
					'characters': mw.language.specialCharacters.cyrillic
				},
				// The core 28-letter alphabet, special letters for the Arabic language,
				// vowels, punctuation, digits.
				// Names of letters are written as in the Unicode charts.
				'arabic': {
					'labelMsg': 'special-characters-group-arabic',
					'layout': 'characters',
					'language': 'ar',
					'direction': 'rtl',
					'characters': mw.language.specialCharacters.arabic
				},
				// Characters for languages other than Arabic.
				'arabicextended': {
					'labelMsg': 'special-characters-group-arabicextended',
					'layout': 'characters',
					'language': 'ar',
					'direction': 'rtl',
					'characters': mw.language.specialCharacters.arabicextended
				},
				'hebrew': {
					'labelMsg': 'special-characters-group-hebrew',
					'layout': 'characters',
					'direction': 'rtl',
					'characters': mw.language.specialCharacters.hebrew
				},
				'bangla': {
					'labelMsg': 'special-characters-group-bangla',
					'language': 'bn',
					'layout': 'characters',
					'characters': mw.language.specialCharacters.bangla
				},
				'tamil': {
					'labelMsg': 'special-characters-group-tamil',
					'language': 'ta',
					'layout': 'characters',
					'characters': mw.language.specialCharacters.tamil
				},
				'telugu': {
					'labelMsg': 'special-characters-group-telugu',
					'language': 'te',
					'layout': 'characters',
					'characters': mw.language.specialCharacters.telugu
				},
				'sinhala': {
					'labelMsg': 'special-characters-group-sinhala',
					'language': 'si',
					'layout': 'characters',
					'characters': mw.language.specialCharacters.sinhala
				},
				'devanagari': {
					'labelMsg': 'special-characters-group-devanagari',
					'layout': 'characters',
					'characters': mw.language.specialCharacters.devanagari
				},
				'gujarati': {
					'labelMsg': 'special-characters-group-gujarati',
					'language': 'gu',
					'layout': 'characters',
					'characters': mw.language.specialCharacters.gujarati
				},
				'thai': {
					'labelMsg': 'special-characters-group-thai',
					'language': 'th',
					'layout': 'characters',
					'characters': mw.language.specialCharacters.thai
				},
				'lao': {
					'labelMsg': 'special-characters-group-lao',
					'language': 'lo',
					'layout': 'characters',
					'characters': mw.language.specialCharacters.lao
				},
				'khmer': {
					'labelMsg': 'special-characters-group-khmer',
					'language': 'km',
					'layout': 'characters',
					'characters': mw.language.specialCharacters.khmer
				}
			}
		},
		'help': {
			'labelMsg': 'wikieditor-toolbar-section-help',
			'type': 'booklet',
			'deferLoad': true,
			'pages': {
				'format': {
					'labelMsg': 'wikieditor-toolbar-help-page-format',
					'layout': 'table',
					'headings': [
						{ 'textMsg': 'wikieditor-toolbar-help-heading-description' },
						{ 'textMsg': 'wikieditor-toolbar-help-heading-syntax' },
						{ 'textMsg': 'wikieditor-toolbar-help-heading-result' }
					],
					'rows': [
						{
							'description': { 'htmlMsg': 'wikieditor-toolbar-help-content-italic-description' },
							'syntax': { 'htmlMsg': 'wikieditor-toolbar-help-content-italic-syntax' },
							'result': { 'htmlMsg': 'wikieditor-toolbar-help-content-italic-result' }
						},
						{
							'description': { 'htmlMsg': 'wikieditor-toolbar-help-content-bold-description' },
							'syntax': { 'htmlMsg': 'wikieditor-toolbar-help-content-bold-syntax' },
							'result': { 'htmlMsg': 'wikieditor-toolbar-help-content-bold-result' }
						},
						{
							'description': { 'htmlMsg': 'wikieditor-toolbar-help-content-bolditalic-description' },
							'syntax': { 'htmlMsg': 'wikieditor-toolbar-help-content-bolditalic-syntax' },
							'result': { 'htmlMsg': 'wikieditor-toolbar-help-content-bolditalic-result' }
						}
					]
				},
				'link': {
					'labelMsg': 'wikieditor-toolbar-help-page-link',
					'layout': 'table',
					'headings': [
						{ 'textMsg': 'wikieditor-toolbar-help-heading-description' },
						{ 'textMsg': 'wikieditor-toolbar-help-heading-syntax' },
						{ 'textMsg': 'wikieditor-toolbar-help-heading-result' }
					],
					'rows': [
						{
							'description': { 'htmlMsg': 'wikieditor-toolbar-help-content-ilink-description' },
							'syntax': { 'htmlMsg': 'wikieditor-toolbar-help-content-ilink-syntax' },
							'result': { 'htmlMsg': 'wikieditor-toolbar-help-content-ilink-result' }
						},
						{
							'description': { 'htmlMsg': 'wikieditor-toolbar-help-content-xlink-description' },
							'syntax': { 'htmlMsg': 'wikieditor-toolbar-help-content-xlink-syntax' },
							'result': { 'htmlMsg': 'wikieditor-toolbar-help-content-xlink-result' }
						}
					]
				},
				'heading': {
					'labelMsg': 'wikieditor-toolbar-help-page-heading',
					'layout': 'table',
					'headings': [
						{ 'textMsg': 'wikieditor-toolbar-help-heading-description' },
						{ 'textMsg': 'wikieditor-toolbar-help-heading-syntax' },
						{ 'textMsg': 'wikieditor-toolbar-help-heading-result' }
					],
					'rows': [
						{
							'description': { 'htmlMsg': 'wikieditor-toolbar-help-content-heading2-description' },
							'syntax': { 'htmlMsg': 'wikieditor-toolbar-help-content-heading2-syntax' },
							'result': { 'htmlMsg': 'wikieditor-toolbar-help-content-heading2-result' }
						},
						{
							'description': { 'htmlMsg': 'wikieditor-toolbar-help-content-heading3-description' },
							'syntax': { 'htmlMsg': 'wikieditor-toolbar-help-content-heading3-syntax' },
							'result': { 'htmlMsg': 'wikieditor-toolbar-help-content-heading3-result' }
						},
						{
							'description': { 'htmlMsg': 'wikieditor-toolbar-help-content-heading4-description' },
							'syntax': { 'htmlMsg': 'wikieditor-toolbar-help-content-heading4-syntax' },
							'result': { 'htmlMsg': 'wikieditor-toolbar-help-content-heading4-result' }
						},
						{
							'description': { 'htmlMsg': 'wikieditor-toolbar-help-content-heading5-description' },
							'syntax': { 'htmlMsg': 'wikieditor-toolbar-help-content-heading5-syntax' },
							'result': { 'htmlMsg': 'wikieditor-toolbar-help-content-heading5-result' }
						}
					]
				},
				'list': {
					'labelMsg': 'wikieditor-toolbar-help-page-list',
					'layout': 'table',
					'headings': [
						{ 'textMsg': 'wikieditor-toolbar-help-heading-description' },
						{ 'textMsg': 'wikieditor-toolbar-help-heading-syntax' },
						{ 'textMsg': 'wikieditor-toolbar-help-heading-result' }
					],
					'rows': [
						{
							'description': { 'htmlMsg': 'wikieditor-toolbar-help-content-ulist-description' },
							'syntax': { 'htmlMsg': 'wikieditor-toolbar-help-content-ulist-syntax' },
							'result': { 'htmlMsg': 'wikieditor-toolbar-help-content-ulist-result' }
						},
						{
							'description': { 'htmlMsg': 'wikieditor-toolbar-help-content-olist-description' },
							'syntax': { 'htmlMsg': 'wikieditor-toolbar-help-content-olist-syntax' },
							'result': { 'htmlMsg': 'wikieditor-toolbar-help-content-olist-result' }
						}
					]
				},
				'file': {
					'labelMsg': 'wikieditor-toolbar-help-page-file',
					'layout': 'table',
					'headings': [
						{ 'textMsg': 'wikieditor-toolbar-help-heading-description' },
						{ 'textMsg': 'wikieditor-toolbar-help-heading-syntax' },
						{ 'textMsg': 'wikieditor-toolbar-help-heading-result' }
					],
					'rows': [
						{
							'description': { 'htmlMsg': 'wikieditor-toolbar-help-content-file-description' },
							'syntax': { 'htmlMsg': [
								'wikieditor-toolbar-help-content-file-syntax',
								fileNamespace,
								mw.config.get( 'wgWikiEditorMagicWords' ).img_thumbnail,
								mw.message( 'wikieditor-toolbar-help-content-file-caption' ).text()
							] },
							'result': { 'html': '<div class="thumbinner" style="width: 102px;">' +
								'<a href="#" class="image">' +
								'<img alt="" src="' + $.wikiEditor.imgPath + 'toolbar/example-image.png" width="100" height="50" class="thumbimage"/>' +
								'</a>' +
								'<div class="thumbcaption"><div class="magnify">' +
								'<a title="' + mw.message( 'thumbnail-more' ).escaped() + '" class="internal" href="#"></a>' +
								'</div>' + mw.message( 'wikieditor-toolbar-help-content-file-caption' ).escaped() + '</div>' +
								'</div>'
							}
						}
					]
				},
				'reference': {
					'labelMsg': 'wikieditor-toolbar-help-page-reference',
					'layout': 'table',
					'headings': [
						{ 'textMsg': 'wikieditor-toolbar-help-heading-description' },
						{ 'textMsg': 'wikieditor-toolbar-help-heading-syntax' },
						{ 'textMsg': 'wikieditor-toolbar-help-heading-result' }
					],
					'rows': [
						{
							'description': { 'htmlMsg': 'wikieditor-toolbar-help-content-reference-description' },
							'syntax': { 'htmlMsg': 'wikieditor-toolbar-help-content-reference-syntax' },
							'result': { 'htmlMsg': 'wikieditor-toolbar-help-content-reference-result' }
						},
						{
							'description': { 'htmlMsg': 'wikieditor-toolbar-help-content-named-reference-description' },
							'syntax': { 'htmlMsg': 'wikieditor-toolbar-help-content-named-reference-syntax' },
							'result': { 'htmlMsg': 'wikieditor-toolbar-help-content-named-reference-result' }
						},
						{
							'description': { 'htmlMsg': 'wikieditor-toolbar-help-content-rereference-description' },
							'syntax': { 'htmlMsg': 'wikieditor-toolbar-help-content-rereference-syntax' },
							'result': { 'htmlMsg': 'wikieditor-toolbar-help-content-rereference-result' }
						},
						{
							'description': { 'htmlMsg': 'wikieditor-toolbar-help-content-showreferences-description' },
							'syntax': { 'htmlMsg': 'wikieditor-toolbar-help-content-showreferences-syntax' },
							'result': { 'htmlMsg': 'wikieditor-toolbar-help-content-showreferences-result' }
						}
					]
				},
				'discussion': {
					'labelMsg': 'wikieditor-toolbar-help-page-discussion',
					'layout': 'table',
					'headings': [
						{ 'textMsg': 'wikieditor-toolbar-help-heading-description' },
						{ 'textMsg': 'wikieditor-toolbar-help-heading-syntax' },
						{ 'textMsg': 'wikieditor-toolbar-help-heading-result' }
					],
					'rows': [
						{
							'description': {
								'htmlMsg': 'wikieditor-toolbar-help-content-signaturetimestamp-description'
							},
							'syntax': { 'htmlMsg': 'wikieditor-toolbar-help-content-signaturetimestamp-syntax' },
							'result': { 'htmlMsg': 'wikieditor-toolbar-help-content-signaturetimestamp-result' }
						},
						{
							'description': { 'htmlMsg': 'wikieditor-toolbar-help-content-signature-description' },
							'syntax': { 'htmlMsg': 'wikieditor-toolbar-help-content-signature-syntax' },
							'result': { 'htmlMsg': 'wikieditor-toolbar-help-content-signature-result' }
						},
						{
							'description': { 'htmlMsg': 'wikieditor-toolbar-help-content-indent-description' },
							'syntax': { 'htmlMsg': 'wikieditor-toolbar-help-content-indent-syntax' },
							'result': { 'htmlMsg': 'wikieditor-toolbar-help-content-indent-result' }
						}
					]
				}
			}
		}
	} };

	// If this page is not a talk page and not in a namespaces listed in
	// wgExtraSignatureNamespaces, remove the signature button
	if ( mw.config.get( 'wgNamespaceNumber' ) % 2 === 0 &&
		$.inArray( mw.config.get( 'wgNamespaceNumber' ), mw.config.get( 'wgExtraSignatureNamespaces' ) ) === -1
	) {
		delete result.toolbar.main.groups.insert.tools.signature;
	}

	return result;
}

}; } ) ( jQuery, mediaWiki );
