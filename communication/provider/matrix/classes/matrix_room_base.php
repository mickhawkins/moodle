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

use stdClass;

/**
 * Class matrix_room_base is the base class for matrix_room and matrix_space.
 *
 * @package    communication_matrix
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class matrix_room_base {

    /** @var stdClass|null $record The matrix room record from db */

    /**
     * Load the matrix room record for the supplied processor.
     *
     * @param int $processorid The id of the communication record
     * @return null|self
     */
    abstract public static function load_by_processor_id(int $processorid): ?self;

    /**
     * Create matrix room data.
     *
     * @param int $processorid The id of the communication record
     * @param string|null $topic The topic of the room for matrix
     * @param string|null $roomid The id of the room from matrix
     * @return self
     */
    abstract public static function create_room_record(
        int $processorid,
        ?string $topic,
        ?string $roomid = null,
    ): self;

    /**
     * Update matrix room data.
     *
     * @param string|null $roomid The id of the room from matrix
     * @param string|null $topic The topic of the room for matrix
     */
    abstract public function update_room_record(
        ?string $roomid = null,
        ?string $topic = null,
    ): void;

    /**
     * Delete matrix room data.
     */
    abstract public function delete_room_record(): void;


    /**
     * Get the id of the matrix room record.
     *
     * @return int The id of the matrix room record
     */
    abstract public function get_id(): int;

    /**
     * Get the processor id.
     * It's the communication record id from table 'communication'.
     *
     * @return int
     */
    abstract public function get_processor_id(): int;

    /**
     * Get the matrix room id.
     *
     * @return string|null
     */
    abstract public function get_room_id(): ?string;

    /**
     * Get the matrix room topic.
     *
     * @return string
     */
    abstract public function get_topic(): string;

    /**
     * Get the matrix room creation content.
     * Extra keys, such as m.federate, to be added to the content of the m.room.create event.
     * The server will overwrite the following keys: creator, room_version.
     *
     * For more information, see https://spec.matrix.org/v1.7/client-server-api/#post_matrixclientv3createroom.
     * In the Request section, see the creation_content and its associated information.
     *
     * @return array
     */
    abstract public function get_creation_content(): array;
}
