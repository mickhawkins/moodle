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
 * Functions related to downloading course content.
 *
 * @module     core_course/downloadcontent
 * @package    core_course
 * @copyright  2020 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as ModalFactory from 'core/modal_factory';
import * as ModalEvents from 'core/modal_events';
import $ from 'jquery';

/**
 * Prepare the content and behaviour of the course download content modal.
 *
 * @return {void}
 */
export const prepareDownloadContentModal = () => {
    const downloadTrigger = $('[data-downloadcourse=1]');
    const downloadLink = downloadTrigger.attr('data-download-link');
    const downloadModalTitle = downloadTrigger.attr('data-download-title');
    const downloadModalBody = '<p>' + downloadTrigger.attr('data-download-body') + '</p>';
    const downloadModalSubmitText = downloadTrigger.attr('data-download-button-text');

    ModalFactory.create({
        title: downloadModalTitle,
        type: ModalFactory.types.SAVE_CANCEL,
        body: downloadModalBody
    }, downloadTrigger)
    .done(function(modal) {
        modal.setSaveButtonText(downloadModalSubmitText);

        // Trigger the download when the "Download" button is pressed.
        modal.getRoot().on(ModalEvents.save, function() {
            // Create a form to submit the file download request, so we can avoid sending sesskey over GET.
            var downloadForm = document.createElement('form');
            downloadForm.action = downloadLink;
            downloadForm.method = 'POST';
            // Open download in a new tab, so current course view is not disrupted.
            downloadForm.target = '_blank';
            var downloadSesskey = document.createElement('input');
            downloadSesskey.name = 'sesskey';
            downloadSesskey.value = M.cfg.sesskey;
            downloadForm.appendChild(downloadSesskey);
            downloadForm.style.display = 'none';

            document.body.appendChild(downloadForm);
            downloadForm.submit();
            document.body.removeChild(downloadForm);
        });
    });
};
