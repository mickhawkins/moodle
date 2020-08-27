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

/**
 * Requested content item.
 *
 * @package core_course\coursecontentexport
 * @copyright 2020 Simey Lameze <simey@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_course\coursecontentexport;

use context;

/**
 * Class representing a content item which has been selected for download.
 *
 * @package core_course\coursecontentexport
 * @copyright  2020 Simey Lameze <simey@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class requested_contentitem extends contentitem {

    /** @var bool $isrequested */
    protected $requested;

    /**
     * requested_contentitem constructor.
     *
     * @param context $context
     * @param string $component
     * @param string $title
     * @param string $type
     * @param int $id
     * @param bool $requested
     */
    public function __construct(context $context, string $component, string $title, string $type, int $id, bool $requested = true) {
        $this->requested = $requested;

        parent::__construct($context, $component, $title, $type, $id);
    }

    /**
     * Has it been requested?
     * @return bool
     */
    public function is_requested(): bool {
        return $this->requested;
    }

    /**
     * Approve.
     *
     * @param contentitem $contentitem
     * @return static
     */
    public static function approve(contentitem $contentitem): self {
        return new self(
            $contentitem->get_context(),
            $contentitem->get_component(),
            $contentitem->get_title(),
            $contentitem->get_type(),
            $contentitem->get_id(),
            true
        );
    }

    /**
     * Reject.
     *
     * @param contentitem $contentitem
     * @return static
     */
    public static function reject(contentitem $contentitem): self {
        return new self(
            $contentitem->get_context(),
            $contentitem->get_component(),
            $contentitem->get_title(),
            $contentitem->get_type(),
            $contentitem->get_id(),
            false
        );
    }
}
