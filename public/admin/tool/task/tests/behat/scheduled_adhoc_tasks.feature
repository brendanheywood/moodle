@tool @tool_task @javascript
Feature: Manage scheduled adhoc task
  In order to manage scheduled adhoc tasks
  As an admin
  I need to be able to view delete scheduled adhoc tasks

  Background:
    Given the time is frozen at "2025-12-12 11:30:00"
    Given the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And I log in as "admin"
    And I navigate to "Server > Tasks > Scheduled ad hoc tasks" in site administration
    And I click on "Edit scheduled ad hoc task: Asynchronous backup task" "link" in the "Asynchronous backup task" "table_row"
    And I set the following fields to these values:
      | minute               | 0  |
      | hour                 | 12 |
      | day                  | *  |
      | month                | *  |
      | dayofweek            | *  |
      | disabled             | 0  |
    And I press "Save changes"
    And I should see "Changes saved"
    And I am on the "Course 1" "backup" page
    And I press "Jump to final step"

  Scenario: View scheduled adhoc tasks next run time
    Given I log in as "admin"
    And I navigate to "Server > Tasks > Ad hoc tasks" in site administration
    Then "asynchronous_backup_task" "table_row" should exist
    Then the following should exist in the "Ad hoc tasks" table:
      | Component / Class name   | Next run |
      | asynchronous_backup_task | Friday, 12 December 2025, 12:00 PM |
    And I click on "asynchronous_backup_task" "link" in the "Ad hoc tasks" "table"
    And the following should exist in the "\core\task\asynchronous_backup_task Ad hoc tasks" table:
      | Next run |
      | Friday, 12 December 2025, 12:00 PM |

  Scenario: Delete an existing scheduled adhoc tasks
    Given I log in as "admin"
    And I navigate to "Server > Tasks > Ad hoc tasks" in site administration
    Then "asynchronous_backup_task" "table_row" should exist
    And I click on "asynchronous_backup_task" "link" in the "Ad hoc tasks" "table"
    When I follow "Delete"
    And I click on "Delete" "button" in the ".modal-dialog" "css_element"
    Then I navigate to "Server > Tasks > Ad hoc tasks" in site administration
    And "asynchronous_backup_task" "table_row" should not exist
