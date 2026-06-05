@tool @tool_task @javascript
Feature: Run scheduled tasks from the administration UI
  In order to run scheduled tasks outside of their normal schedule
  As an admin
  I need to be able to run a task immediately or schedule it to run at the next cron execution

  Background:
    Given the following config values are set as admin:
      | enablerunnow | 1            | tool_task |
      | pathtophp    | /usr/bin/php |           |
    And the scheduled task "\core\task\send_new_user_passwords_task" has a next run time in the future
    And I log in as "admin"
    And I navigate to "Server > Tasks > Scheduled tasks" in site administration

  # Run now

  Scenario: Run now button appears for an enabled task
    Then I should see "Run now" in the "Log table cleanup" "table_row"

  Scenario: Run now button does not appear for a task whose plugin is disabled
    Then I should not see "Run now" in the "Synchronise users task" "table_row"

  Scenario: Confirming Run now executes the task and shows output
    When I click on "Run now" "link" in the "Log table cleanup" "table_row"
    Then I should see "Are you sure you want to run this task" in the ".modal-dialog" "css_element"
    And I should see "The task will run on the web server" in the ".modal-dialog" "css_element"
    When I click on "Run now" "button" in the ".modal-dialog" "css_element"
    Then I should see "Log table cleanup"
    And I should see "Run again"

  Scenario: Cancelling Run now returns to the scheduled tasks list without running
    When I click on "Run now" "link" in the "Log table cleanup" "table_row"
    Then I should see "Are you sure you want to run this task" in the ".modal-dialog" "css_element"
    When I click on "Cancel" "button" in the ".modal-dialog" "css_element"
    Then I should see "Scheduled tasks"
    And I should not see "Run again"

  # Run ASAP

  Scenario: Run ASAP button appears for an enabled task scheduled for the future
    Then I should see "Run ASAP" in the "Send new user passwords" "table_row"

  Scenario: Run ASAP button does not appear when the task is already due
    Given the scheduled task "\core\task\send_new_user_passwords_task" has a next run time in the past
    And I navigate to "Server > Tasks > Scheduled tasks" in site administration
    Then I should not see "Run ASAP" in the "Send new user passwords" "table_row"

  Scenario: Run ASAP button does not appear for a disabled task
    Given the scheduled task "\core\task\send_new_user_passwords_task" is disabled
    And I navigate to "Server > Tasks > Scheduled tasks" in site administration
    Then I should not see "Run ASAP" in the "Send new user passwords" "table_row"

  Scenario: Confirming Run ASAP schedules the task and shows a success message
    When I click on "Run ASAP" "link" in the "Send new user passwords" "table_row"
    Then I should see "Are you sure you want to run this task" in the ".modal-dialog" "css_element"
    And I should see "The task will run via cron at the next available time." in the ".modal-dialog" "css_element"
    When I click on "Run ASAP" "button" in the ".modal-dialog" "css_element"
    Then I should see "has been scheduled to run ASAP"

  Scenario: Cancelling Run ASAP returns to the scheduled tasks list without changes
    When I click on "Run ASAP" "link" in the "Send new user passwords" "table_row"
    Then I should see "Are you sure you want to run this task" in the ".modal-dialog" "css_element"
    When I click on "Cancel" "button" in the ".modal-dialog" "css_element"
    Then I should see "Scheduled tasks"
    And I should not see "has been scheduled to run ASAP"
