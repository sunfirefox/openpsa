<?php
/**
 * @package midcom.helper
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Image Filter Class, used to post process all images, check the documentation
 * of the individual methods.
 *
 * This class always works on existing files at this time. It supports scripting
 * support by using the process_chain / process_command helper functions, which
 * allow the application to let the user specify a list of filters that should
 * be applied during upload.
 *
 * @todo Image type automatic best-web-format conversion
 * @todo Add support for working on attachments
 * @author Eero af Heurlin, Nemein Oy <eero.afheurlin@nemein.com>
 * @author Nico Kaiser <nico@siriux.net>
 * @author Torben Nehmer <torben@nehmer.net>
 * @version 1.1 (2005-11-03)
 * @package midcom.helper
 */
class midcom_helper_imagefilter
{
    /**
     * The file currently being processed.
     *
     * @var string
     */
    private $_filename = null;

    /**
     * The quality to use for JPEG manipulation, this is not
     * yet settable from the outside.
     *
     * Stored as a valid imagemagick option, defaults to '-quality 90' right now.
     *
     * @var string
     */
    private $_quality = "-quality 90";

    public function __construct(midcom_db_attachment $input = null)
    {
        if (null !== $input)
        {
            $tmpfile = $this->create_tmp_copy($input);
            if ($tmpfile === false)
            {
                throw new midcom_error("Could not create a working copy, aborting");
            }

            if (!$this->set_file($tmpfile))
            {
                // Clean up
                unlink($tmpfile);
                throw new midcom_error("set_file() failed, aborting");
            }
        }
    }

    public function __destruct()
    {
        if (   !empty($this->_filename)
            && file_exists($this->_filename))
        {
            unlink($this->_filename);
        }
    }

    /**
     * Creates a working copy to filesystem from given attachment object
     *
     * @param mixed $input The attachment object or filename to copy
     * @return string tmp file name (or false on failure)
     */
    function create_tmp_copy($input)
    {
        $tmpname = $this->_get_tempfile();

        if (is_string($input))
        {
            // TODO: error handling
            copy($input, $tmpname);
            // With the following line, filesize() will return zero, see https://bugs.php.net/bug.php?id=65701
            clearstatcache();
            return $tmpname;
        }

        $src = $input->open('r');
        if (!$src)
        {
            debug_add("Could not open attachment #{$input->id} for reading", MIDCOM_LOG_ERROR);
            return false;
        }

        $dst = fopen($tmpname, 'w+');
        if (!$dst)
        {
            debug_add("Could not open file '{$tmpname}' for writing", MIDCOM_LOG_ERROR);
            unlink($tmpname);
            return false;
        }
        stream_copy_to_stream($src, $dst);
        $input->close();
        fclose($dst);
        return $tmpname;
    }

    public function write(midcom_db_attachment $target)
    {
        $src = fopen($this->_filename, 'r');
        if (!$src)
        {
            debug_add("Could not open file '{$this->_filename}' for reading", MIDCOM_LOG_ERROR);
            return false;
        }
        if (!$target->copy_from_handle($src))
        {
            debug_add("copy_from_handle() failed", MIDCOM_LOG_ERROR);
            fclose($src);
            return false;
        }
        fclose($src);
        return true;
    }

    public static function imagemagick_available()
    {
        static $return = -1;
        if ($return !== -1)
        {
            return $return;
        }
        $convert_cmd = escapeshellcmd(midcom::get('config')->get('utility_imagemagick_base') . "convert -version");
        $output = array();
        $ret = null;
        exec($convert_cmd, $output, $ret);
        if ($ret !== 0 && $ret !== 1)
        {
            debug_add("ImageMagick, '{$convert_cmd}' (part of ImageMagick suite) returned failure", MIDCOM_LOG_ERROR);
            $return = false;
            return $return;
        }
        $return = true;
        return $return;
    }

    private function _jpegtran_available()
    {
        static $return = -1;
        if ($return !== -1)
        {
            return $return;
        }
        $cmd = midcom::get('config')->get('utility_jpegtran');
        if (!$cmd)
        {
            $return = false;
            return $return;
        }
        if (!file_exists($cmd))
        {
            $find_cmd = escapeshellcmd('which ' . midcom::get('config')->get('utility_jpegtran'));
            $output = array();
            $ret = null;

            exec($find_cmd, $output, $ret);

            if ($ret !== 0)
            {
                debug_add("jpegtran (part of libjpeg suite) was not found", MIDCOM_LOG_ERROR);
                $return = false;
                return $return;
            }
        }
        $return = true;
        return $return;
    }

