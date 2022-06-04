@en.m.wikipedia.beta.wmflabs.org @firefox @test2.m.wikipedia.org @vagrant
Feature: Table of contents

  Background:
    Given I am using the mobile site
      #And in Firefox see bug T88288

  @smoke @integration
  Scenario: Don't show table of contents on mobile
    Given I am viewing the site in mobile mode
    When I go to a page that has sections
    Then I should not see the table of contents

  Scenario: Show table of contents on tablet
    Given I am viewing the site in tablet mode
    When I go to a page that has sections
    Then I should see the table of contents
