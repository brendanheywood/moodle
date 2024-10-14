@core @core_user
Feature: Admin users can log in as another admin

  Scenario: Admin logs in as another admin and sees the "Log in as" link
    Given the following "users" exist:
      | username | firstname | lastname | password | role    |
      | admin2   | second    | admin    | password | admin   |
    Given I log in as "admin"
    When I navigate to "Users > Accounts > Browse list of users" in site administration
    And I follow "second admin"
    And I follow "Log in as"
    Then I should see "You are logged in as second admin" in the ".usermenu" "css_element"
    And I log out