    /**
     * Sets the filename of the image currently being edited.
     *
     * This must be the full path to the file, it will be
     * replaced with the modified image.
     *
     * The process will check for write permissions at this point,
     * A return value of false will indicate some problem, see the
     * debug log for details.
     *
     * @todo Use ImageMagick Identify to check for a valid image.
     *
     * @param string filename The file to be edited.
     * @return boolean true, if the file is valid, false otherwise.
     */
    function set_file($filename)
    {
        if (!self::imagemagick_available())
        {
            debug_add("ImageMagick is not available, can't do any operations", MIDCOM_LOG_ERROR);
            midcom::get('uimessages')->add('midcom.helper.imagefilter', "ImageMagick is not available, can't process commands", 'error');
            return false;
        }
        if (! is_writeable($filename))
        {
            debug_add("The File {$filename} is not writeable.", MIDCOM_LOG_ERROR);
            return false;
        }
        $this->_filename = $filename;
        return true;
    }

    public function get_file()
    {
        return $this->_filename;
    }

    /**
     * This function will process a string-based filter chain.
     *
     * This is a semicolon-separated list of commands, which will be evaluated
     * using process_command each.
     *
     * Processing stops as soon as one filter command fails.
     *
     * @param string chain The filter chain to be processed (filter1();filter2();...)
     * @return boolean true, if all filters have been successfully applied, false otherwise.
     */
    function process_chain($chain)
    {
        $filters = explode(";", $chain);

        foreach ($filters as $cmd)
        {
            if ($cmd == '')
            {
                // Skip empty commands
                continue;
            }

            if (!$this->process_command($cmd))
            {
                debug_add("Execution of {$cmd} failed, aborting now.");
                midcom::get('uimessages')->add('midcom.helper.imagefilter', "Execution of {$cmd} failed", 'error');
                return false;
            }
        }

        return true;
    }

    /**
     * This is the main function evaluator of the filter class.
     *
     * It will take a function style command like
     * resize(90,90), arguments are separated by commas, there is no
     * quoting at the moment. All arguments are parsed as strings,
     * function names need to be [a-z_]
     *
     * Execution will relay to the corresponding filter function.
     *
     * All filters will use defaults for missing arguments (which can
     * result in a null operation) and will ignore excessive arguments.
     *
     * @param string cmd The command to be executed.
     * @return boolean true, if the filter executed successfully, false otherwise.
     */
    function process_command($cmd)
    {
        $i = preg_match('/([a-z_]*)\(([^)]*)\)/', $cmd, $matches);
        if (! $i)
        {
            debug_add("Failed to parse command {$cmd}, aborting.", MIDCOM_LOG_INFO);
            return false;
        }
        $command = $matches[1];
        $args = explode(',', $matches[2]);

        debug_print_r("Have to execute {$command} with these arguments:", $args);

        if ($command == 'none')
        {
            return true;
        }
        else if (is_callable(array($this, $command)))
        {
            return call_user_func_array(array($this, $command), $args);
        }

        debug_add('This is no known command, we try to find a callback.');
        return $this->execute_user_callback($command, $args);
    }

    /*********** INTERNAL HELPERS ******************/

    /**
     * Returns the name of a temporary file to be used to write
     * the transformed image to. Has to be managed by the callee.
     */
    private function _get_tempfile()
    {
        return tempnam(midcom::get('config')->get('midcom_tempdir'), "midcom_helper_imagefilter");
    }

    /**
     * This will replace the original file with the processed copy
     * of $tmpfile, deleting the temporary file afterwards.
     */
    private function _process_tempfile($tmpname)
    {
        copy($tmpname, $this->_filename);
        unlink($tmpname);
    }

    /*********** IMAGE MANIPULATION STUFF *****************/

    /**
     * Executes a custom image manipulation callback.
     * The command name is the function that is searched. The function must
     * use the following signature:
     *
     * boolean callback (string $infile, string $outfile, array $parameters);
     *
     * infile is the file to be manipulated, outfile the name where
     * the result has to be stored in (a temporary file usually) and
     * the parameters passed to the command originally. It should return
     * true on a successful transformation, false otherwise.
     *
     * @param string $command  The name of the callback to execute
     * @param array $args      The arguments passed to the callback
     * @return The return code of the callback.
     */
    function execute_user_callback($command, $args)
    {
        if (! function_exists($command))
        {
            debug_add("The function {$command} could not be found, aborting", MIDCOM_LOG_ERROR);
            return false;
        }
        $tmpfile = $this->_get_tempfile();

        if (! $command($this->_filename, $tmpfile, $args))
        {
            unlink($tmpfile);
            return false;
        }

        $this->_process_tempfile($tmpfile);
        return true;
    }

