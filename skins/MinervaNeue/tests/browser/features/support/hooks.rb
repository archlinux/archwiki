# Needed for cucumber --dry-run -f stepdefs
require_relative 'env'

Before('@skip') do |scenario|
  scenario.skip_invoke!
end
