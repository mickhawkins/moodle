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
        // Remove paths like ./
        $filepathinzip = str_replace('./', '/', $filepathinzip);

        // De-duplicate slashes.
        $filepathinzip = str_replace('//', '/', $filepathinzip);

        // Remove leading /.
        ltrim($filepathinzip, '/');

        // TODO Add additional path sanitisation here.

        // Fetch the path from the course down.
        $parentcontexts = array_reverse($context->get_parent_contexts(true));
        foreach ($parentcontexts as $curcontext) {
            if ($curcontext->contextlevel < CONTEXT_COURSE) {
                // Ignore anything above the course level.
                continue;
            }

            $name = $curcontext->get_context_name();
            $id = ' _.' . $curcontext->id;
            $path[] = shorten_text(
                clean_param($name, PARAM_FILE),
                self::MAX_CONTEXT_NAME_LENGTH,
                true,
                json_decode('"' . '\u2026' . '"')
            ) . $id;
        }

        $path[] = $filepathinzip;
        return implode($path, DIRECTORY_SEPARATOR);
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

}
