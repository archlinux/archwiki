Then(/^I should not see the table of contents$/) do
  on(ArticlePage) do |page|
    page.toc_element.when_not_visible
    expect(page.toc_element).not_to be_visible
  end
end

Then(/^I should see the table of contents$/) do
  expect(on(ArticlePage).toc_element.when_present(10)).to be_visible
end
