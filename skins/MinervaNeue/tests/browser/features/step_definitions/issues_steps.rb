When(/^I click the overlay issue close button$/) do
  on(ArticlePage).overlay_close_button_element.when_present.click
end

When(/^I click the page issues stamp$/) do
  on(ArticlePage).issues_stamp_element.when_present.click
end

When(/^I see the issues overlay$/) do
  on(ArticlePage).overlay_element.when_present
end

When(/^this page has issues$/) do
  on(ArticlePage).wait_until_rl_module_ready('skins.minerva.scripts')
  on(ArticlePage).issues_stamp_element.when_present
end

Then(/^I should not see the issues overlay$/) do
  expect(on(ArticlePage).overlay_element).not_to be_visible
end

Then(/^I should see the issues overlay$/) do
  expect(on(ArticlePage).overlay_element.when_present).to be_visible
end
