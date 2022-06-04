Then(/^I should see "(.*?)" as added content$/) do |text|
  expect(on(DiffPage).inserted_content_element.text).to eq text
end

Then(/^I should see "(.*?)" as removed content$/) do |text|
  expect(on(DiffPage).deleted_content_element.text).to eq text
end
