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
 * Zip writer wrapper.
 *
 * @package core_course\coursecontentexport
 * @copyright 2020 Simey Lameze <simey@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core\content;

use context;
use context_system;
use stdClass;
use stored_file;

/**
 * Zip writer wrapper.
 *
 * @package core_course\coursecontentexport
 * @copyright 2020 Simey Lameze <simey@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class zipwriter {

    /** @const int Maximum folder length name for a context */
    const MAX_CONTEXT_NAME_LENGTH = 32;

    /** @var \ZipStream\ZipStream */
    protected $archive;

    /** @var int Max file size of an individual file in the archive */
    protected $maxfilesize = 1 * 1024 * 1024 * 10;

    /** @var resource File resource for the file handle for a file-based zip stream */
    protected $zipfilehandle = null;

    /** @var string File path for a file-based zip stream */
    protected $zipfilepath = null;

    /** @var context The context to use as a base for export */
    protected $rootcontext = null;

    /** @var array The files in the zip */
    protected $filesinzip = [];

    /** @var bool Whether page requirements needed for HTML pages have been added */
    protected $pagerequirementsadded = false;

    /**
     * zipwriter constructor.
     *
     * @param \ZipStream\ZipStream $archive
     * @param stdClass|null $options
     */
    public function __construct(\ZipStream\ZipStream $archive, stdClass $options = null) {
        $this->archive = $archive;
        if ($options) {
            $this->parse_options($options);
        }

        $this->rootcontext = context_system::instance();
    }

    /**
     * Set a root context for use during the export.
     *
     * This is primarily used for creating paths within the archive relative to the root context.
     *
     * @param   context $rootcontext
     */
    public function set_root_context(context $rootcontext): void {
        $this->rootcontext = $rootcontext;
    }

    /**
     * Parse options.
     *
     * @param stdClass $options
     */
    protected function parse_options(stdClass $options): void {
        if (property_exists($options, 'maxfilesize')) {
            $this->maxfilesize = $options->maxfilesize;
        }
    }

    /**
     * Finish writing the zip footer.
     */
    public function finish(): void{
        $this->archive->finish();

        if ($this->zipfilehandle) {
            fclose($this->zipfilehandle);
        }
    }

    /**
     * Get the stream writer.
     *
     * @param string $filename
     * @param stdClass|null $exportoptions
     * @return static
     */
    public static function get_stream_writer(string $filename, stdClass $exportoptions = null) {
        $options = new \ZipStream\Option\Archive();
        $options->setSendHttpHeaders(true);
        $archive = new \ZipStream\ZipStream($filename, $options);

        $zipwriter = new static($archive, $exportoptions);

        \core\session\manager::write_close();
        return $zipwriter;
    }

    /**
     * Get the file writer.
     *
     * @param string $filename
     * @param stdClass|null $exportoptions
     * @return static
     */
    public static function get_file_writer(string $filename, stdClass $exportoptions = null) {
        $options = new \ZipStream\Option\Archive();

        $dir = make_request_directory();
        $filepath = $dir . "/$filename";
        $fh = fopen($filepath, 'w');

        $options->setOutputStream($fh);
        $options->setSendHttpHeaders(false);
        $archive = new \ZipStream\ZipStream($filename, $options);

        $zipwriter = new static($archive, $exportoptions);

        $zipwriter->zipfilehandle = $fh;
        $zipwriter->zipfilepath = $filepath;


        \core\session\manager::write_close();
        return $zipwriter;
    }

    /**
     * Get the file path for a file-based zip writer.
     *
     * If this is not a file-based writer then no value is returned.
     *
     * @return  null|string
     */
    public function get_file_path(): ?string {
        return $this->zipfilepath;
    }

    /**
     * Add a file from the File Storage API.
     *
     * @param   context $context
     * @param   string $filepathinzip
     * @param   stored_file $file The file to add
     */
    public function add_file_from_stored_file(
        context $context,
        string $filepathinzip,
        stored_file $file
    ): void {
        $fullfilepathinzip = $this->get_context_path($context, $filepathinzip);

        if ($file->get_filesize() <= $this->maxfilesize) {
            $filehandle = $file->get_content_file_handle();
            $this->archive->addFileFromStream($fullfilepathinzip, $filehandle);
            fclose($filehandle);

            $this->filesinzip[] = $fullfilepathinzip;
        }
    }

    /**
     * Add a file from string content.
     *
     * @param   context $context
     * @param   string $filepathinzip
     * @param   string $content
     */
    public function add_file_from_string(
        context $context,
        string $filepathinzip,
        string $content
    ): void {
        $fullfilepathinzip = $this->get_context_path($context, $filepathinzip);

        $this->archive->addFile($fullfilepathinzip, $content);

        $this->filesinzip[] = $fullfilepathinzip;
    }

    /**
     * Add a file from string content.
     *
     * @param   context $context
     * @param   string $filepathinzip
     * @param   string $content
     */
    public function add_file_from_html_string(
        context $context,
        string $filepathinzip,
        string $content
    ): void {
        $fullfilepathinzip = $this->get_context_path($context, $filepathinzip);

        // TODO Wrap content in HTML body.

        $this->add_file_from_string($context, $filepathinzip, $content);
    }

    public function add_file_from_template(
        context $context,
        string $filepathinzip,
        string $template,
        stdClass $templatedata
    ): void {
        global $PAGE;

        $this->add_html_page_requirements();
        $templatedata->pathtotop = $this->get_relative_context_path($context, $this->rootcontext, '/');
        error_log("Path to top: {$templatedata->pathtotop} (from {$context->path})");

        $renderer = $PAGE->get_renderer('core');
        $this->add_file_from_string($context, $filepathinzip, $renderer->render_from_template($template, $templatedata));
    }


    /**
     * Check whether the file was actually added to the archive.
     *
     * @param   context $context
     * @param   string $filepathinzip
     * @return  bool
     */
    public function is_file_in_archive(context $context, string $filepathinzip): bool {
        $fullfilepathinzip = $this->get_context_path($context, $filepathinzip);

        return in_array($fullfilepathinzip, $this->filesinzip);
    }

    /**
     * Get the full path to the context within the zip.
     *
     * @param   context $context
     * @param   string $filepathinzip
     * @return  string
     */
    public function get_context_path(context $context, string $filepathinzip): string {
        // TODO Add additional path sanitisation here.

        if (!$context->is_child_of($this->rootcontext, true)) {
            throw new \coding_exception("Unexpected path requested");
        }

        // Fetch the path from the course down.
        $parentcontexts = array_filter(
            $context->get_parent_contexts(true),
            function(context $curcontext): bool {
                return $curcontext->is_child_of($this->rootcontext, true);
            }
        );

        foreach (array_reverse($parentcontexts) as $curcontext) {
            $path[] = $this->get_context_folder_name($curcontext);
        }

        $path[] = $filepathinzip;

        $finalpath = implode($path, DIRECTORY_SEPARATOR);

        // Remove paths like ./
        $finalpath = str_replace('./', '/', $finalpath);

        // De-duplicate slashes.
        $finalpath = str_replace('//', '/', $finalpath);

        // Remove leading /.
        ltrim($finalpath, '/');

        return $finalpath;
    }

    /**
     * Get a relative path to the specified context path.
     *
     * @param   context $rootcontext
     * @param   context $targetcontext
     * @param   string $filepathinzip
     * @return  string
     */
    public function get_relative_context_path(context $rootcontext, context $targetcontext, string $filepathinzip): string {
        $path = [];
        if ($targetcontext === $rootcontext) {
            $lookupcontexts = [];
        } else if ($targetcontext->is_child_of($rootcontext, true)) {
            error_log("{$targetcontext->path} is a child of {$rootcontext->path}");

            // Fetch the path from the course down.
            $lookupcontexts = array_filter(
                $targetcontext->get_parent_contexts(true),
                function(context $curcontext): bool {
                    return $curcontext->is_child_of($this->rootcontext, false);
                }
            );

            foreach ($lookupcontexts as $curcontext) {
                array_unshift($path, $this->get_context_folder_name($curcontext));
            }
        } else if ($targetcontext->is_parent_of($rootcontext, true)) {
            error_log("{$targetcontext->path} is a parent of {$rootcontext->path}");
            $lookupcontexts = $targetcontext->get_parent_contexts(true);
            $path[] = '..';
        }

        $path[] = $filepathinzip;
        $relativepath =  implode($path, DIRECTORY_SEPARATOR);

        // De-duplicate slashes and remove leading /.
        $relativepath = ltrim(preg_replace('#/+#', '/', $relativepath), '/');

        if (substr($relativepath, 0, 1) !== '.') {
            $relativepath = "./{$relativepath}";
        }

        error_log($relativepath);
        return $relativepath;
    }

    protected function get_context_folder_name(context $context): string {
        $shortenedname = shorten_text(
            clean_param($context->get_context_name(), PARAM_FILE),
            self::MAX_CONTEXT_NAME_LENGTH,
            true,
            json_decode('"' . '\u2026' . '"')
        );

        return "{$shortenedname}_.{$context->id}";
    }

    /**
     * Get the Plugin File URL for a file in the zip.
     *
     * @param   int $contextid
     * @param   string $component
     * @param   string $area
     * @param   int $itemid
     * @param   string $pathname
     * @param   string $filename
     * @return  string
     */
    protected function get_tokenurl(
        string $contextid,
        string $component,
        string $area,
        int $itemid,
        string $pathname,
        string $filename
    ): string {
        return moodle_url::make_pluginfile_url($contextid, $component, $area, $itemid, $pathname, $filename, true, true)->out(false);
    }

    /**
     * Prepare a text area by processing pluginfile URLs within it.
     *
     * @param array $filesinzip
     * @param string $component
     * @param string $filearea
     * @param int $itemid
     * @param string $text
     * @return string
     */
    public function rewrite_pluginfile_urls(array $filesinzip, string $component, string $filearea, int $itemid, string $text): string {
        // TODO For each instance of '@@PLUGINFILE@@' replace that usage with the relevant PLUGINFILE usage.
        return $text;
    }

    public function add_html_page_requirements(): void {
        global $CFG;

        if ($this->pagerequirementsadded) {
            return;
        }

        // CSS required.
        $this->add_core_content('/theme/boost/style/moodle.css', 'shared/moodle.css');

        // Icons to be used.
        $this->add_core_content('/pix/moodlelogo.svg', 'shared/moodlelogo.svg');

        $this->pagerequirementsadded = true;
    }

    protected function add_core_content(string $dirrootpath, string $pathinzip): void {
        global $CFG;

        $this->archive->addFileFromPath(
            $this->get_context_path($this->rootcontext, $pathinzip),
            "{$CFG->dirroot}/{$dirrootpath}"
        );
    }

}
