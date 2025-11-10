@tool @tool_task @javascript
Feature: Manage scheduled adhoc tasks
  In order to configure scheduled adhoc tasks
  As an admin
  I need to be able to disable, enable, edit and reset to default scheduled adhoc tasks

  Background:
    Given I log in as "admin"
    And I navigate to "Server > Tasks > Scheduled ad hoc tasks" in site administration

  Scenario: Disable scheduled adhoc task
    When I click on "Edit scheduled ad hoc task: Asynchronous backup task" "link" in the "Asynchronous backup task" "table_row"
    Then I should see "Edit scheduled ad hoc task: Asynchronous backup task"
    And I set the following fields to these values:
      | disabled             | 1 |
    And I press "Save changes"
    Then I should see "Changes saved"
    And I should see "No" in the "Asynchronous backup task" "table_row"
    And I should see "Asynchronous backup task" in the "tr.table-primary" "css_element"

  Scenario: Enable scheduled adhoc task
    When I click on "Edit scheduled ad hoc task: Asynchronous backup task" "link" in the "Asynchronous backup task" "table_row"
    Then I should see "Edit scheduled ad hoc task: Asynchronous backup task"
    And I set the following fields to these values:
      | disabled             | 0 |
    And I press "Save changes"
    Then I should see "Changes saved"
    And I should not see "Task disabled" in the "Asynchronous backup task" "table_row"
    And I should see "Asynchronous backup task" in the "tr.table-primary" "css_element"

  Scenario: Edit scheduled adhoc task
    When I click on "Edit scheduled ad hoc task: Asynchronous backup task" "link" in the "Asynchronous backup task" "table_row"
    Then I should see "Edit scheduled ad hoc task: Asynchronous backup task"
    And I should see "\core\task\asynchronous_backup_task"
    And I should see "From component: Core"
    And I should see "Default: *" in the "Minute" "fieldset"
    And I should see "Default: *" in the "Day" "fieldset"
    And I set the following fields to these values:
      | minute               | frog |
    And I press "Save changes"
    And I should see "Data submitted is invalid"
    And I set the following fields to these values:
      | minute               | */5 |
      | hour                 | 1   |
      | day                  | 2   |
      | month                | 3   |
      | dayofweek            | 4   |
    And I press "Save changes"
    And I should see "Changes saved"
    And the following should exist in the "admintable" table:
      | Component | Minute         | Hour         | Day          | Day of week  | Month        |
      | Core      | */5 Default: * | 1 Default: * | 2 Default: * | 4 Default: * | 3 Default: * |
    And I should see "Asynchronous backup task" in the "tr.table-primary" "css_element"
    And I should see "*/5 Default: *" in the "td.table-warning" "css_element"

  Scenario: Reset scheduled adhoc task to default
    When I click on "Edit scheduled ad hoc task: Asynchronous backup task" "link" in the "Asynchronous backup task" "table_row"
    Then I should see "Edit scheduled ad hoc task: Asynchronous backup task"
    And I set the following fields to these values:
      | resettodefaults      | 1   |
    And I press "Save changes"
    Then I should see "Changes saved"
    And the following should not exist in the "admintable" table:
      | Name                     | Minute | Hour | Day | Day of week | Month |
      | Asynchronous backup task | */5    | 1    | 2   | 4           | 3     |
    And I should see "Asynchronous backup task" in the "tr.table-primary" "css_element"
