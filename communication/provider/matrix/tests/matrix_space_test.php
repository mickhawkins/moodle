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

namespace communication_matrix;

/**
 * Tests for the matrix_space class.
 *
 * @package    communication_matrix
 * @category   test
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \communication_matrix\matrix_room
 */
class matrix_space_test extends \advanced_testcase {

    /**
     * Test for load_by_processor_id with no record.
     *
     * @covers ::load_by_processor_id
     */
    public function test_load_by_processor_id_none(): void {
        $this->assertNull(matrix_space::load_by_processor_id(999999999));
    }

    /**
     * Test for load_by_processor_id with valid records.
     *
     * @covers ::create_room_record
     * @covers ::__construct
     * @covers ::load_by_processor_id
     * @covers ::get_processor_id
     * @covers ::get_room_id
     * @covers ::get_topic
     */
    public function test_create_room_record(): void {
        $this->resetAfterTest();

        $space = matrix_space::create_room_record(
            processorid: 12345,
            topic: 'The topic of this space is thusly',
        );

        $this->assertInstanceOf(matrix_space::class, $space);
        $this->assertEquals(12345, $space->get_processor_id());
        $this->assertEquals('The topic of this space is thusly', $space->get_topic());
        $this->assertNull($space->get_room_id());

        $space = matrix_space::create_room_record(
            processorid: 54321,
            topic: 'The topic of this space is thusly',
            roomid: 'This is a spaceid',
        );

        $this->assertInstanceOf(matrix_space::class, $space);
        $this->assertEquals(54321, $space->get_processor_id());
        $this->assertEquals('The topic of this space is thusly', $space->get_topic());
        $this->assertEquals('This is a spaceid', $space->get_room_id());

        $reloadedspace = matrix_space::load_by_processor_id(54321);
        $this->assertEquals(54321, $reloadedspace->get_processor_id());
        $this->assertEquals('The topic of this space is thusly', $reloadedspace->get_topic());
        $this->assertEquals('This is a spaceid', $reloadedspace->get_room_id());

    }

    /**
     * Test for update_room_record.
     *
     * @covers ::update_room_record
     */
    public function test_update_room_record(): void {
        $this->resetAfterTest();

        $space = matrix_space::create_room_record(
            processorid: 12345,
            topic: 'The topic of this space is that',
        );

        // Add a roomid.
        $space->update_room_record(
            roomid: 'This is a spaceid',
        );

        $this->assertEquals('This is a spaceid', $space->get_room_id());
        $this->assertEquals('The topic of this space is that', $space->get_topic());
        $this->assertEquals(12345, $space->get_processor_id());

        // Alter the spaceid and topic.
        $space->update_room_record(
            roomid: 'updatedSpaceId',
            topic: 'updatedTopic is here',
        );

        $this->assertEquals('updatedSpaceId', $space->get_room_id());
        $this->assertEquals('updatedTopic is here', $space->get_topic());
        $this->assertEquals(12345, $space->get_processor_id());
    }

    /**
     * Tests for delete_room_record.
     *
     * @covers ::delete_room_record
     */
    public function test_delete_room_record(): void {
        global $DB;

        $this->resetAfterTest();

        $space = matrix_space::create_room_record(
            processorid: 12345,
            topic: 'The topic of this space is that',
        );
        $this->assertCount(1, $DB->get_records('matrix_space'));

        $space->delete_room_record();
        $this->assertCount(0, $DB->get_records('matrix_space'));
    }
}