    /**
     * Gamma-Adjustment of the image.
     *
     * Filter Syntax: gamma($gamma)
     *
     * Where $gamma is a positive floating point number, e.g. 1.2
     *
     * @param float $gamma Gamma adjustment value.
     * @return boolean true on success.
     */
    public function gamma($gamma = 1.2)
    {
        $gamma = (double) $gamma;

        $cmd = midcom::get('config')->get('utility_imagemagick_base') . "mogrify {$this->_quality} -gamma "
            . escapeshellarg($gamma) . " " . escapeshellarg($this->_filename);

        exec($cmd, $output, $exit_code);

        if ($exit_code === 0)
        {
            return true;
        }
        else
        {
            debug_print_r("ImageMagick failed to convert the image, it returned with {$exit_code}, the generated output was:", $output, MIDCOM_LOG_ERROR);
            debug_add("Command was: [{$cmd}]");
            return false;
        }
    }

    /**
     * This function converts the image to the specified image format.
     *
     * It must be a suitable extension to use with the Imagemagick convert
     * utility. The file will not be renamed, you have to do this yourself.
     *
     * This call will always convert the first page only of the supplied image, otherwise,
     * Imagemagick would spam the temp directory in case we are working on a PDF file or
     * whatever.
     *
     * @param string $format The format to convert to. This must be a valid conversion targed
     *     recognized by Imagemagick, it defaults to 'jpg'.
     */
    public function convert($format = 'jpg')
    {
        $tempfile = $this->_get_tempfile();

        $cmd = midcom::get('config')->get('utility_imagemagick_base') . "convert {$this->_quality} "
            . escapeshellarg("{$this->_filename}[0]") . " {$format}:{$tempfile}";

        exec($cmd, $output, $exit_code);

        if ($exit_code === 0)
        {
            $this->_process_tempfile($tempfile);
            return true;
        }
        else
        {
            unlink($tempfile);
            debug_print_r("ImageMagick failed to convert the image, it returned with {$exit_code}, the generated output was:", $output, MIDCOM_LOG_ERROR);
            debug_add("Command was: [{$cmd}]");
            return false;
        }
    }

    /**
     * Automatic rotation for the image using EXIF tags.
     *
     * NOTE: This REQUIRES php_read_exif to be present. It will fail
     * if it is missing.
     *
     * Filter Syntax: exifrotate()
     *
     * @return boolean true on success.
     */
    public function exifrotate()
    {
        if (! function_exists("exif_read_data"))
        {
            debug_add("exif_read_data required for exifrotate.", MIDCOM_LOG_ERROR);
            return false;
        }
        // Silence this, gives warnings on images that do not contain EXIF data
        try
        {
            $exif = @exif_read_data($this->_filename);
        }
        catch (Exception $e)
        {
            debug_add("Could not read EXIF data: " . $e->getMessage() . ", skipping.", MIDCOM_LOG_WARN);
            return true;
        }
        if (!is_array($exif))
        {
            debug_add("Could not read EXIF data, skipping.", MIDCOM_LOG_WARN);
            return true;
        }
        if (!array_key_exists('Orientation', $exif))
        {
            debug_add("EXIF information misses the orientation tag. Skipping.", MIDCOM_LOG_INFO);
            return true;
        }
        if ($exif["Orientation"] == 1)
        {
            debug_add("No rotation necessary.");
            return true;
        }

        $do_unlink = false;
        $imagesize = getimagesize($this->_filename);

        if (   $imagesize[2] == IMAGETYPE_JPEG
            && $this->_jpegtran_available())
        {
            /* jpegtran */
            $operations = array
            (
                2 => "-flip horizontal",
                3 => "-rotate 180",
                4 => "-flip vertical",
                5 => "-transpose",
                6 => "-rotate 90",
                7 => "-transverse",
                8 => "-rotate 270"
            );

            $tmpfile = $this->_get_tempfile();
            $do_unlink = true;
            $cmd = midcom::get('config')->get('utility_jpegtran') . " -outfile {$tmpfile} -copy all";
        }
        else
        {
            /* Mogrify */
            debug_add("jpegtran not found, falling back to mogrify.");

            $operations = array
            (
                2 => "-flip",
                3 => "-rotate 180",
                4 => "-flip",
                5 => "-rotate 90 -flip",
                6 => "-rotate 90",
                7 => "-rotate 270 -flop",
                8 => "-rotate 270"
            );

            $cmd = midcom::get('config')->get('utility_imagemagick_base') . "mogrify {$this->_quality}";
        }
        if (!array_key_exists($exif["Orientation"], $operations))
        {
            debug_add("Unsupported EXIF-Rotation tag encountered, ingoring: " . $exif["Orientation"], MIDCOM_LOG_INFO);
            return true;
        }
        $cmd .= ' ' . $operations[$exif["Orientation"]] . ' ' . escapeshellarg($this->_filename);

        debug_add("executing: {$cmd}");
        exec($cmd, $output, $exit_code);

        if ($exit_code !== 0)
        {
            debug_print_r("Imagemagick/jpegtran returned with {$exit_code} and produced this output:", $output, MIDCOM_LOG_ERROR);
            debug_add("Command was: {$cmd}");
            if ($do_unlink)
            {
                unlink($tmpfile);
            }
            return false;
        }

        if ($do_unlink)
        {
            $this->_process_tempfile($tmpfile);
        }
        return true;
    }

