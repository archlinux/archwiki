#!/bin/sh

# Get parameters from environment

export PDFHANDLER_INFO="${PDFHANDLER_INFO:-pdfinfo}"
export PDFHANDLER_TOTEXT="${PDFHANDLER_TOTEXT:-pdftotext}"

runInfo() {
	# Note in poppler 0.26 the -meta and page data options worked together,
	# but as of poppler 0.48 they must be queried separately.
	# https://bugs.freedesktop.org/show_bug.cgi?id=96801
	# Report metadata as UTF-8 text...and report XMP metadata
	"$PDFHANDLER_INFO" \
		-enc 'UTF-8' \
		-meta \
		file.pdf > meta

	# Report metadata as UTF-8 text...and report page sizes for all pages
	"$PDFHANDLER_INFO" \
		-enc 'UTF-8' \
		-l 9999999 \
		file.pdf > pages

}

runToText() {
	"$PDFHANDLER_TOTEXT" \
		file.pdf - > text
	# Store exit code so we can use it later
	echo $? > text_exit_code
}

if [ -x "$PDFHANDLER_INFO" ]; then
	runInfo
fi

if [ -x "$PDFHANDLER_TOTEXT" ]; then
	runToText
fi
