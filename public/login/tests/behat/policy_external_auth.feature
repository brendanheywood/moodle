@auth @auth_db @core_login
Feature: Password policy check with external auth change password URL
  In order to prevent an infinite redirect loop when passwordpolicycheckonlogin is enabled
  for an external auth plugin that provides its own change password URL (e.g. auth_db, LDAP),
  Moodle must redirect directly to that URL rather than setting the auth_forcepasswordchange
  flag (which only Moodle's own change_password.php can clear, causing a permanent loop).

  Background:
    Given the following config values are set as admin:
      | passwordpolicycheckonlogin | 1       |
      | passwordpolicy             | 1       |
      | minpasswordlength          | 8       |
      | minpassworddigits          | 1       |
      | minpasswordupper           | 1       |
      | minpasswordlower           | 1       |
    And auth_db is configured with an external change password url "/login/index.php?extpwdchange=1"
    And an external auth_db user "extuser" exists with password "weak"
    And the following "users" exist:
      | username | auth | firstname | lastname | email               |
      | extuser  | db   | External  | User     | extuser@example.com |

  Scenario: User with a policy-failing password is sent to the external change URL and can log in normally afterwards
    # Step 1: Log in — weak password fails the policy, expect redirect to external change URL.
    When I am on homepage
    And I set the field "Username" to "extuser"
    And I set the field "Password" to "weak"
    And I press "Log in"
    Then the current url should contain "extpwdchange=1"

    # Step 2: The flag must NOT be set. If it were, every subsequent page would redirect again.
    And the user "extuser" should not have the "auth_forcepasswordchange" preference set

    # Step 3: Simulate returning from the external change URL — navigate directly to the homepage.
    # With the fix: user is on the homepage normally (loop broken).
    # Without the fix: would be redirected to the change URL again.
    When I am on site homepage
    Then the current url should not contain "extpwdchange=1"
    And I should see "You are logged in as"