    /**
     * Manual image rotation
     *
     * Filter Syntax: rotate($rotate)
     *
     * Where $rotate is a positive floating point number greater than 0
     * and less than 360; if omitted, a null operation is done.
     *
     * @param float $rotate Degrees of rotation clockwise, negative amounts possible
     * @return boolean true on success.
     */
    public function rotate($rotate = 0)
    {
        // Do some normalizing on the argument
        while ($rotate < 0)
        {
            $rotate += 360;
        }
        while ($rotate > 360)
        {
            $rotate -= 360;
        }
        if (   $rotate == 0
            || $rotate == 360)
        {
            debug_add("Rotate is {$rotate}, we're happy as-is.");
            return true;
        }

        $do_unlink = false;
        $imagesize = getimagesize($this->_filename);

        // Try lossless jpegtran rotation if possible
        if (   $imagesize[2] == 2
            && ($rotate == 90 || $rotate == 180 || $rotate == 270)
            && $this->_jpegtran_available())
        {
            $tmpfile = $this->_get_tempfile();
            $do_unlink = true;
            $cmd = midcom::get('config')->get('utility_jpegtran') . " -copy all -rotate {$rotate} -outfile {$tmpfile} " . escapeshellarg($this->_filename);
        }
        else
        {
            /* Mogrify */
            debug_add("jpegtran not found or rotation incompatible, falling back to mogrify.");

            $cmd = midcom::get('config')->get('utility_imagemagick_base') . "mogrify {$this->_quality} -rotate {$rotate} " . escapeshellarg($this->_filename);
        }

        debug_add("We have to rotate clockwise by {$rotate} degrees, do_unlink: {$do_unlink}");
        exec($cmd, $output, $exit_code);

        if ($exit_code !== 0)
        {
            debug_print_r("Imagemagick/jpegtran returned with {$exit_code} and produced this output:", $output, MIDCOM_LOG_ERROR);
            debug_add("Command was: {$cmd}");
            if ($do_unlink)
            {
                unlink($tmpfile);
            }
            return false;
        }

        if ($do_unlink)
        {
            $this->_process_tempfile($tmpfile);
        }
        return true;
    }

    /**
     * Image Resize: This will resize the original image.
     *
     * Filter Syntax: resize ($x, $y)
     *
     * The width of the image in pixels, it will be scaled in
     * proportion. One of the two parameters can be 0, meaning that
     * the width/height of the image is adjusted to the other, given
     * value.
     *
     * The resize will be done only if it would not increase the image
     * as this won't gain you any benefit.
     *
     * @param int $x Width
     * @param int $y Height
     * @return boolean true on success.
     */
    public function resize($x = 0, $y = 0)
    {
        $x = (int) $x;
        $y = (int) $y;

        if ($x == 0 && $y == 0)
        {
            debug_add("Both x and y are 0, skipping operation.", MIDCOM_LOG_INFO);
            return true;
        }

        if ($x == 0)
        {
            $geo = "x{$y}>";
        }
        else if ($y == 0)
        {
            $geo = "{$x}x>";
        }
        else
        {
            $geo = "{$x}x{$y}>";
        }
        $geo = "-geometry " . escapeshellarg($geo);

        $cmd = midcom::get('config')->get('utility_imagemagick_base') . "mogrify {$this->_quality} {$geo} "
            . escapeshellarg($this->_filename);

        $output = null;
        $exit_code = 0;
        exec($cmd, $output, $exit_code);

        if ($exit_code !== 0)
        {
            debug_print_r("Imagemagick returned with {$exit_code} and produced this output:", $output, MIDCOM_LOG_ERROR);

            debug_add("Command was: {$cmd}");
            return false;
        }

        return true;
    }

