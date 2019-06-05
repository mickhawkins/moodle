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
 * @package report_security
 * @copyright 2019 Michael Hawkins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/templates', 'core/notification'], function($, ajax, templates, notification) {

    return {
        init: function() {
            var info = [];
            //info = [{'sectionid': 1}]; //TODO: Have this setting for all of the sections to be called. See push in relateCompetenciesHandler in /admin/tool/lp/amd/src/competencyactions.js

            info = {'sectionid': 2};

            var promises = ajax.call([{
                methodname: 'report_security_prepare_report_section',
                args: {section: info}
            }]);

            promises[0].done(function (data) {
                templates.render('report_security/security_report_section_results', data).done(function (html, js) {

                    var section_id = 2; //TODO - have this passed in

                    $('#security_report_' + section_id).replaceWith(html);
                    templates.runTemplateJS(js);

                    return true;

                }).fail(notification.exception);
            }).fail(notification.exception);

            return false;
        }
    };
});
