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

namespace mod_peerfeedback\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API provider: group membership, feedback given and received, and teacher reviews.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** SQL joining a module context to the activity. */
    private const CTXJOIN = "JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :ctxlevel
                             JOIN {modules} m ON m.id = cm.module AND m.name = 'peerfeedback'
                             JOIN {peerfeedback} pf ON pf.id = cm.instance";

    /**
     * Describe stored personal data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('peerfeedback_member', [
            'userid' => 'privacy:metadata:peerfeedback_member:userid',
            'groupid' => 'privacy:metadata:peerfeedback_member:groupid',
        ], 'privacy:metadata:peerfeedback_member');
        $collection->add_database_table('peerfeedback_response', [
            'giverid' => 'privacy:metadata:peerfeedback_response:giverid',
            'receiverid' => 'privacy:metadata:peerfeedback_response:receiverid',
            'status' => 'privacy:metadata:peerfeedback_response:status',
            'grade' => 'privacy:metadata:peerfeedback_response:grade',
            'teachercomment' => 'privacy:metadata:peerfeedback_response:teachercomment',
            'reviewerid' => 'privacy:metadata:peerfeedback_response:reviewerid',
            'timesubmitted' => 'privacy:metadata:peerfeedback_response:timesubmitted',
            'timemodified' => 'privacy:metadata:peerfeedback_response:timemodified',
        ], 'privacy:metadata:peerfeedback_response');
        $collection->add_database_table('peerfeedback_answer', [
            'value' => 'privacy:metadata:peerfeedback_answer:value',
        ], 'privacy:metadata:peerfeedback_answer');
        $collection->add_database_table('peerfeedback_overall', [
            'userid' => 'privacy:metadata:peerfeedback_overall:userid',
            'comment' => 'privacy:metadata:peerfeedback_overall:comment',
            'teacherid' => 'privacy:metadata:peerfeedback_overall:teacherid',
            'timemodified' => 'privacy:metadata:peerfeedback_overall:timemodified',
        ], 'privacy:metadata:peerfeedback_overall');
        $collection->add_subsystem_link('core_grades', [], 'privacy:metadata:core_grades');
        return $collection;
    }

    /**
     * Contexts holding data about a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        $base = "SELECT ctx.id FROM {context} ctx " . self::CTXJOIN;
        $list->add_from_sql(
            "$base JOIN {peerfeedback_member} mb ON mb.peerfeedbackid = pf.id WHERE mb.userid = :u",
            ['ctxlevel' => CONTEXT_MODULE, 'u' => $userid]
        );
        $list->add_from_sql(
            "$base JOIN {peerfeedback_response} r ON r.peerfeedbackid = pf.id
            WHERE r.giverid = :u1 OR r.receiverid = :u2 OR r.reviewerid = :u3",
            ['ctxlevel' => CONTEXT_MODULE, 'u1' => $userid, 'u2' => $userid, 'u3' => $userid]
        );
        $list->add_from_sql(
            "$base JOIN {peerfeedback_overall} o ON o.peerfeedbackid = pf.id WHERE o.userid = :u1 OR o.teacherid = :u2",
            ['ctxlevel' => CONTEXT_MODULE, 'u1' => $userid, 'u2' => $userid]
        );
        return $list;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $params = ['cmid' => $context->instanceid, 'modname' => 'peerfeedback'];
        $pfsql = "SELECT cm.instance FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                   WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', "SELECT userid FROM {peerfeedback_member} WHERE peerfeedbackid = ($pfsql)", $params);
        foreach (['giverid', 'receiverid', 'reviewerid'] as $f) {
            $userlist->add_from_sql($f, "SELECT $f FROM {peerfeedback_response} WHERE peerfeedbackid = ($pfsql)", $params);
        }
        foreach (['userid', 'teacherid'] as $f) {
            $userlist->add_from_sql($f, "SELECT $f FROM {peerfeedback_overall} WHERE peerfeedbackid = ($pfsql)", $params);
        }
    }

    /**
     * Export a user's data: group, feedback given (with teacher grade/comment), feedback received (giver hidden),
     * and reviews they wrote as a teacher.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $pf = self::pf_for_context($context);
            if (!$pf) {
                continue;
            }
            $data = helper::get_context_data($context, $contextlist->get_user());
            $group = $DB->get_field_sql(
                "SELECT g.name FROM {peerfeedback_member} mb
                JOIN {peerfeedback_group} g ON g.id = mb.groupid WHERE mb.peerfeedbackid = ? AND mb.userid = ?",
                [$pf->id, $userid]
            );
            if ($group !== false) {
                $data->group = format_string($group);
            }
            writer::with_context($context)->export_data([], $data);
            helper::export_context_files($context, $contextlist->get_user());

            $questions = $DB->get_records('peerfeedback_question', ['peerfeedbackid' => $pf->id], 'sortorder');
            $given = [];
            foreach ($DB->get_records('peerfeedback_response', ['peerfeedbackid' => $pf->id, 'giverid' => $userid], 'id') as $r) {
                $item = self::response_export($r, $questions);
                $item->receiver = fullname(\core_user::get_user($r->receiverid));
                $item->teachergrade = $r->grade === null ? null : (float)$r->grade;
                $item->teachercomment = $r->teachercomment;
                $given[] = $item;
            }
            if ($given) {
                writer::with_context($context)->export_data(
                    [get_string('privacy:given', 'peerfeedback')],
                    (object)['feedback' => $given]
                );
            }
            $received = [];
            foreach (
                $DB->get_records_select(
                    'peerfeedback_response',
                    'peerfeedbackid = ? AND receiverid = ? AND status >= 1',
                    [$pf->id, $userid],
                    'id'
                ) as $r
            ) {
                $received[] = self::response_export($r, $questions);
            }
            if ($received) {
                writer::with_context($context)->export_data(
                    [get_string('privacy:received', 'peerfeedback')],
                    (object)['feedback' => $received]
                );
            }
            $reviews = [];
            $reviewed = $DB->get_records('peerfeedback_response', ['peerfeedbackid' => $pf->id, 'reviewerid' => $userid], 'id');
            foreach ($reviewed as $r) {
                $reviews[] = (object)['grade' => $r->grade === null ? null : (float)$r->grade,
                    'comment' => $r->teachercomment,
                    'timereviewed' => $r->timereviewed ? transform::datetime($r->timereviewed) : null];
            }
            $overall = $DB->get_record('peerfeedback_overall', ['peerfeedbackid' => $pf->id, 'userid' => $userid]);
            if ($overall) {
                writer::with_context($context)->export_data(
                    [get_string('privacy:overall', 'peerfeedback')],
                    (object)['comment' => $overall->comment, 'shown' => transform::yesno($overall->shown),
                        'timemodified' => transform::datetime($overall->timemodified)]
                );
            }
            foreach ($DB->get_records('peerfeedback_overall', ['peerfeedbackid' => $pf->id, 'teacherid' => $userid]) as $o) {
                $reviews[] = (object)['grade' => null, 'comment' => $o->comment,
                    'timereviewed' => transform::datetime($o->timemodified)];
            }
            if ($reviews) {
                writer::with_context($context)->export_data(
                    [get_string('privacy:reviews', 'peerfeedback')],
                    (object)['reviews' => $reviews]
                );
            }
        }
    }

    /**
     * Delete everything in one activity.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if (!$pf = self::pf_for_context($context)) {
            return;
        }
        $DB->delete_records_select(
            'peerfeedback_answer',
            'responseid IN (SELECT id FROM {peerfeedback_response} WHERE peerfeedbackid = ?)',
            [$pf->id]
        );
        $DB->delete_records('peerfeedback_response', ['peerfeedbackid' => $pf->id]);
        $DB->delete_records('peerfeedback_overall', ['peerfeedbackid' => $pf->id]);
        $DB->delete_records('peerfeedback_member', ['peerfeedbackid' => $pf->id]);
    }

    /**
     * Delete one user's data in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_users($context, [(int)$contextlist->get_user()->id]);
        }
    }

    /**
     * Delete several users' data in one context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        self::delete_users($userlist->get_context(), $userlist->get_userids());
    }

    /**
     * Remove memberships and feedback given or received by the users; detach them as reviewers.
     * Feedback the deleted users received is removed too, so other givers' averages are recomputed.
     *
     * @param \context $context
     * @param int[] $userids
     */
    private static function delete_users(\context $context, array $userids): void {
        global $DB, $CFG;
        if (!$userids || !$pf = self::pf_for_context($context)) {
            return;
        }
        [$in1, $p1] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'g');
        [$in2, $p2] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'r');
        $where = "peerfeedbackid = :pfid AND (giverid $in1 OR receiverid $in2)";
        $params = ['pfid' => $pf->id] + $p1 + $p2;
        $affected = $DB->get_fieldset_select('peerfeedback_response', 'DISTINCT giverid', $where, $params);
        $DB->delete_records_select(
            'peerfeedback_answer',
            "responseid IN (SELECT id FROM {peerfeedback_response} WHERE $where)",
            $params
        );
        $DB->delete_records_select('peerfeedback_response', $where, $params);
        [$in, $p] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('peerfeedback_member', "peerfeedbackid = :pfid AND userid $in", ['pfid' => $pf->id] + $p);
        $DB->delete_records_select('peerfeedback_overall', "peerfeedbackid = :pfid AND userid $in", ['pfid' => $pf->id] + $p);
        $DB->set_field_select(
            'peerfeedback_overall',
            'teacherid',
            null,
            "peerfeedbackid = :pfid AND teacherid $in",
            ['pfid' => $pf->id] + $p
        );
        $DB->set_field_select(
            'peerfeedback_response',
            'reviewerid',
            null,
            "peerfeedbackid = :pfid AND reviewerid $in",
            ['pfid' => $pf->id] + $p
        );
        require_once($CFG->dirroot . '/mod/peerfeedback/lib.php');
        foreach (array_diff($affected, $userids) as $giverid) {
            peerfeedback_update_grades($pf, (int)$giverid);
        }
    }

    /**
     * Answers of one response in question order.
     *
     * @param \stdClass $r
     * @param \stdClass[] $questions
     * @return \stdClass
     */
    private static function response_export(\stdClass $r, array $questions): \stdClass {
        global $DB;
        $answers = $DB->get_records_menu('peerfeedback_answer', ['responseid' => $r->id], '', 'questionid, value');
        $out = [];
        foreach ($questions as $q) {
            if (array_key_exists($q->id, $answers)) {
                $out[] = (object)['question' => format_string($q->questiontext), 'value' => $answers[$q->id]];
            }
        }
        return (object)[
            'status' => get_string(
                $r->status >= 2 ? 'statusreviewed' : ($r->status >= 1 ? 'statussubmitted' : 'statussaved'),
                'peerfeedback'
            ),
            'timesubmitted' => $r->timesubmitted ? transform::datetime($r->timesubmitted) : null,
            'answers' => $out,
        ];
    }

    /**
     * Activity record for a module context, or null when it is not a peer feedback activity.
     *
     * @param \context $context
     * @return \stdClass|null
     */
    private static function pf_for_context(\context $context): ?\stdClass {
        global $DB;
        if (!$context instanceof \context_module) {
            return null;
        }
        $pf = $DB->get_record_sql("SELECT pf.* FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module AND m.name = 'peerfeedback'
            JOIN {peerfeedback} pf ON pf.id = cm.instance WHERE cm.id = ?", [$context->instanceid]);
        return $pf ?: null;
    }
}
