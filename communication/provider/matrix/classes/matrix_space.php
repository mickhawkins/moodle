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
 * Class matrix_space to manage the updates to the space information in db.
 *
 * @package    communication_matrix
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class matrix_space extends matrix_room_base {

    public static function load_by_processor_id(
        int $processorid,
    ): ?self {
        global $DB;
        $record = $DB->get_record(matrix_constants::TABLE_MATRIX_SPACE, ['commid' => $processorid]);

        if (!$record) {
            return null;
        }
        return new self($record);
    }

    protected function __construct(
        protected stdClass $record,
    ) {
    }

    public static function create_room_record(
        int $processorid,
        ?string $topic,
        ?string $roomid = null,
    ): self {
        global $DB;

        $roomrecord = (object) [
            'commid' => $processorid,
            'roomid' => $roomid,
            'topic' => $topic,
        ];
        $roomrecord->id = $DB->insert_record(matrix_constants::TABLE_MATRIX_SPACE, $roomrecord);

        return self::load_by_processor_id($processorid);
    }

    public function update_room_record(
        ?string $roomid = null,
        ?string $topic = null,
    ): void {
        global $DB;

        if ($roomid !== null) {
            $this->record->roomid = $roomid;
        }

        if ($topic !== null) {
            $this->record->topic = $topic;
        }

        $DB->update_record(matrix_constants::TABLE_MATRIX_SPACE, $this->record);
    }

    public function delete_room_record(): void {
        global $DB;
        $DB->delete_records(matrix_constants::TABLE_MATRIX_SPACE, ['commid' => $this->record->commid]);

        unset($this->record);
    }

    public function get_id(): int {
        return $this->record->id;
    }

    public function get_processor_id(): int {
        return $this->record->commid;
    }

    public function get_room_id(): ?string {
        return $this->record->roomid;
    }

    public function get_topic(): string {
        return $this->record->topic ?? '';
    }

    public function get_creation_content(): array {
        return [
            'type' => 'm.space',
        ];
    }
}
