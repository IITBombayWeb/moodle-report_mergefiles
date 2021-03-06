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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Merges pdf documents in a course.
 *
 * This file generates a merged pdf document of all the pdfs found in a particular course.
 *
 * @package     report_mergefiles
 * @copyright   2017 IIT Bombay
 * @author      Kashmira Nagwekar
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/backup/cc/cc_lib/gral_lib/pathutils.php'); // For using function rmdirr(string $dirname).
require_once($CFG->dirroot.'/report/mergefiles/performmerge_form.php');

if (empty($id)) {
    $id = required_param('courseid', PARAM_INT);
    $course = get_course($id);
    require_login($course);
    $context = context_course::instance($course->id);
    $coursename = format_string($course->fullname, true, array('context' => $context));
}
require_capability('report/mergefiles:view', $context);

$url = new moodle_url("/report/mergefiles/index.php", array ('courseid' => $course->id));

$PAGE->set_url($url);
$PAGE->set_pagelayout('report');

$strlastmodified    = get_string('lastmodified');
$strlocation        = get_string('location');
$strintro           = get_string('moduleintro');
$strname            = get_string('name');
$strresources       = get_string('resources');
$strsectionname     = get_string('sectionname', 'format_' . $course->format);
$strsize            = get_string('size');
$strsizeb           = get_string('sizeb');
$heading            = get_string('heading', 'report_mergefiles');
$note               = get_string('note', 'report_mergefiles');
$pluginname         = get_string('pluginname', 'report_mergefiles');

if (empty($id)) {
    //     $id = required_param('courseid', PARAM_INT);
    admin_externalpage_setup('reportmergefiles', '', null, '', array('pagelayout' => 'report'));
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title($course->shortname . ' | ' . $pluginname);
$PAGE->set_heading($course->fullname . ' | ' . $pluginname);

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);
echo $note . '<br><br>';

// Source code from course/resources.php...used for getting all the pdf files in the course in order to merge them.

// Get list of all resource-like modules.
$allmodules = $DB->get_records('modules', array ('visible' => 1));
$availableresources = array ();
foreach($allmodules as $key => $module) {
    $modname = $module->name;
    $libfile = "$CFG->dirroot/mod/$modname/lib.php";
    if (!file_exists($libfile)) {
        continue;
    }
    $archetype = plugin_supports('mod', $modname, FEATURE_MOD_ARCHETYPE, MOD_ARCHETYPE_OTHER);
    if ($archetype != MOD_ARCHETYPE_RESOURCE) {
        continue;
    }

    $availableresources[] = $modname; // List of all available resource types.
}

$modinfo = get_fast_modinfo($course); // Fetch all course data.
$usesections = course_format_uses_sections($course->format);

$cms = array ();
$resources = array ();

foreach($modinfo->cms as $cm) { // Fetch all modules in the course, like forum, quiz, resource etc.
    if (!in_array($cm->modname, $availableresources)) {
        continue;
    }
    if (!$cm->uservisible) {
        continue;
    }
    if (!$cm->has_view()) {
        // Exclude label and similar.
        continue;
    }
    $cms[$cm->id] = $cm;
    $resources[$cm->modname][] = $cm->instance; // Fetch only modules having modname -'resource'...
                                                // ...pdf files have modname 'resource'.
}

// Preload instances.
foreach($resources as $modname => $instances) { // Get data from mdl_resource table, namely, id, name of the pdf file etc.
    $resources[$modname] = $DB->get_records_list($modname, 'id', $instances, 'id', 'id,name');
}

if (!$cms) {
    notice(get_string('thereareno', 'moodle', $strresources), "$CFG->wwwroot/course/view.php?id=$course->id");
    exit();
}

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($usesections) {
    $strsectionname = get_string('sectionname', 'format_' . $course->format);
    $table->head = array (
            $strsectionname,
            $strname,
            $strintro,
            $strsize . ' (' . $strsizeb . ')');
            // "Location (moodledata/filedir/..)"
    $table->align = array (
            'center',
            'left',
            'left',
            'left');
} else {
    $table->head = array (
            $strlastmodified,
            $strname,
            $strintro,
            $strsize . ' (' . $strsizeb . ')');
            // $strlocation
    $table->align = array (
            'left',
            'left',
            'left',
            'left');
}

$fs = get_file_storage();

$tempdir = $CFG->tempdir . '/mergefiles'; // Create temporary storage location for files.
if (!file_exists($tempdir)) {
    $mkdir = mkdir($tempdir, 0777, true);
}
$tempdirpath = $tempdir . '/';

$currentsection = '';
foreach($cms as $cm) {
    if (!isset($resources[$cm->modname][$cm->instance])) {
        continue;
    }
    $resource = $resources[$cm->modname][$cm->instance];

    $printsection = '';
    if ($usesections) {
        if ($cm->sectionnum !== $currentsection) {
            if ($cm->sectionnum) {
                $printsection = get_section_name($course, $cm->sectionnum);
            }
            if ($currentsection !== '') {
                // $table->data[] = 'hr';
            }
            $currentsection = $cm->sectionnum;
        }
    }

    $extra = empty($cm->extra) ? '' : $cm->extra;
    $icon = '<img src="' . $cm->get_icon_url() . '" class="activityicon" alt="' . $cm->get_module_type_name() . '" /> ';
    $class = $cm->visible ? '' : 'class="dimmed"'; // Hidden modules are dimmed.

    // ----------------------------------------------------------------------------
    // Source from mod/resource/view.php...used for getting contenthash of the file.

    $context = context_module::instance($cm->id);
    $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);
    if (count($files) < 1) {
        // resource_print_filenotfound($resource, $cm, $course);
        continue;
    } else {
        $file = reset($files);
        unset($files);
    }

    // End of source from mod/resource/view.php.
    // ---------------------------------------------------------------------------

    $url = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename());

    static $p = 1;
    $file->copy_content_to($tempdirpath . $p . '.pdf');
    $arr[$p] = $tempdirpath . $p . '.pdf';

    $table->data[] = array (
            $printsection,
            "<a $class $extra href=\"" . $url . "\">" . $icon . $cm->get_formatted_name() . "</a>",
            $file->get_filename(),
            $file->get_filesize());
    $p++;
}

