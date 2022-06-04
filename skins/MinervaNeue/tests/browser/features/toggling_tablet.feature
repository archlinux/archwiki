@chrome @en.m.wikipedia.beta.wmflabs.org @firefox @vagrant
Feature: Toggling sections

  Background:
    Given I am using the mobile site

  Scenario: Section open by default on tablet
    Given I am viewing the site in tablet mode
      And I go to a page that has sections
    When I click on the first collapsible section heading
    Then I should not see the content of the first section
