@chrome @en.m.wikipedia.beta.wmflabs.org @firefox @test2.m.wikipedia.org @vagrant
Feature: Page actions menu when anonymous

  Background:
    Given I am using the mobile site
      And I am on the "Albert Einstein" page

  @feature-anon-editing-support
  Scenario: Receive notification message - Edit Icon
    When I click the edit button
    Then I see the anonymous editor warning

  Scenario: Receive notification message - Watchlist Icon
    When I click the watch star
    Then I should see a drawer with message "Keep track of this page and all changes to it."
