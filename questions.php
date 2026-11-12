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
 * Question editor page.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_peerfeedback\form\question_form;
use mod_peerfeedback\local\qtype\base;
use mod_peerfeedback\local\question_manager;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$qid = optional_param('qid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'peerfeedback');
$pf = $DB->get_record('peerfeedback', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/peerfeedback:managequestions', $context);

$baseurl = new moodle_url('/mod/peerfeedback/questions.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($pf->name) . ': ' . get_string('questions', 'peerfeedback'));
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->disable();

$qm = new question_manager($pf);
$renderer = $PAGE->get_renderer('mod_peerfeedback');

if ($action === 'move' && confirm_sesskey()) {
    $qm->move($qid, optional_param('dir', 1, PARAM_INT));
    redirect($baseurl);
}
if ($action === 'delete' && confirm_sesskey()) {
    if (optional_param('confirm', 0, PARAM_BOOL)) {
        $qm->delete($qid);
        redirect($baseurl);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('confirmdeletequestion', 'peerfeedback'),
        new moodle_url($baseurl, ['action' => 'delete', 'qid' => $qid, 'confirm' => 1, 'sesskey' => sesskey()]), $baseurl);
    echo $OUTPUT->footer();
    exit;
}

if (in_array($action, ['add', 'edit', 'save'], true)) {
    $existing = $qid ? $qm->get_question($qid) : null;
    $qtype = $existing ? $existing->qtype : required_param('qtype', PARAM_ALPHA);
    base::get($qtype);
    $form = new question_form($baseurl, ['qtype' => $qtype, 'locked' => $existing && $qm->has_submissions()]);
    $defaults = ['id' => $cm->id] + ($existing ? question_form::data_from_question($existing) : []);
    $form->set_data($defaults);
    if ($form->is_cancelled()) {
        redirect($baseurl);
    }
    if ($data = $form->get_data()) {
        $config = question_form::config_from_data($data);
        $required = !empty($data->required);
        try {
            if ($existing) {
                $qm->update($existing->id, $data->questiontext, $required, $config);
            } else {
                $qm->add($qtype, $data->questiontext, $required, $config);
            }
            redirect($baseurl);
        } catch (moodle_exception $e) {
            \core\notification::error($e->getMessage());
        }
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string($existing ? 'editquestion' : 'addquestion', 'peerfeedback'));
    $form->display();
    echo $renderer->branding_footer();
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $renderer->tabs($cm, 'questions');
echo $renderer->render_from_template('mod_peerfeedback/questions',
    $renderer->questions_context($cm, $qm->get_questions(), $qm->has_submissions()));
echo $renderer->branding_footer();
echo $OUTPUT->footer();
