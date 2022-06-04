When(/^I click on the first collapsible section heading$/) do
  on(ArticlePage) do |page|
    page.wait_until_rl_module_ready('mobile.init')
    page.first_section_element.when_present.click
  end
end

Then(/^I should not see the content of the first section$/) do
  expect(on(ArticlePage).first_section_content_element).not_to be_visible
end

Then(/^I should see the content of the first section$/) do
  expect(on(ArticlePage).first_section_content_element.when_present(10)).to be_visible
end

Then(/^the heading element with id "(.*?)" should be visible$/) do |id|
  expect(on(ArticlePage).span_element(id: id).when_present(10)).to be_visible
end
