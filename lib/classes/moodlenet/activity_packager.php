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

namespace core\moodlenet;

use backup;
use backup_controller;
use backup_root_task;
use cm_info;

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

/**
 * Packager to prepare appropriate backup of an activity to share to MoodleNet.
 *
 * @copyright 2023 Raquel Ortega <raquel.ortega@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_packager {

    /** @var cm_info $cminfo */
    protected $cminfo;

    /** @var activity_resource $resourceinfo */
    protected $resourceinfo;

    /** @var backup_controller $controller */
    protected $controller;

    /** @var array $overriddensettings */
    protected $overriddensettings;

    /**
     * Constructor
     *
     * @param activity_resource $resourceinfo Information about the resource being packaged.
     */
    public function __construct(activity_resource $resourceinfo) {
        global $USER;

        $cminfo = $resourceinfo->get_cm();
        $this->resourceinfo = $resourceinfo;

        // Check backup/restore support.
        if (!plugin_supports('mod', $cminfo->modname , FEATURE_BACKUP_MOODLE2)) {
            throw new \coding_exception("Cannot backup module $cminfo->modname. This module doesn't support the backup feature.");
        }

        $this->cminfo = $cminfo;
        $this->overriddensettings = [];

        $this->controller = new backup_controller (
            backup::TYPE_1ACTIVITY,
            $cminfo->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id
        );
    }

    /**
     * Prepare the backup file and return relevant information.
     *
     * @return array Array of relevant backup file information.
     */
    public function get_package(): array {

        $alltasksettings = $this->get_all_task_settings();

        // Override relevant settings to remove user data when packaging to share to MoodleNet.
        $this->override_task_setting($alltasksettings, 'setting_root_anonymize', 1);
        $this->override_task_setting($alltasksettings, 'setting_root_users', 0);
        $this->override_task_setting($alltasksettings, 'setting_root_role_assignments', 0);
        $this->override_task_setting($alltasksettings, 'setting_root_blocks', 0);
        $this->override_task_setting($alltasksettings, 'setting_root_comments', 0);
        $this->override_task_setting($alltasksettings, 'setting_root_badges', 0);
        $this->override_task_setting($alltasksettings, 'setting_root_userscompletion', 0);
        $this->override_task_setting($alltasksettings, 'setting_root_logs', 0);
        $this->override_task_setting($alltasksettings, 'setting_root_grade_histories', 0);
        $this->override_task_setting($alltasksettings, 'setting_root_groups', 0);

        return $this->package();
    }

    /**
     * Get all settings available for override.
     *
     * @return array the associative array of taskclass => settings instances
     */
    protected function get_all_task_settings(): array {
        $tasksettings = [];
        foreach ($this->controller->get_plan()->get_tasks() as $task) {
            $taskclass = get_class($task);
            $tasksettings[$taskclass] = $task->get_settings();
        }
        return $tasksettings;
    }

    /**
     * Overrides the given task setting with the given value
     *
     * @param array $alltasksettings All tasks settings
     * @param string $taskclassname Use the task class name to override the settting
     * @param int $settingvalue
     * @return void
     */
    protected function override_task_setting (array $alltasksettings, string $settingname, bool $settingvalue): void {
        if (empty($rootsettings = $alltasksettings[backup_root_task::class])) {
            return;
        }

        foreach ($rootsettings as $setting) {
            $name = $setting->get_ui_name();
            if ($name == $settingname && $settingvalue != $setting->get_value()) {
                $setting->set_value($settingvalue);
                $this->overriddensettings[$settingname] = $settingvalue;
                return;
            }
        }
    }

    /**
     * Package the activity identified by CMID.
     *
     * Custom plan settings, where overrides the settings from a backup plan, by specifying them in the array.
     * Any setting dependent on a setting disabled this way will also be locked by reason of hierarchy,
     * as would be the case in regular interactive backups.
     *
     * @return null|array the activity and file record information. E.g. [activity, filerecord]
     */
    protected function package(): ?array {

        // Executes the backup
        $this->controller->execute_plan();

        // Grab the result.
        $result = $this->controller->get_results();
        if (!isset($result['backup_destination'])) {
            throw new \moodle_exception('Failed to package activity.');
        }

        // Controller is not used anymore, freeing resources
        $this->controller->destroy();

        // Grab the filename.
        $file = $result['backup_destination'];
        if (!$file->get_contenthash()) {
            throw new \moodle_exception('Failed to package activity (invalid file).');
        }

        // Create the location we want to copy this file to.
        $fr = array(
            'contextid' => \context_course::instance($this->cminfo->course)->id,
            'component' => 'core',
            'filearea' => 'moodlenet_activity',
            'itemid' => $this->cminfo->id,
            'timemodified' => time()
        );

        // Prepare the file array
        $fs = get_file_storage();

        // The script should generate a new backup file each time it is run.
        $fs->delete_area_files($fr['contextid'], $fr['component'], $fr['filearea'], $fr['itemid']);

        if (!$fs->create_file_from_storedfile($fr, $file)) {
            throw new \moodle_exception("Failed to copy backup file to moodlenet_activity area.");
        }

        // Delete the old file.
        $file->delete();

        $areafiles = $fs->get_area_files($fr['contextid'], $fr['component'], $fr['filearea'], $fr['itemid']);
        foreach ($areafiles as $file) {
            if (!$file->is_directory()) {
                $fr['file'] = $file;
                $fileurl = \moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    $file->get_itemid(),
                    $file->get_filepath(),
                    $file->get_filename(),
                );
                $fr['fileurl'] = $fileurl;
            }
        }

        return $fr;
    }
}
