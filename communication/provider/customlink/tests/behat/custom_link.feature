@communication @communication_customlink @javascript
Feature: Communication custom link
  In order to facilitate easy access to an existing communication platform
  As a teacher
  I need to be able to make a custom communication link available in my course

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following config values are set as admin:
      | enablecommunicationsubsystem | 1 |

  @mick
  Scenario: As a teacher I can configure and access a link to a custom communication provider
    Given the following "courses" exist:
      | fullname  | shortname |
      | Course 1  | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    When I am on the "Course 1" "Course" page logged in as "teacher1"
#TODO: Try to avoid CSS elements
    And ".btn-footer-communication" "css_element" should not be visible
    And I navigate to "Communication" in current page administration
    And the "Communication service" select box should contain "Custom link"
    And I should not see "Custom link URL"
    And I select "Custom link" from the "Communication service" singleselect
    And I should see "Custom link URL"
    # Simulate a custom link using the FQDN of an internal URL to avoid external dependency.
    And I set the following fields to these values:
      | communicationroomname | Test URL           |
      | customlinkurl         | /stable_master/my/ |
#TODO: Have a better way to do a FQDN ^^
    And I press "Save changes"
    Then I should see "Your Custom link room is ready"
    And ".btn-footer-communication" "css_element" should be visible
    And I click on ".btn-footer-communication" "css_element"
    # Check the link hits the expected destination.
    And I switch to a second window
    #I switch to "test_url" window
    And I should see "Welcome, Teacher" in the "page-header" "region"
    And I close all opened windows
#And I log out
#TODO: May need to run as admin
    # Ensure any communication subsystem tasks have no impact on availability.
    And I run all adhoc tasks
    And I am on the "Course 1" course page
    #logged in as "student1"
    And ".btn-footer-communication" "css_element" should be visible
    And I click on ".btn-footer-communication" "css_element"
    And I switch to a second window
    #I switch to "test_url" window
    And I should see "Dashboard" in the "page-header" "region"

  Scenario: As a student I can access a link to a custom communication provider
    Given I am on the "Test course" "Course" page logged in as "student1"
    And ".btn-footer-communication" "css_element" should not be visible
    When the following "courses" exist:
      | fullname    | shortname   | selectedcommunication    | customlinkurl |
      | Test course | Test course | communication_customlink | /stable_master/my/           |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And I am on the "Course 1" course page
    And ".btn-footer-communication" "css_element" should be visible
    And I click on ".btn-footer-communication" "css_element"
    And I switch to "test_url" window
    And I should see "Welcome, Student" in the "page-header" "region"
