class PdfPage
  include PageObject

  a(:download_as_pdf, text: 'Download as PDF')
  a(:download_the_file, text: 'Download the file')
end
