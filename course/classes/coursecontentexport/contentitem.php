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
 * Content item.
 *
 * @package core_course\coursecontentexport
 * @copyright 2020 Simey Lameze <simey@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_course\coursecontentexport;

use context;

/**
 * Represents an item of content which may be exported.
 *
 * @package core_course\coursecontentexport
 */
class contentitem {

    /** @var context The context the content item is in */
    protected $context;

    /** @var string $component The content item component */
    protected $component;

    /** @var string $title The content item title */
    protected $title;

    /** @var string $type The type of content being exported */
    protected $type;

    /** @var int $id The id of the content item */
    protected $id;

    /**
     * The contentitem constructor.
     *
     * @param context $context The context that the item is in, usually a context_module
     * @param string $component Frankenstyle component such as 'core', 'core_analytics' or 'mod_workshop'
     * @param string $title Just a title to be shown to the user such as 'Lecture notes'
     * @param string $type The type of content being exported such as 'file', 'folder', 'discussion'...
     * @param int $id The ID of the file in the API
     */
    public function __construct(context $context, string $component, string $title, string $type, int $id) {
        $this->context = $context;
        $this->component = $component;
        $this->title = $title;
        $this->type = $type;
        $this->id = $id;
    }

    /**
     * Get the content item context.
     *
     * @return context
     */
    public function get_context(): context {
        return $this->context;
    }

    /**
     * Get the content item component.
     *
     * @return string
     */
    public function get_component(): string {
        return $this->component;
    }

    /**
     * Get the content item title.
     *
     * @return string
     */
    public function get_title(): string {
        return $this->title;
    }

    /**
     * Get the content item type.
     *
     * @return string
     */
    public function get_type(): string {
        return $this->type;
    }

    /**
     * Get the content item id.
     *
     * @return int
     */
    public function get_id(): int {
        return $this->id;
    }
}
