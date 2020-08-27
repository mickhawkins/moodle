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
 * Exported content item.
 *
 * @package core_course\coursecontentexport
 * @copyright 2020 Simey Lameze <simey@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_course\coursecontentexport;

use context;

/**
 * Class representing a content item which has been exported in a zip file.
 *
 * @package core_course\coursecontentexport
 * @copyright  2020 Simey Lameze <simey@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exported_contentitem extends contentitem {

    /** @var string $pathinzip */
    protected $pathinzip;

    /**
     * The exported_contentitem class constructor.
     *
     * @param \context $context
     * @param string $component
     * @param string $title
     * @param string $type
     * @param int $id
     * @param string $pathinzip
     */
    public function __construct(context $context, string $component, string $title, string $type, int $id, string $pathinzip) {
        $this->pathinzip = $pathinzip;

        parent::__construct($context, $component, $title, $type, $id);
    }

    /**
     * Get the path in the zip.
     *
     * @return string
     */
    public function get_path_in_zip(): string {
        return $this->pathinzip;
    }

    /**
     * Create.
     *
     * @param contentitem $contentitem
     * @param string $pathinzip
     * @return static
     */
    public static function create(contentitem $contentitem, string $pathinzip): self {
        return new self(
            $contentitem->get_context(),
            $contentitem->get_component(),
            $contentitem->get_title(),
            $contentitem->get_type(),
            $contentitem->get_id(),
            $pathinzip
        );
    }
}
