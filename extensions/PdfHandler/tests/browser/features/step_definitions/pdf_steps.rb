Then(/^Download as PDF should be present$/) do
  on(PdfPage).download_as_pdf_element.should exist
end
When(/^I click on Download as PDF$/) do
  on(PdfPage).download_as_pdf_element.when_present.click
end
Then(/^Download the file link should be present$/) do
  on(PdfPage).download_the_file_element.when_present(30).should exist
end
