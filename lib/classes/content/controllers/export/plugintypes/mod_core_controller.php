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
 * Activity module export controller for the content API.
 *
 * @package     core
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core\content\controllers\export\plugintypes;

use cm_info;
use context;
use core\content\controllers\export\plugintype_controller;
use core\content\exportable_items\exportable_textarea;
use core\content\export\helper;
use core\content\export\exported_item;
use core\content\servable_item;
use stdClass;

/**
 * The definition for a single pluginfile file area within a component.
 *
 * This class is responsible for returning information about a file area used in Moodle, to support translation of a
 * pluginfile URL into an item of servable content, and determining whether a user can access that file.
 *
 * @copyright   2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_core_controller extends plugintype_controller {

    /**
     * Get the exportable items for the user in the specified context.
     *
     * Note: This context must be a child of the root context defined in the instance.
     *
     * @param   context $currentcontext The current context being exported
     * @return  exportable_item[]
     */
    public function get_exportable_items_for_user(context $currentcontext): array {
        $cm = self::get_cm_from_context($currentcontext);
        $contentitems = [];

        if (plugin_supports('mod', $cm->modname, FEATURE_MOD_INTRO, true)) {
            $contentitems[] = new exportable_textarea(
                $currentcontext,
                $this->component,
                get_string('moduleintro', 'core'),
                'index.html',
                $this->get_modname($currentcontext),
                'intro',
                $cm->instance,
                'introformat',
                'intro',
                0,
                null
            );
        }

        return $contentitems;
    }

    public function export_exportables(context $context, array $exportables) {
        global $PAGE;

        $cm = self::get_cm_from_context($context);

        $templatedata = (object) [
            'activity' => (object) [
                'link' => $cm->url,
                'name' => $cm->get_formatted_name(),
            ],
            'intro' => null,
            'sections' => [],
        ];

        if (plugin_supports('mod', $cm->modname, FEATURE_MOD_INTRO, true)) {
            $templatedata->intro = $this->get_rewritten_intro($context, $cm)->get_content();
        }

        $exporteditems = [];
        foreach ($exportables as $exportable) {
            $exporteditems[] = $exportable->add_to_archive($this->get_archive());
        }

        foreach ($exporteditems as $item) {
            $templatedata->sections[] = $item->get_template_data();
        }

        // Add the index to the archive.
        $this->archive->add_file_from_template(
            $context,
            'index.html',
            'core/content/export/module_index',
            $templatedata
        );
    }

    protected function get_rewritten_intro(context $context, cm_info $cm): exported_item {
        global $DB;

        $record = $DB->get_record($cm->modname, ['id' => $cm->instance], 'intro');

        return helper::export_files_for_content(
            $this->get_archive(),
            $context,
            '',
            $record->intro,
            "mod_{$cm->modname}",
            'intro',
            0,
            null
        );
    }
}
