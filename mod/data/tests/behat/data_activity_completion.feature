@mod @mod_data @core_completion
Feature: View activity completion in the database activity
  In order to have visibility of database completion requirements
  As a student
  I need to be able to view my database completion progress

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Vinnie    | Student1 | student1@example.com |
      | teacher1 | Darrell   | Teacher1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user | course | role           |
      | student1 | C1 | student        |
      | teacher1 | C1 | editingteacher |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Edit settings" in current page administration
    And I expand all fieldsets
    And I set the following fields to these values:
      | Enable completion tracking | Yes |
      | Show completion conditions | Yes |
    And I press "Save and display"
    And I turn editing mode on
    And I add a "Database" to section "1" and I fill the form with:
      | Name                                       | Music history                                     |
      | Aggregate type                             | Average of ratings                                |
      | Completion tracking                        | Show activity as complete when conditions are met |
      | Require view                               | 1                                                 |
      | Require grade                              | 1                                                 |
      | completionentriesenabled                   | 1                                                 |
      | completionentries                          | 1                                                 |
    And I follow "Music history"
    And I add a "Text input" field to "Music history" database and I fill the form with:
      | Field name | Instrument types |
    And I log out

  Scenario: View automatic completion items as a teacher and confirm all tabs display conditions
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I follow "Music history"
    Then "Music history" should have the "View" completion condition
    And "Music history" should have the "Make entries: 1" completion condition
    And "Music history" should have the "Receive a grade" completion condition
    And I follow "View single"
    And "Music history" should have the "View" completion condition
    And "Music history" should have the "Make entries: 1" completion condition
    And "Music history" should have the "Receive a grade" completion condition
    And I follow "Search"
    And "Music history" should have the "View" completion condition
    And "Music history" should have the "Make entries: 1" completion condition
    And "Music history" should have the "Receive a grade" completion condition
    And I follow "Add entry"
    And "Music history" should have the "View" completion condition
    And "Music history" should have the "Make entries: 1" completion condition
    And "Music history" should have the "Receive a grade" completion condition
    And I follow "Export"
    And "Music history" should have the "View" completion condition
    And "Music history" should have the "Make entries: 1" completion condition
    And "Music history" should have the "Receive a grade" completion condition
    And I follow "Templates"
    And "Music history" should have the "View" completion condition
    And "Music history" should have the "Make entries: 1" completion condition
    And "Music history" should have the "Receive a grade" completion condition
    And I follow "Fields"
    And "Music history" should have the "View" completion condition
    And "Music history" should have the "Make entries: 1" completion condition
    And "Music history" should have the "Receive a grade" completion condition
    And I follow "Presets"
    And "Music history" should have the "View" completion condition
    And "Music history" should have the "Make entries: 1" completion condition
    And "Music history" should have the "Receive a grade" completion condition

  Scenario: View automatic completion items as a student
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Music history"
    And the "View" completion condition of "Music history" is displayed as "done"
    And the "Make entries: 1" completion condition of "Music history" is displayed as "todo"
    And the "Receive a grade" completion condition of "Music history" is displayed as "todo"
    When I am on "Course 1" course homepage
    And I follow "Music history"
    And I am on "Course 1" course homepage
    And I add an entry to "Music history" database with:
      | Instrument types | Drums |
    And I press "Save and view"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Music history"
    And I follow "View single"
    And I set the field "rating" to "3"
    And I press "Rate"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Music history"
    Then the "View" completion condition of "Music history" is displayed as "done"
    And the "Make entries: 1" completion condition of "Music history" is displayed as "done"
    And the "Receive a grade" completion condition of "Music history" is displayed as "done"

  @javascript
  Scenario: Use manual completion
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Music history"
    And I navigate to "Edit settings" in current page administration
    And I expand all fieldsets
    And I set the field "Completion tracking" to "Students can manually mark the activity as completed"
    And I press "Save and display"
    # Teacher view.
    And the manual completion button for "Music history" should be disabled
    And I log out
    # Student view.
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Music history"
    Then the manual completion button of "Music history" is displayed as "Mark as done"
    And I toggle the manual completion state of "Music history"
    And the manual completion button of "Music history" is displayed as "Done"
