@chrome @firefox @en.m.wikipedia.beta.wmflabs.org @vagrant
Feature: Wikidata descriptions

  Background:
    Given I am using the mobile site
      And I am on the "Main Page" page

  Scenario: Description does not appear on Main Page
    Then I should not see a tagline

  Scenario: Description appears on main namespace
    When I am on the "Albert Einstein" page
    Then I should see a tagline

  Scenario: Description does not appear on non-main namespaces
    Given I am on the "Talk:Contributions" page
    Then I should not see a tagline
