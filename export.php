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
 * Download submitted feedback by giver or by receiver.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_peerfeedback\local\exporter;

$id = required_param('id', PARAM_INT);
$by = optional_param('by', 'giver', PARAM_ALPHA) === 'receiver' ? 'receiver' : 'giver';
$format = optional_param('format', '', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'peerfeedback');
$pf = $DB->get_record('peerfeedback', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/peerfeedback:export', $context);

$PAGE->set_url('/mod/peerfeedback/export.php', ['id' => $cm->id, 'by' => $by]);

if ($format !== '') {
    $exporter = new exporter($pf);
    $filename = clean_filename('peerfeedback-' . $by . '-' . userdate(time(), '%Y-%m-%d'));
    \core\dataformat::download_data($filename, $format, $exporter->headers(), $exporter->rows($by));
    exit;
}

$PAGE->set_title(format_string($pf->name) . ': ' . get_string('export', 'peerfeedback'));
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->disable();
echo $OUTPUT->header();
echo $PAGE->get_renderer('mod_peerfeedback')->tabs($cm, 'review');
echo $OUTPUT->heading(get_string($by === 'receiver' ? 'exportbyreceiverheading' : 'exportbygiverheading', 'peerfeedback'));
echo $OUTPUT->download_dataformat_selector(
    get_string('downloadas', 'table'),
    'export.php',
    'format',
    ['id' => $cm->id, 'by' => $by]
);
echo $PAGE->get_renderer('mod_peerfeedback')->branding_footer();
echo $OUTPUT->footer();
