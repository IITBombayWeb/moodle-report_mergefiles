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
 * Libs, public API.
 *
 * NOTE: page type not included because there can not be any blocks in popups
 *
 * @package    tool_mergefiles
 * @copyright  2017 IIT Bombay
 * @author     Kashmira Nagwekar
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * This function extends the navigation with the report items
 *
 * @global stdClass       $CFG
 * @global core_renderer  $OUTPUT
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass        $course     The course to object for the report
 * @param context         $context    The context of the course
 */
function tool_mergefiles_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('tool/mergefiles:view', $context)) {
        $url = new moodle_url('/admin/tool/mergefiles/index.php', array('courseid' => $course->id));
        $navigation->add(get_string('pluginname', 'tool_mergefiles'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                null,
                new pix_icon('t/download', ''));
    }
}

// function <component>_extend_navigation_course(navigation_node $parentnode, stdClass $course, context_course $context);