echo html_writer::table($table);

$mform = new performmerge_form(null);
$formdata = array ('courseid' => $id);
$mform->set_data($formdata);
$mform->display();

if ($data = $mform->get_data()) {
    if (!empty($data->save)) {

        // Code for merging all the course pdfs. ---------------------------------------------------
        // Merge all course pdf files and store the merged document at a temporary location.

        $mergedpdf = $tempdirpath . uniqid('mergedfile_') . '.pdf'; // Path to the merged pdf document with unique filename.

        // Merge all the pdf files in the course using pdftk.
        $cmd = "pdftk ";
        // Add each pdf file to the command.
        foreach($arr as $pdffile) {
            $cmd .= $pdffile . " ";
        }
        $cmd .= " output $mergedpdf";
        $result = shell_exec($cmd);

        // Create a blank numbered pdf document. ----------------------------------------------------

        // Find no. of pages in the merged pdf document.
        $noofpages = shell_exec("pdftk $mergedpdf dump_data | grep NumberOfPages | awk '{print $2}'");

        // Latex script for creating blank numbered pdf document.
        $startpage = 1;
        $texscript = '
	 		\documentclass[12pt,a4paper]{article}
	 		\usepackage{helvet}
	 		\usepackage{times}
	 		\usepackage{multido}
		 	\usepackage{fancyhdr}
			\usepackage[hmargin=.8cm,vmargin=1.5cm,nohead,nofoot]{geometry}
	 		\renewcommand{\familydefault}{\sfdefault}
	 		\begin{document}
	 		\fancyhf{} % clear all header and footer fields
	 		\renewcommand{\headrulewidth}{0pt}
	 		\pagestyle{fancy}
	 		%\rhead{{\large\bfseries\thepage}}
	 		\rhead{{\fbox{\large\bfseries\thepage}}}
	 		\setcounter{page}{' . $startpage . '}
	 		\multido{}{' . $noofpages . '}{\vphantom{x}\newpage}
	 		\end{document}
			';

        $tempfilename = uniqid('latexfile_');
        $latexfilename = $tempdirpath . $tempfilename;
        $latexfile = $latexfilename . '.tex';

        $latexfileinfo = array (
                'contextid' => $context->id,
                'component' => 'mod_resource',
                'filearea'  => 'content',
                'itemid'    => 0,
                'filepath'  => '/',
                'filename'  => $tempfilename . '.tex');

        $fs->create_file_from_string($latexfileinfo, $texscript);

        $latexfile1 = $fs->get_file(
                $latexfileinfo['contextid'],
                $latexfileinfo['component'],
                $latexfileinfo['filearea'],
                $latexfileinfo['itemid'],
                $latexfileinfo['filepath'],
                $latexfileinfo['filename']);

        $latexfile1->copy_content_to($latexfile);

        // Execute pdflatex with parameter.
        // Store the output blank numbered pdf document and all the intermediate files at the temp loc.
        $result1 = shell_exec('pdflatex -aux-directory=' . $tempdirpath . ' -output-directory=' . $tempdirpath . ' ' . $latexfile . ' ');

        // var_dump( $pdflatex );
        // Test for success.
        if (!file_exists($latexfile)) {
            print_r(file_get_contents($latexfilename . ".log"));
        } else {
            // echo "\nPDF created!\n";
        }

        // Merge the blank numbered pdf document with the merged pdf document (containing all course pdfs).
        $stampedpdf = $tempdirpath . uniqid('stampedfile_') . ".pdf";   // Unique filename (with entire path to the file) for the merged and stamped pdf document.

        $result2 = shell_exec("pdftk $mergedpdf multistamp " . $latexfilename . ".pdf output $stampedpdf");

        $stampedfilename = uniqid('stampedfile_') . '.pdf';
        $stampedfileinfo = array (
                'contextid' => $context->id,
                'component' => 'mod_resource',
                'filearea'  => 'content',
                'itemid'    => 0,
                'filepath'  => '/',
                'filename'  => $stampedfilename);

        $fs->create_file_from_pathname($stampedfileinfo, $stampedpdf);

        $stampedfile = $fs->get_file(
                $stampedfileinfo['contextid'],
                $stampedfileinfo['component'],
                $stampedfileinfo['filearea'],
                $stampedfileinfo['itemid'],
                $stampedfileinfo['filepath'],
                $stampedfileinfo['filename']);

        $stampedfileurl = moodle_url::make_pluginfile_url(
                $stampedfile->get_contextid(),
                $stampedfile->get_component(),
                $stampedfile->get_filearea(),
                $stampedfile->get_itemid(),
                $stampedfile->get_filepath(),
                $stampedfile->get_filename());

        rmdirr($tempdir);
        echo get_string('mergedpdfdoc', 'report_mergefiles') . " | " . "<a $class $extra href=\"" . $stampedfileurl . "\">" . $icon . get_string('availablehere', 'report_mergefiles') . "</a>";

    }
}

echo $OUTPUT->footer();