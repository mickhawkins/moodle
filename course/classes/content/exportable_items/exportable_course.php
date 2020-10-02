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
 * The definition of an item which can be exported.
 *
 * @package     core
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace core_course\content\exportable_items;

use context;
use core\content\exportable_item;
use core\content\controllers\export\controller;
use section_info;
use stdClass;
use stored_file;

/**
 * An object used to represent content which can be served.
 *
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exportable_course extends exportable_item {

    /** @var stdClass The course to be exported */
    protected $course;

    /**
     * Create a new exportable_item instance.
     *
     * If no filearea or itemid  is specified the no attempt will be made to export files.
     *
     * @param   context $context The context that this content belongs to
     * @param   string $component
     * @param   string $uservisiblename The name displayed to the user when filtering
     * @param   stdClass $course
     */
    public function __construct(
        context $context,
        string $component,
        string $uservisiblename,
        stdClass $course
    ) {
        parent::__construct($context, $component, $uservisiblename);

        $this->course = $course;
    }

    /**
     * Add the content to the archive.
     *
     * @param   controller $controller The export controller associated with this export
     */
    public function add_to_archive(controller $controller): void {
        global $PAGE;

        // A course export is composed of:
        // - Course summary (including inline files)
        // - Overview files
        // - Section:
        // -- Section name
        // -- Section summary (including inline files)
        // -- List of available activities.

        $templatedata = (object) [
            'course' => $this->course,
            'overviewfiles' => [],
            'sections' => '',
        ];

        if (empty($this->course->summary)) {
            $this->course->summary = '';
        }

        // Add the course summary.
        $templatedata->summary = $this->export_files($controller, 'summary', 0, $this->course->summary, '_course', null);

        // Add the overview files.
        $templatedata->overviewfiles = $this->export_overview_files($controller);

        // Add all sections.
        $modinfo = get_fast_modinfo($this->course);
        foreach ($modinfo->get_section_info_all() as $number => $section) {
            $templatedata->sections = $this->export_section($controller, $section);
        }

        $courserenderer = $PAGE->get_renderer('core', 'course');
        $content = $courserenderer->render_from_template('core_course/content/courseexport', $templatedata);

        $controller->get_archive()->add_file_from_html_string(
            $this->get_context(),
            'index.html',
            $content
        );
    }

    /**
     * Export files releating to this text area.
     *
     * @param   string $content
     * @return  string
     */
    protected function export_files(controller $controller, string $filearea, int $itemid, string $content, string $subdir, ?int $pluginfileitemid): string {
        // Export all of the files for this filearea.
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, $this->component, $filearea, $itemid);

        $filelist = [];
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }

            $filepathinzip = $this->get_filepath_for_file($file, $subdir, false);
            $controller->get_archive()->add_file_from_stored_file(
                $this->get_context(),
                $filepathinzip,
                $file
            );

            if ($controller->get_archive()->is_file_in_archive($this->get_context(), $filepathinzip)) {
                // Attempt to rewrite any @@PLUGINFILE@@ URLs for this file in the content.
                $searchpath = "@@PLUGINFILE@@" . $file->get_filepath() . rawurlencode($file->get_filename());
                $content = str_replace($searchpath, $this->get_filepath_for_file($file, $subdir, true), $content);
            }
        }

        return $this->rewrite_other_pluginfile_urls($content, $filearea, $pluginfileitemid);
    }

    /**
     * Export all course overview files for the course.
     *
     * @param   controller $controller
     * @return  string
     */
    protected function export_overview_files(controller $controller): string {
        global $PAGE;
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, $this->component, 'overviewfiles', 0);

        $exportedfiles = [];
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $exportedfiles[] = (object) [
                'filename' => $file->get_filename(),
                'filepath' => "@@PLUGINFILE@@" . $file->get_filepath() . rawurlencode($file->get_filename()),
            ];
        }

        if (empty($exportedfiles)) {
            return '';
        }

        $courserenderer = $PAGE->get_renderer('core', 'course');
        $content = $courserenderer->render_from_template(
            'core_course/content/overviewfilelist',
            (object) [
                'files' => $exportedfiles,
            ]);

        return $this->export_files($controller, 'overviewfiles', 0, $content, '_course', null);
    }

    protected function export_section(controller $controller, section_info $section): string {
        error_log($section->summary);
        return $this->export_files(
            $controller,
            'section',
            (int) $section->id,
            $section->summary,
            "_course/sections/{$section->id}",
            (int) $section->id
        );
    }

    /**
     * Rewrite any pluginfile URLs in the content.
     *
     * @param   string $content
     * @param   string $filearea
     * @param   null|int $pluginfileitemid
     * @return  string
     */
    protected function rewrite_other_pluginfile_urls(string $content, string $filearea, ?int $pluginfileitemid): string {
        // The pluginfile URLs should have been rewritten when the files were exported, but if any file was too large it
        // may not have been included.
        // In that situation use a tokenpluginfile URL.

        if (strpos($content, '@@PLUGINFILE@@/') !== false) {
            // Some files could not be rewritten.
            // Use a tokenurl pluginfile for those.
            $content = file_rewrite_pluginfile_urls(
                $content,
                'pluginfile.php',
                $this->context->id,
                $this->component,
                $filearea,
                $pluginfileitemid,
                [
                    'includetoken' => true,
                ]
            );
        }

        return $content;
    }

    /**
     * Get the filepath for the specified stored_file.
     *
     * @param   stored_file $file
     * @param   bool $escape
     * @return  string
     */
    protected function get_filepath_for_file(stored_file $file, string $subdir, bool $escape): string {
        $path = [];

        if (!empty($subdir)) {
            $subdir = '/' . $subdir . '/';
        }

        $filepath = sprintf(
            '%s%s%s%s',
            $subdir,
            $file->get_filearea(),
            $file->get_filepath(),
            $file->get_filename()
        );

        if ($escape) {
            foreach (explode('/', $filepath) as $dirname) {
                $path[] = rawurlencode($dirname);
            }
            $filepath = implode('/', $path);
        }

        return ltrim(preg_replace('#/+#', '/', $filepath), '/');
    }

    /**
     * Serialize the exportable item.
     *
     * @return  array
     */
    public function __serialize(): array {
        return array_merge(
            [
                'courseid' => $this->course->id,
            ],
            parent::__serialize()
        );
    }

    /**
     * Unserialize the exportable item.
     *
     * @param   array $data
     */
    public function __unserialize(array $data): void {
        $this->course = get_course($data['courseid']);

        parent::__unserialize($data);
    }
}