    /**
     * Square Thumbnails: This will resize&crop the original image.
     *
     * Filter Syntax: squarethumb ($x)
     *
     * The filter will adapt picture to given width, point gravity
     * to the centre and crop leftovers.
     *
     * This is a one parameter shorthand for cropping, provided for
     * backwards compatibility
     *
     * @param int $x Width
     * @return boolean true on success.
     */
    public function squarethumb($x = 0, $gravity = 'center')
    {
        return $this->crop($x, $x, $gravity);
    }

    /**
     * Crop an image to given proportions
     *
     * @param int $x Width
     * @param int $y Height
     * @return boolean true on success.
     */
    public function crop($x = 0, $y = 0, $gravity = 'center')
    {
        if ($x == 0)
        {
            return true;
        }

        if ($y == 0)
        {
            $y = $x;
        }

        $data = @getimagesize($this->_filename);

        // 1a. If got...
        if ($data)
        {
            $size_x = $data[0];
            $size_y = $data[1];
        }
        else
        {
            // If image data was not available, try to get it with identify program
            $cmd = midcom::get('config')->get('utility_imagemagick_base') . "identify -verbose {$this->_filename}";
            exec($cmd, $output, $exit_code);

            if ($exit_code !== 0)
            {
                debug_print_r("Imagemagick returned with {$exit_code} and produced this output:", $output, MIDCOM_LOG_ERROR);
                debug_add("Command was: {$cmd}");
            }

            $output = implode("\n", $output);

            if (!preg_match('/Geometry:\s([0-9]+)x([0-9]+)/i', $output, $regs))
            {
                return false;
            }

            $size_x = (int) $regs[1];
            $size_y = (int) $regs[2];
        }

        // Get resize ratio in relations to the original
        $ratio = str_replace(',', '.', 100 * max($x / $size_x, $y / $size_y));
        $cmd = midcom::get('config')->get('utility_imagemagick_base') . "mogrify {$this->_quality} -resize {$ratio}% -gravity {$gravity} -crop {$x}x{$y}+0+0 +repage " . escapeshellarg($this->_filename);
        exec($cmd, $output, $exit_code);

        if ($exit_code !== 0)
        {
            debug_print_r("Imagemagick returned with {$exit_code} and produced this output:", $output, MIDCOM_LOG_ERROR);
            debug_add("Command was: {$cmd}");
            return false;
        }

        return true;
    }

    /**
     * Resize image and apply fill to match given size
     *
     * @param int $x Width
     * @param int $y Height
     * @param string $color Color
     * @param string $gravity Gravity point
     * @return boolean true on success.
     */
    public function fill($x = null, $y = null, $color = null, $gravity = 'center')
    {
        if (   empty($x)
            || empty($y)
            || empty($color))
        {
            //This is a bit silly, but here for backwards compatibility...
            return true;
        }

        // Currently accepting only hex colors
        if (!preg_match('/^#?([0-9a-f]{3}){1,2}$/', $color))
        {
            debug_add("Given color ({$color}) is not hex RGB.", MIDCOM_LOG_ERROR);
            return false;
        }

        $cmd = midcom::get('config')->get('utility_imagemagick_base') . "mogrify {$this->_quality} -resize '{$x}x{$y}' -background '{$color}' -gravity {$gravity} -extent {$x}x{$y} +repage " . escapeshellarg($this->_filename);

        $output = null;
        $exit_code = 0;
        exec($cmd, $output, $exit_code);

        if ($exit_code !== 0)
        {
            debug_print_r("Imagemagick returned with {$exit_code} and produced this output:", $output, MIDCOM_LOG_ERROR);
            debug_add("Command was: {$cmd}");
            return false;
        }

        return true;
    }
}
?>