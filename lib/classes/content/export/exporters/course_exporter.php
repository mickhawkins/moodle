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
 * The course exporter.
 *
 * @package     core
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core\content\export\exporters;

use context;
use context_course;
use core\content\export\exported_item;
use core\content\export\zipwriter;
use section_info;
use stdClass;

/**
 * The course exporter.
 *
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_exporter extends component_exporter {
    /** @var context The course context */
    protected $context;

    /** @var zipwriter */
    protected $archive;

    /**
     * Constructor for the course exporter.
     *
     * @param   context_course $context The context of the course to export
     * @param   stdClass $user
     * @param   zipwriter $archive
     */
    public function __construct(context_course $context, stdClass $user, zipwriter $archive) {
        $this->course = get_course($context->instanceid);
        parent::__construct($context, 'core_course', $user, $archive);
    }

    /**
     * Get the exportable items for the user in the specified context.
     *
     * Note: This context must be a child of the root context defined in the instance.
     *
     * @return  exportable_item[]
     */
    public function get_exportables(): array {
        return [];
    }

    /**
     * Export the course.
     *
     * @param   context[] $exportedcontexts A list of contexts which were successfully exported
     */
    public function export_course(array $exportedcontexts): void {
        global $PAGE;

        // A course export is composed of:
        // - Course summary (including inline files)
        // - Overview files
        // - Section:
        // -- Section name
        // -- Section summary (including inline files)
        // -- List of available activities.

        if (empty($this->course->summary)) {
            $this->course->summary = '';
        }

        $templatedata = (object) [
            'course' => $this->course,
            'summary' => $this->get_course_summary()->get_content(),
            'overviewfiles' => $this->get_course_overview_files()->get_template_data()->files,
            'sections' => [],
        ];

        // Add all sections.
        $modinfo = get_fast_modinfo($this->course);
        foreach ($modinfo->get_section_info_all() as $number => $section) {
            $templatedata->sections[] = $this->get_course_section($exportedcontexts, $section);
        }

        $this->archive->add_file_from_template(
            $this->context,
            'index.html',
            'core/content/export/course_index',
            $templatedata
        );
    }

    /**
     * Export files associated with the course summary and fetch the course summary text.
     *
     * @return  exported_item
     */
    protected function get_course_summary(): exported_item {
        global $DB;

        return $this->archive->add_pluginfiles_for_content(
            $this->context,
            '_course',
            $this->course->summary,
            'course',
            'summary',
            0,
            null
        );
    }

    /**
     * Export files in the course overview.
     *
     * @return  exported_item
     */
    protected function get_course_overview_files(): exported_item {
        return $this->archive->add_pluginfiles_for_content(
            $this->context,
            '',
            '',
            'course',
            'overviewfiles',
            0,
            null
        );
    }

    /**
     * Fetch data for the specified course section.
     *
     * @param   context[] $exportedcontexts A list of contexts which were successfully exported
     * @param   section_info $section The section being exported
     * @return  stdClass
     */
    protected function get_course_section(array $exportedcontexts, section_info $section): stdClass {
        $sectiondata = (object) [
            'number' => $section->section,
            'title' => $section->name,
            'summary' => '',
            'activities' => [],
        ];

        $sectiondata->summary = $this->archive->add_pluginfiles_for_content(
            $this->context,
            "_course",
            $section->summary,
            'course',
            'section',
            $section->id,
            $section->id
        )->get_template_data()->content;

        $modinfo = get_fast_modinfo($this->course);
        if (empty($modinfo->sections[$section->section])) {
            return $sectiondata;
        }

        foreach ($modinfo->sections[$section->section] as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if (!$cm->uservisible) {
                continue;
            }

            if (array_key_exists($cm->context->id, $exportedcontexts)) {
                // This activity was exported.
                // The link to it from the course index should be a relative link.
                $url = $this->archive->get_relative_context_path($this->context, $cm->context, 'index.html');
            } else {
                // This activity was not included in the export for some reason.
                // Link to the live activity.
                $url = $cm->url;
            }
            $sectiondata->activities[] = (object) [
                'title' => $cm->name,
                'modname' => $cm->modfullname,
                'link' => $url,
            ];
        }

        return $sectiondata;
    }
}
