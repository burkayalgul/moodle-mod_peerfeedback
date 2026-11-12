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
 * Group management page.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_peerfeedback\local\group_manager;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'peerfeedback');
$pf = $DB->get_record('peerfeedback', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/peerfeedback:managegroups', $context);

$baseurl = new moodle_url('/mod/peerfeedback/groups.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($pf->name) . ': ' . get_string('groups', 'peerfeedback'));
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->disable();

$gm = new group_manager($pf, $context);
$renderer = $PAGE->get_renderer('mod_peerfeedback');

if ($action === 'pair' && !optional_param('confirm', 0, PARAM_BOOL)) {
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('confirmpair', 'peerfeedback'),
        new moodle_url($baseurl, ['action' => 'pair', 'confirm' => 1, 'sesskey' => sesskey()]), $baseurl);
    echo $OUTPUT->footer();
    exit;
}

if ($action !== '') {
    require_sesskey();
    $before = $DB->count_records('peerfeedback_response', ['peerfeedbackid' => $pf->id, 'status' => 0]);
    switch ($action) {
        case 'assign':
            $userids = optional_param_array('userids', [], PARAM_INT);
            $groupid = required_param('groupid', PARAM_INT);
            if ($groupid === 0) {
                $groupid = $gm->create_group();
            }
            if ($userids) {
                $gm->assign($userids, $groupid);
            } else {
                \core\notification::warning(get_string('noneselected', 'peerfeedback'));
            }
            break;
        case 'creategroup':
            $gm->create_group(optional_param('name', '', PARAM_TEXT));
            break;
        case 'rename':
            $gm->rename_group(required_param('groupid', PARAM_INT), required_param('name', PARAM_TEXT));
            break;
        case 'deletegroup':
            $gm->delete_group(required_param('groupid', PARAM_INT));
            break;
        case 'unassign':
            $gm->unassign(required_param('userid', PARAM_INT));
            break;
        case 'random':
            try {
                $gm->random_assign(required_param('mode', PARAM_ALPHA), required_param('value', PARAM_INT),
                    optional_param('reshuffle', 0, PARAM_BOOL));
            } catch (moodle_exception $e) {
                \core\notification::error($e->getMessage());
            }
            break;
        case 'pair':
            $gm->pair_all();
            break;
    }
    if (!in_array($action, ['creategroup', 'rename'])) {
        // Group changes alter who must be evaluated, so completion is recomputed.
        peerfeedback_update_completion($pf, array_keys($gm->eligible_users()));
    }
    $discarded = $before - $DB->count_records('peerfeedback_response', ['peerfeedbackid' => $pf->id, 'status' => 0]);
    if ($discarded > 0) {
        \core\notification::warning(get_string('draftsdiscarded', 'peerfeedback', $discarded));
    }
    redirect($baseurl);
}

$PAGE->requires->js_amd_inline("
    document.querySelectorAll('[data-pf-selectall]').forEach(function(all) {
        all.addEventListener('change', function() {
            document.querySelectorAll('[data-pf-user]').forEach(function(box) { box.checked = all.checked; });
        });
    });
");

echo $OUTPUT->header();
echo $renderer->tabs($cm, 'groups');
echo $renderer->render_from_template('mod_peerfeedback/groups', $renderer->groups_context($cm, $gm));
echo $renderer->branding_footer();
echo $OUTPUT->footer();
