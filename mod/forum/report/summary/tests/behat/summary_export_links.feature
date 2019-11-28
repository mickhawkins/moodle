@mod @mod_forum @forumreport @forumreport_summary
Feature: Posts relevant to a user's summary report data can be exported
  In order to export a user's posts related to their data in the forum summary report
  As a teacher
  I need to be linked to the forum export in a way which passes any filter data to the export

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "groups" exist:
      | name    | course | idnumber |
      | Group A | C1     | G1       |
      | Group B | C1     | G2       |
    And the following "group members" exist:
      | user     | group |
      | teacher1 | G1    |
      | teacher1 | G2    |
      | student1 | G1    |
      | student2 | G2    |
    And the following "activities" exist:
      | activity | name   | description | course | idnumber | groupmode |
      | forum    | forum1 | C1 forum    | C1     | forum1   | 2         |
    And the following forum discussions exist in course "Course 1":
      | user     | forum  | name        | message        | group            | created                 |
      | student1 | forum1 | discussion1 | s1 group       | G1               | ##2019-01-01 09:00:00## |
      | student1 | forum1 | discussion2 | s1 no group    | All participants | ##2019-03-27 04:30:00## |
    And the following forum replies exist in course "Course 1":
      | user     | forum  | discussion  | message  | created                 |
      | student1 | forum1 | discussion1 | d1 reply | ##2019-02-14 20:00:00## |

  @javascript
  Scenario: Export link is only available where a user has some posts in the current view
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "forum1"
    When I navigate to "Summary report" in current page administration
    Then I should see "Student 1"
    And I should see "Student 2"
    And "Export" "button" should exist
    And "[title='Export posts for Student 1']" "css_element" should exist
    And "[title='Export posts for Student 2']" "css_element" should not exist
    And I click on "Groups" "button"
    And I click on "Group A" "checkbox" in the "filter-groups-popover" "region"
    And I click on "Save" "button" in the "filter-groups-popover" "region"
    And "Export" "button" should exist
    And "[title='Export posts for Student 1']" "css_element" should exist
    And "[title='Export posts for Student 2']" "css_element" should not exist
    And I should see "Student 1"
    And I should not see "Student 2"
    And I click on "Dates" "button"
    And I click on "filterdatetopopover[enabled]" "checkbox"
    And the field "filterdatetopopover[enabled]" matches value "1"
    And I set the following fields to these values:
      | filterdatetopopover[day]       | 1        |
      | filterdatetopopover[month]     | January  |
      | filterdatetopopover[year]      | 2000     |
    And I click on "Save" "button" in the "filter-dates-popover" "region"
    And I should see "Student 1"
    And I should not see "Student 2"
    And "Export" "button" should not exist
    And "[title='Export posts for Student 1']" "css_element" should not exist
    And "[title='Export posts for Student 2']" "css_element" should not exist

  @javascript
  Scenario: Export link only filters by user when no report filters are applied
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "forum1"
    When I navigate to "Summary report" in current page administration
    And I click on "Export" "button"
    Then I should see "Student 1" in the ".form-autocomplete-selection" "css_element"
    And I should not see "Student 2" in the ".form-autocomplete-selection" "css_element"
    And I should see "All discussions"
    And the field "from[enabled]" does not match value "1"
    And the field "to[enabled]" does not match value "1"

  @javascript
  Scenario: Export link applies relevant filters from report to the export
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "forum1"
    When I navigate to "Summary report" in current page administration
    And I click on "Dates" "button"
    And I click on "filterdatefrompopover[enabled]" "checkbox"
    And the field "filterdatefrompopover[enabled]" matches value "1"
    And I click on "filterdatetopopover[enabled]" "checkbox"
    And the field "filterdatetopopover[enabled]" matches value "1"
    And I set the following fields to these values:
      | filterdatefrompopover[day]     | 30       |
      | filterdatefrompopover[month]   | December |
      | filterdatefrompopover[year]    | 2018     |
      | filterdatetopopover[day]       | 4        |
      | filterdatetopopover[month]     | December |
      | filterdatetopopover[year]      | 2019     |
    And I click on "Save" "button" in the "filter-dates-popover" "region"
    And I click on "Groups" "button"
    And I click on "Group A" "checkbox" in the "filter-groups-popover" "region"
    And I click on "Save" "button" in the "filter-groups-popover" "region"
    And I click on "Export" "button"
    Then I should see "Student 1" in the ".form-autocomplete-selection" "css_element"
    And I should not see "Student 2" in the ".form-autocomplete-selection" "css_element"
    And I should see "discussion1"
    And I should not see "discussion2"
    And I should not see "All discussions"
    And the following fields match these values:
      | from[day]     | 30       |
      | from[month]   | December |
      | from[year]    | 2018     |
      | from[hour]    | 0        |
      | from[minute]  | 0        |
      | from[enabled] | 1        |
      | to[day]       | 4        |
      | to[month]     | December |
      | to[year]      | 2019     |
      | to[hour]      | 23       |
      | to[minute]    | 59       |
      | to[enabled]   | 1        |
