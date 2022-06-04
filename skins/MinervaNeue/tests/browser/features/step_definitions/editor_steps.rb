Given(/^I am on a page that has the following edits:$/) do |table|
  page = 'Selenium_diff_test_'.concat(@random_string)
  table.rows.each { |(text)| api.edit(title: page, text: text) }
  visit(ArticlePage, using_params: { article_name: page })
end

When(/^I clear the editor$/) do
  on(ArticlePage).editor_textarea_element.when_present.clear
end

When(/^I click the edit button$/) do
  on(ArticlePage).edit_link_element.when_present.click
end

When(/^I click the wikitext editor overlay close button$/) do
  on(ArticlePage).editor_overlay_close_button_element.when_present.click
end

When(/^I do not see the wikitext editor overlay$/) do
  on(ArticlePage).editor_overlay_element.when_not_visible
end

When(/^I see the wikitext editor overlay$/) do
  on(ArticlePage).editor_textarea_element.when_present
end

When(/^I type "(.+)" into the editor$/) do |text|
  on(ArticlePage).editor_textarea_element.when_present.send_keys(text)
end

Then(/^I should not see the wikitext editor overlay$/) do
  expect(on(ArticlePage).editor_overlay_element).not_to be_visible
end

Then(/^I see the anonymous editor warning$/) do
  expect(on(ArticlePage).anon_editor_warning_element.when_present).to be_visible
end
