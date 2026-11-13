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

$confirmed = optional_param('confirm', 0, PARAM_BOOL);
if ($action !== '') {
    // Every action, including the confirmation step, must come from this page's own forms.
    require_sesskey();
}
// Ticked students, or the list carried through the confirmation page.
$selected = array_map('intval', optional_param_array('userids', [], PARAM_INT));
if (!$selected && ($list = optional_param('useridlist', '', PARAM_SEQUENCE)) !== '') {
    $selected = array_map('intval', explode(',', $list));
}
$selected = array_values(array_intersect($selected, array_map('intval', array_keys($gm->eligible_users()))));
if ($action !== '' && !$confirmed) {
    // Warn before group changes that would throw away students' saved drafts, naming exactly what will change.
    $after = $gm->membership();
    $params = ['action' => $action];
    $groupnames = array_map(fn($g) => format_string($g->name), $gm->get_groups());
    $what = '';
    switch ($action) {
        case 'assign':
            $userids = array_intersect(
                array_map('intval', optional_param_array('userids', [], PARAM_INT)),
                array_map('intval', array_keys($gm->eligible_users()))
            );
            $groupid = optional_param('groupid', 0, PARAM_INT);
            foreach ($userids as $uid) {
                $after[$uid] = $groupid ?: -1;
            }
            $params += ['groupid' => $groupid, 'useridlist' => implode(',', $userids)];
            $what = get_string('confirmassign', 'peerfeedback', ['count' => count($userids),
                'group' => $groupnames[$groupid] ?? get_string('newgroup', 'peerfeedback')]);
            break;
        case 'deletegroup':
            $groupid = required_param('groupid', PARAM_INT);
            $after = array_filter($after, fn($g) => $g !== $groupid);
            $params['groupid'] = $groupid;
            $what = get_string('confirmdeletegroup', 'peerfeedback', $groupnames[$groupid] ?? '');
            break;
        case 'unassign':
            $userid = required_param('userid', PARAM_INT);
            unset($after[$userid]);
            $params['userid'] = $userid;
            $eligible = $gm->eligible_users();
            $what = get_string('confirmunassign', 'peerfeedback', isset($eligible[$userid]) ? s(fullname($eligible[$userid])) : '');
            break;
        case 'random':
            if (optional_param('reshuffle', 0, PARAM_BOOL)) {
                $after = [];
                $what = get_string('confirmreshuffle', 'peerfeedback');
            }
            $params += ['mode' => optional_param('mode', 'groups', PARAM_ALPHA), 'value' => optional_param('value', 0, PARAM_INT),
                'reshuffle' => optional_param('reshuffle', 0, PARAM_BOOL)];
            break;
        case 'pair':
        case 'alone':
            if ($selected) {
                // Each ticked student leaves their group; count drafts as if all of them were split up.
                foreach ($selected as $i => $uid) {
                    $after[$uid] = -1 - $i;
                }
                $params['useridlist'] = implode(',', $selected);
                $what = get_string('confirm' . $action . 'selected', 'peerfeedback', count($selected));
            } else {
                $after = [];
            }
            break;
    }
    $lost = in_array($action, ['assign', 'deletegroup', 'unassign', 'random', 'pair', 'alone'], true)
        ? $gm->drafts_lost($after) : 0;
    $wholeclass = in_array($action, ['pair', 'alone'], true) && !$selected;
    if ($lost > 0 || $wholeclass) {
        $message = $wholeclass ? get_string('confirm' . $action, 'peerfeedback') : $what;
        if ($lost > 0) {
            $message = trim($message . ' ' . get_string('confirmdiscard', 'peerfeedback', $lost));
        }
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            $message,
            new moodle_url($baseurl, $params + ['confirm' => 1, 'sesskey' => sesskey()]),
            $baseurl
        );
        echo $OUTPUT->footer();
        exit;
    }
}

if ($action !== '') {
    require_sesskey();
    $before = $DB->count_records('peerfeedback_response', ['peerfeedbackid' => $pf->id, 'status' => 0]);
    switch ($action) {
        case 'assign':
            $userids = optional_param_array('userids', [], PARAM_INT);
            if (!$userids && ($list = optional_param('useridlist', '', PARAM_SEQUENCE)) !== '') {
                // Coming back from the confirmation page, which carries the ticked users as a list.
                $userids = explode(',', $list);
            }
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
                $gm->random_assign(
                    required_param('mode', PARAM_ALPHA),
                    required_param('value', PARAM_INT),
                    optional_param('reshuffle', 0, PARAM_BOOL)
                );
            } catch (moodle_exception $e) {
                \core\notification::error($e->getMessage());
            }
            break;
        case 'pair':
            $selected ? $gm->pair_selected($selected) : $gm->pair_all();
            break;
        case 'alone':
            $selected ? $gm->alone_selected($selected) : $gm->alone_all();
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
            relabel();
        });
    });
    // Pair and Alone act on the ticked students when there are any, otherwise on everyone.
    function relabel() {
        var some = document.querySelector('[data-pf-user]:checked') !== null;
        document.querySelectorAll('[data-pf-label-all]').forEach(function(btn) {
            btn.textContent = some ? btn.dataset.pfLabelSome : btn.dataset.pfLabelAll;
        });
    }
    document.querySelectorAll('[data-pf-user]').forEach(function(box) { box.addEventListener('change', relabel); });
    relabel();
");

echo $OUTPUT->header();
echo $renderer->tabs($cm, 'groups');
echo $renderer->render_from_template('mod_peerfeedback/groups', $renderer->groups_context($cm, $gm));
echo $renderer->branding_footer();
echo $OUTPUT->footer();
