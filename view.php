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
 * Main activity page: teacher overview, or the student answering screen.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');

use mod_peerfeedback\local\qtype\base;
use mod_peerfeedback\local\question_manager;
use mod_peerfeedback\local\response_manager;

$id = required_param('id', PARAM_INT);
$peerid = optional_param('peer', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'peerfeedback');
$pf = $DB->get_record('peerfeedback', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/peerfeedback:view', $context);

$PAGE->set_url('/mod/peerfeedback/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($pf->name));
$PAGE->set_heading($course->fullname);

$event = \mod_peerfeedback\event\course_module_viewed::create(['objectid' => $pf->id, 'context' => $context]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('peerfeedback', $pf);
$event->trigger();
(new completion_info($course))->set_module_viewed($cm);

$renderer = $PAGE->get_renderer('mod_peerfeedback');
$rm = new response_manager($pf, $context);

if (has_capability('mod/peerfeedback:review', $context)) {
    echo $OUTPUT->header();
    echo $renderer->tabs($cm, 'overview');
    echo $renderer->render_from_template('mod_peerfeedback/overview', $renderer->overview_context($cm, $pf, $context));
    echo $renderer->branding_footer();
    echo $OUTPUT->footer();
    exit;
}

require_capability('mod/peerfeedback:submit', $context);
$peers = $rm->get_peers($USER->id);
$questions = (new question_manager($pf))->get_questions();
$errors = [];
$posted = null;

if ($peers) {
    $peerids = array_map('intval', array_keys($peers));
    if (!in_array($peerid, $peerids, true)) {
        $peerid = $peerids[0];
        foreach ($peerids as $pid) {
            $r = $rm->get_response($USER->id, $pid);
            if (!$r || $r->status < 1) {
                $peerid = $pid;
                break;
            }
        }
    }
    if (data_submitted() && confirm_sesskey()) {
        $submit = optional_param('submitbtn', '', PARAM_RAW) !== '';
        $posted = [];
        foreach ($questions as $q) {
            $posted[$q->id] = in_array($q->qtype, ['multichoice', 'grid'], true)
                ? optional_param_array('q' . $q->id, [], PARAM_RAW_TRIMMED)
                : optional_param('q' . $q->id, '', PARAM_RAW_TRIMMED);
        }
        $errors = $rm->save($USER->id, $peerid, $posted, $submit);
        if (!$errors) {
            $pos = array_search($peerid, $peerids, true);
            $next = $submit ? $peerid : ($peerids[$pos + 1] ?? $peerid);
            if ($submit) {
                \core\notification::success(get_string('feedbacksubmitted', 'peerfeedback'));
            }
            redirect(new moodle_url('/mod/peerfeedback/view.php', ['id' => $cm->id, 'peer' => $next]));
        }
    }
}

echo $OUTPUT->header();
echo $renderer->render_from_template('mod_peerfeedback/answer',
    $renderer->answer_context($cm, $pf, $rm, $peers, $peerid, $questions, $posted, $errors));
echo $renderer->branding_footer();
echo $OUTPUT->footer();
