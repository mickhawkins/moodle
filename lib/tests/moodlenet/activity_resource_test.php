<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace core\moodlenet;

/**
 * Unit tests for {@see activity_resource}.
 *
 * @coversDefaultClass \core\moodlenet\activity_resource
 * @package core
 * @copyright 2023 Michael Hawkins <michaelh@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_resource_test extends \advanced_testcase {

    /**
     * Test core\moodlenet\activity_resource get methods.
     *
     * @covers ::get_courseid
     * @covers ::get_get_cm
     * @covers ::get_get_name
     * @covers ::get_description
     * @return void
     */
    public function test_getters() {
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        $assigndata = [
            'course' => $course->id,
            'name' => 'Extremely interesting assignment',
            'intro' => 'A great assignment to share',
        ];
        $assign = $generator->create_module('assign', $assigndata);

        $resource = new activity_resource($course->id, $assign->cmid);

        // Verify ::get_courseid returns correct course ID.
        $this->assertEquals($course->id, $resource->get_courseid());

        // Verify ::get_cm fetches the correct cm_info object.
        $modinfo = get_fast_modinfo($course->id);
        $cminfo = $modinfo->get_cm($assign->cmid);
        $this->assertEqualsCanonicalizing($cminfo, $resource->get_cm());

        // Verify ::get_name returns the correct activity name.
        $this->assertEquals($assigndata['name'], $resource->get_name());

        // Verify get_description returns the correct activity description.
        $this->assertEquals($assigndata['intro'], $resource->get_description());
    }
}
