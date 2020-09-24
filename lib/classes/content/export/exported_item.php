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
 * Exported Item.
 *
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace core\content\export;

use stdClass;

/**
 * This class describes a set of files which were exported, and a container for any text content that those files were
 * contained in.
 *
 * @package     core;
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exported_item {

    /** @var string A short, descriptive, name for this exported item */
    protected $title = null;

    /** @var string Any string content for export  */
    protected $content = '';

    /** @var string[] A list of files which were exported and are not present in the content */
    protected $files = [];

    /** @var string[] A list of files which were exported and are present in the content */
    protected $contentfiles = [];

    /**
     * Constructor for the exported_item.
     *
     * @param   array $files A list of all files which were exported
     * @param   string $content Rewritten content that the files relate to
     * @param   array $contentfiles Files which were included in the content
     */
    public function __construct(array $files = []) {
        $this->add_files($files);
    }

    /**
     * Set a title for this exported item.
     *
     * @param   string $title;
     */
    public function set_title(string $title): void {
        $this->title = $title;
    }

    /**
     * Add a file to the list of exported files.
     *
     * @param   string $relativefilepath The path to the content relative to the exported context
     * @param   bool $incontent Whether this file is included within the content
     */
    public function add_file(string $relativefilepath, bool $incontent = false, ?string $filepath = null): void {
        if ($filepath === null) {
            $filepath = $relativefilepath;
        }

        $file = (object) [
            'filepath' => $filepath,
            'filename' => basename($relativefilepath),
        ];

        $this->files[$relativefilepath] = $file;
        if ($incontent) {
            $this->contentfiles[$relativefilepath] = $file;
        }
    }

    /**
     * Add a list of files to the list of exported files.
     *
     * @param   string[] $files The path to the content relative to the exported context
     * @param   bool $incontent Whether this file is included within the content
     */
    public function add_files(array $files, bool $incontent = false): void {
        foreach ($files as $relativefilepath) {
            $this->add_file($relativefilepath, $incontent);
        }
    }

    /**
     * Set the rewritten content.
     *
     * @param   string $content
     */
    public function set_content(string $content): void {
        $this->content = $content;
    }

    /**
     * Fetch the rewritten content.
     *
     * @return  string
     */
    public function get_content(): string {
        return $this->content;
    }

    /**
     * Get a short, descriptive name associated with the exported content, if one is avaiable.
     *
     * @return  null|string
     */
    public function get_title(): ?string {
        return $this->title;
    }

    /**
     */
    public function get_template_data(): stdClass {
        return (object) [
            'title' => $this->get_title(),
            'files' => $this->get_noncontent_files(),
            'content' => $this->content,
        ];
    }

    public function get_all_files(): array {
        return $this->files;
    }

    public function get_content_files(): array {
        return $this->contentfiles;
    }

    public function get_noncontent_files(): array {
        return array_values(array_diff_key(
            $this->get_all_files(),
            $this->get_content_files()
        ));
    }
}
