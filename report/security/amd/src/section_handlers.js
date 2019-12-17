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
 * Module responsible for fetching the security report sections.
 *
 * @package report_security
 * @copyright 2019 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';

export const fetchsections = () => {
    var info = [];
    //TODO: Have this setting for all of the sections to be called.
    //See push in relateCompetenciesHandler in /admin/tool/lp/amd/src/competencyactions.js
    //info = [{'sectionid': 1}];

    info = {'sectionid': 2};

    const request = {
        methodname: 'report_security_prepare_report_section',
        args: {section: info}
    };

    Ajax.call([request])[0].done(function(data) {
        Templates.render('report_security/security_report_section_results', data).done(function (html, js) {

            document.getElementById(`security_report_${data.sectionid}`).outerHTML = html;
            Templates.runTemplateJS(js);

        }).fail(Notification.exception);
    }).fail(Notification.exception);
};

export const tableevents = (sectionid) => {
    document.getElementById(`togglepassedsection${sectionid}`).onclick = () => {
        document.getElementById(`togglepassedsection${sectionid}showtext`).classList.toggle('hidden');
        document.getElementById(`togglepassedsection${sectionid}hidetext`).classList.toggle('hidden');
    };
};