@report @report_status @report_security
Feature: Check report pages show a status summary bar
  In order to quickly understand the overall health of the system
  As an administrator
  I should see a summary bar counting results by status at the top of each check report page

  Scenario: System status report shows a summary bar
    Given I log in as "admin"
    When I navigate to "Reports > System status" in site administration
    Then "[data-region='check-summary-bar']" "css_element" should be visible
    And I should see "OK" in the "[data-region='check-summary-bar']" "css_element"

  Scenario: Security checks report shows a summary bar
    Given I log in as "admin"
    When I navigate to "Reports > Security checks" in site administration
    Then "[data-region='check-summary-bar']" "css_element" should be visible
    And I should see "OK" in the "[data-region='check-summary-bar']" "css_element"

  Scenario: Summary bar is not shown on the single check detail page
    Given I log in as "admin"
    When I visit "/report/status/index.php?detail=core_environment"
    Then "[data-region='check-summary-bar']" "css_element" should not exist
