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

namespace mod_peerfeedback\local;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/lib.php');

use mod_peerfeedback\local\qtype\base;

/**
 * What a student may see, according to the activity's visibility setting.
 * Givers are never identified to students for feedback about someone (modes 2 and 3).
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_view {
    /**
     * Constructor.
     *
     * @param \stdClass $pf activity record
     */
    public function __construct(
        /** @var \stdClass activity record */
        protected \stdClass $pf,
    ) {
    }

    /**
     * Visible feedback items for a student.
     *
     * @param int $userid
     * @return array list of ['receivername', 'givername', 'answers' => [['question', 'value']], 'grade', 'teachercomment']
     */
    public function for_user(int $userid): array {
        global $DB;
        $mode = (int)$this->pf->visibility;
        $base = 'peerfeedbackid = :pfid AND status >= :status';
        $params = ['pfid' => $this->pf->id, 'status' => constants::STATUS_SUBMITTED];
        switch ($mode) {
            case constants::VIS_GIVEN:
                $where = "$base AND giverid = :uid";
                break;
            case constants::VIS_RECEIVED:
                $where = "$base AND receiverid = :uid";
                break;
            case constants::VIS_ALL:
                $groupid = $DB->get_field(
                    'peerfeedback_member',
                    'groupid',
                    ['peerfeedbackid' => $this->pf->id, 'userid' => $userid]
                );
                if (!$groupid) {
                    return [];
                }
                // Current members on both ends, so feedback survives regrouping into the same group.
                $members = "SELECT userid FROM {peerfeedback_member} WHERE peerfeedbackid = :pfid2 AND groupid = :gid";
                $where = "$base AND giverid IN ($members) AND receiverid IN (" .
                    str_replace([':pfid2', ':gid'], [':pfid3', ':gid2'], $members) . ")";
                $params += ['pfid2' => $this->pf->id, 'gid' => $groupid, 'pfid3' => $this->pf->id, 'gid2' => $groupid];
                break;
            default:
                return [];
        }
        $params['uid'] = $userid;
        $responses = $DB->get_records_select('peerfeedback_response', $where, $params, 'receiverid, timesubmitted, id');
        if (!$responses) {
            return [];
        }
        $questions = (new question_manager($this->pf))->get_questions();
        [$in, $inparams] = $DB->get_in_or_equal(array_keys($responses));
        $answers = [];
        foreach ($DB->get_records_select('peerfeedback_answer', "responseid $in", $inparams) as $a) {
            $answers[$a->responseid][$a->questionid] = $a->value;
        }
        $userids = array_unique(array_merge(array_column($responses, 'receiverid'), array_column($responses, 'giverid')));
        $users = user_get_users_by_id($userids);

        $items = [];
        foreach ($responses as $r) {
            $own = (int)$r->giverid === $userid;
            $showreview = $own && $this->pf->grading && (int)$r->status === constants::STATUS_REVIEWED;
            $list = [];
            foreach ($questions as $q) {
                $type = base::get($q->qtype);
                if ($type->is_answerable()) {
                    $list[] = ['question' => format_string($q->questiontext),
                        'value' => $type->display_value($q->config, $answers[$r->id][$q->id] ?? null)];
                }
            }
            $items[] = [
                'receivername' => $mode === constants::VIS_RECEIVED ? null : fullname($users[$r->receiverid]),
                'givername' => $mode === constants::VIS_GIVEN ? fullname($users[$r->giverid]) : null,
                'answers' => $list,
                'grade' => $showreview && $r->grade !== null ? (float)$r->grade : null,
                'teachercomment' => $showreview ? $r->teachercomment : null,
            ];
        }
        return $items;
    }
}
