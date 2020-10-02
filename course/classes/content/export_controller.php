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
 * Content export definition.
 *
 * @package     core
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_course\content;

use context;
use context_course;
use core_course\content\exportable_items\exportable_course;
use core\content\controllers\export\component_controller;
use core\content\zipwriter;
use core\content\export\helper;
use core\content\export\exported_item;
use section_info;
use stdClass;

/**
 * A class which assists a component to export content.
 *
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_controller {
    /** @var context The course context */
    protected $context;

    /** @var zipwriter */
    protected $archive;

    public function __construct(context_course $context, zipwriter $archive) {
        $this->context = $context;
        $this->course = get_course($context->instanceid);
        $this->archive = $archive;
    }

    public function export_items_for_user(array $exportedcontexts): void {
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
            'core_course/content/courseexport',
            $templatedata
        );
    }

    protected function get_course_summary(): exported_item {
        global $DB;

        return helper::add_text_with_pluginfiles_to_archive(
            $this->archive,
            $this->context,
            '',
            $this->course->summary,
            'course',
            'summary',
            0,
            null
        );
    }

    protected function get_course_overview_files(): exported_item {
        return helper::export_files_for_content(
            $this->archive,
            $this->context,
            '',
            '',
            'course',
            'overviewfiles',
            0,
            null
        );
    }

    protected function get_course_section(array $exportedcontexts, section_info $section): stdClass {
        $sectiondata = (object) [
            'number' => $section->section,
            'title' => $section->name,
            'summary' => '',
            'activities' => [],
        ];

        $sectiondata->summary = helper::add_text_with_pluginfiles_to_archive(
            $this->archive,
            $this->context,
            "sections/{$section->section}",
            $section->summary,
            'course',
            'section',
            $section->id,
            $section->id
        )->get_template_data()->content;

        $modinfo = get_fast_modinfo($this->course);
        foreach ($modinfo->sections[$section->section] as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if (!$cm->uservisible) {
                continue;
            }

            if (array_key_exists($cm->context->path, $exportedcontexts)) {
                $url = $this->archive->get_relative_context_path($this->context, $cm->context, 'index.html');
            } else {
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

    protected function get_archive(): zipwriter {
        return $this->archive;
    }
}
