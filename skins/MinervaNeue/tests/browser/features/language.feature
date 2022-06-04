@chrome @test2.m.wikipedia.org
Feature: Language selection

  Background:
    Given I am using the mobile site

  @smoke @integration @en.m.wikipedia.beta.wmflabs.org
  Scenario: Language button
    Given I go to a page that has languages
    Then I should see the switch-language page action

  @smoke @integration @en.m.wikipedia.beta.wmflabs.org
  Scenario: Language button (on a page that doesn't have languages)
    Given I go to a page that does not have languages
    Then I should see the disabled switch-language page action

  @en.m.wikipedia.beta.wmflabs.org
  Scenario: Tapping icon opens language overlay
    Given I go to a page that has languages
    When I click the switch-language page action
    Then I should see the language overlay

  @en.m.wikipedia.beta.wmflabs.org
  Scenario: Tapping icon does not open language overlay (on a page that doesn't have languages)
    Given I go to a page that does not have languages
    When I click the switch-language page action
    Then I should not see the languages overlay

  @integration
  Scenario: Tapping the disabled icon shows a toast
    Given I go to a page that does not have languages
    When I click the switch-language page action
    Then I should see a toast with message "This page is not available in other languages."

  @en.m.wikipedia.beta.wmflabs.org
  Scenario: Closing language overlay (overlay button)
    Given I go to a page that has languages
    When I click the switch-language page action
    And I see the language overlay
    And I click the language overlay close button
    Then I should not see the languages overlay

  @en.m.wikipedia.beta.wmflabs.org
  Scenario: Closing language overlay (browser button)
    Given I go to a page that has languages
    When I click the switch-language page action
    And I see the language overlay
    And I click the browser back button
    Then I should not see the languages overlay

  @en.m.wikipedia.beta.wmflabs.org
  Scenario: Checking that there are no suggested language links
    Given I go to a page that has languages
    When I click the switch-language page action
    And I see the language overlay
    Then I should not see a suggested language link
    Then I should see a non-suggested language link

  @smoke @en.m.wikipedia.beta.wmflabs.org
  Scenario: Checking that the suggested language link has been created
    Given I go to a page that has languages
    And I click the switch-language page action
    And I see the language overlay
    And I click on a language from the list of all languages
    And I click the browser back button
    And I see the language overlay
    Then I should see a suggested language link
