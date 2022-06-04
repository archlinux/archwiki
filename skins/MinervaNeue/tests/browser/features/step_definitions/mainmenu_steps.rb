When(/^I click on the main navigation button$/) do
  on(ArticlePage).mainmenu_button_element.click
end

When(/^I click on "(.*?)" in the main navigation menu$/) do |text|
  step 'I click on the main navigation button'
  on(ArticlePage).navigation_element.link_element(text: text).when_visible.click
end

Then(/^I should see a link to "(.*?)" in the main navigation menu$/) do |text|
  expect(on(ArticlePage).navigation_element.link_element(text: text)).to be_visible
end

Then(/^I should not see a link to "(.*?)" in the main navigation menu$/) do |text|
  expect(on(ArticlePage).navigation_element.link_element(text: text)).not_to be_visible
end

Then(/^I should see a link to the about page$/) do
  expect(on(ArticlePage).about_link_element).to be_visible
end

Then(/^I should see a link to the disclaimer$/) do
  expect(on(ArticlePage).disclaimer_link_element.when_visible).to be_visible
end

Then(/^I should see a link to my user page in the main navigation menu$/) do
  expect(on(ArticlePage).navigation_element.link_element(href: /User:#{user}/, text: user_label)).to be_visible
end
