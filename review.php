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
 * Teacher review page: feedback given by / received by a student, with grading.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_peerfeedback\local\group_manager;
use mod_peerfeedback\local\response_manager;

$id = required_param('id', PARAM_INT);
$dir = optional_param('dir', 'given', PARAM_ALPHA);
$filter = optional_param('filter', 'all', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);
$dir = in_array($dir, ['given', 'received'], true) ? $dir : 'given';
$filter = in_array($filter, ['all', 'notreviewed', 'reviewed'], true) ? $filter : 'all';

[$course, $cm] = get_course_and_cm_from_cmid($id, 'peerfeedback');
$pf = $DB->get_record('peerfeedback', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/peerfeedback:review', $context);

$gm = new group_manager($pf, $context);
$rm = new response_manager($pf, $context);
$users = $gm->eligible_users();
if (!isset($users[$userid]) && $users) {
    $userid = (int)array_key_first($users);
}
$params = ['id' => $cm->id, 'dir' => $dir, 'filter' => $filter, 'userid' => $userid];
$pageurl = new moodle_url('/mod/peerfeedback/review.php', $params);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($pf->name) . ': ' . get_string('review', 'peerfeedback'));
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->disable();

$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '' && confirm_sesskey()) {
    $responseid = required_param('responseid', PARAM_INT);
    if ($action === 'reopen') {
        $rm->reopen($responseid);
    } else {
        $rawgrade = trim(optional_param('grade', '', PARAM_RAW_TRIMMED));
        $grade = $rawgrade === '' ? null : unformat_float($rawgrade, true);
        try {
            if ($grade === false) {
                throw new moodle_exception('err_gradeoutofrange', 'peerfeedback', '', $pf->grade);
            }
            $rm->review(
                $responseid,
                $grade,
                optional_param('comment', '', PARAM_TEXT),
                $action === 'markreviewed',
                $USER->id
            );
            \core\notification::success($action === 'markreviewed'
                ? get_string('markedreviewed', 'peerfeedback') : get_string('changessaved'));
        } catch (moodle_exception $e) {
            \core\notification::error($e->getMessage());
        }
    }
    redirect(new moodle_url($pageurl, [], 'r' . $responseid));
}

$renderer = $PAGE->get_renderer('mod_peerfeedback');
echo $OUTPUT->header();
echo $renderer->tabs($cm, 'review');
echo $renderer->render_from_template(
    'mod_peerfeedback/review',
    $renderer->review_context($cm, $pf, $rm, $users, $userid, $dir, $filter)
);
echo $renderer->branding_footer();
echo $OUTPUT->footer();
