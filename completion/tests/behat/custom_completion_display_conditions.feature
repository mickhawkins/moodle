@core @core_completion
Feature: Allow teachers to edit the visibility of completion conditions in a course
  In order to show students the course completion conditions in a course
  As a teacher
  I need to be able to edit completion conditions settings

  @javascript
  Scenario: Configure completion condition displaying
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | First | teacher1@example.com |
      | student1 | Student | First | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity  | course | idnumber | name        |
      | choice    | C1     | c1       | Test choice |
    And I log in as "teacher1"

    And I am on "Course 1" course homepage with editing mode on
    And I navigate to "Edit settings" in current page administration
    When I set the following fields to these values:
      | Enable completion tracking | Yes |
      | Show completion conditions | Yes |
    And I click on "Save and display" "button"
    And I follow "Test choice"
    When I navigate to "Edit settings" in current page administration
    And I set the following fields to these values:
      | Completion tracking | Students can manually mark the activity as completed |
    And I press "Save and display"
    Then I should see "Mark as done"
    When I navigate to "Edit settings" in current page administration
    And I set the following fields to these values:
      | completion | Show activity as complete when conditions are met|
      | completionsubmit | 1 |
    And I press "Save and display"
    Then I should see "Make a choice"
    When I navigate to "Edit settings" in current page administration
    And I expand all fieldsets
    And I click on "Unlock completion options" "button"
    And I set the following fields to these values:
      | Completion tracking | Do not indicate activity completion |
    And I press "Save and display"
    Then I should not see "Mark as done"
    And I am on "Course 1" course homepage with editing mode on
    And I navigate to "Edit settings" in current page administration
    When I set the following fields to these values:
      | Show completion conditions | No |
    And I click on "Save and display" "button"
    And I follow "Test choice"
    Then I should not see "Make a choice"
    And I am on "Course 1" course homepage with editing mode on
    And I navigate to "Edit settings" in current page administration
    When I set the following fields to these values:
      | Enable completion tracking | No |
    And I click on "Save and display" "button"
    And I follow "Test choice"
    Then I should not see "Mark as done"
