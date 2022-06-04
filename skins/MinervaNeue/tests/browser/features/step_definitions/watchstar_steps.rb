Given(/^I am viewing a watched page$/) do
  api.create_page 'Selenium mobile watch test', 'watch test'
  api.watch_page 'Selenium mobile watch test'
  step 'I am on the "Selenium mobile watch test" page'
end

Given(/^I am viewing an unwatched page$/) do
  api.create_page 'Selenium mobile watch test', 'watch test'
  api.unwatch_page 'Selenium mobile watch test'
  step 'I am on the "Selenium mobile watch test" page'
end
