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
 * Zip writter wrapper.
 *
 * @package core_course\coursecontentexport
 * @copyright 2020 Simey Lameze <simey@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_course\coursecontentexport;

use stdClass;
use stored_file;
use context;

/**
 * Zip writter wrapper.
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
    protected $maxfilesize = 1024 * 1024 * 1024 * 102;

    /** @var resource File resource for the file handle for a file-based zip stream */
    protected $zipfilehandle = null;

    /** @var string File path for a file-based zip stream */
    protected $zipfilepath = null;

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
     * @param   string $filepathinzip
     * @param   stored_file $file The file to add
     * @param   int $contextid
     * @param   string $component
     * @param   string $area
     * @param   int $itemid
     * @param   string $pathname
     * @param   string $filename
     * @return  string
     */
    public function add_file_from_stored_file(
        context $context,
        string $filepathinzip,
        stored_file $file,
        int $contextid,
        string $component,
        string $area,
        int $itemid,
        string $pathname,
        string $filename
    ): string {
        $fullfilepathinzip = $this->get_context_path($context, $filepathinzip);
        if ($file->get_filesize() > $this->maxfilesize) {
            // Add a placeholder which links back to the tokenpluginfile instead.
            // TODO: Look at whether an HTML Placeholder file is required here too.
            return $this->get_tokenurl(array_slice(func_get_args(), 3));
        } else {
            $filehandle = $file->get_content_file_handle();
            $this->archive->addFileFromStream($fullfilepathinzip, $filehandle);
            fclose($filehandle);

            return "./{$fullfilepathinzip}";
        }
    }

    /**
     * Get the full path to the context within the zip.
     *
     * @param   context $context
     * @param   string $filepathinzip
     * @return  string
     */
    protected function get_context_path(context $context, string $filepathinzip): string {
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
