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

namespace communication_matrix\local\spec\features\matrix;

use communication_matrix\local\command;
use communication_matrix\matrix_user_manager;
use GuzzleHttp\Psr7\Response;

/**
 * Matrix API feature to update a room space.
 *
 * Matrix rooms have a concept of spaces, where rooms can be added to a space to group them together.
 *
 * https://spec.matrix.org/v1.7/client-server-api/#spaces
 *
 * @package    communication_matrix
 * @copyright  2023 Safat Shahin <safat.shahin@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @codeCoverageIgnore
 * This code does not warrant being tested. Testing offers no discernible benefit given its usage is tested.
 */
trait update_room_parent_space_v3 {

    /**
     * Set the parent space for a room.
     *
     * @param string $roomid The matrix room id
     * @param string $parentid The matrix space id
     * @return Response
     */
    public function update_room_parent_space(string $roomid, string $parentid): Response {
        $params = [
            ':roomid' => $roomid,
            ':statekey' => $parentid,
            'via' => [
                matrix_user_manager::get_formatted_matrix_home_server(),
            ],
        ];

        return $this->execute(new command(
            $this,
            method: 'PUT',
            endpoint: '_matrix/client/v3/rooms/:roomid/state/m.space.child/:statekey',
            params: $params,
        ));
    }
}
