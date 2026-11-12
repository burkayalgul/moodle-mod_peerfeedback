<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * List of peer feedback activities in a course.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_login($course);

$PAGE->set_url('/mod/peerfeedback/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'peerfeedback'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'peerfeedback'));

$table = new html_table();
$table->head = [get_string('name')];
foreach (get_all_instances_in_course('peerfeedback', $course) as $pf) {
    $url = new moodle_url('/mod/peerfeedback/view.php', ['id' => $pf->coursemodule]);
    $table->data[] = [html_writer::link($url, format_string($pf->name))];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
