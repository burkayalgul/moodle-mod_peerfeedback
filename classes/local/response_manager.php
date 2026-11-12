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

use mod_peerfeedback\local\qtype\base;

/**
 * Who a student evaluates, and saving/submitting their feedback.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_manager {

    /** @var group_manager */
    protected group_manager $groups;

    /**
     * Constructor.
     *
     * @param \stdClass $pf activity record
     * @param \context_module $context
     */
    public function __construct(
        /** @var \stdClass activity record */
        protected \stdClass $pf,
        /** @var \context_module module context */
        protected \context_module $context,
    ) {
        $this->groups = new group_manager($pf, $context);
    }

    /**
     * The people a user evaluates: other group members by last name, then themselves if self-evaluation is on.
     *
     * @param int $userid
     * @return \stdClass[] userid => user
     */
    public function get_peers(int $userid): array {
        $group = $this->groups->get_group_of($userid);
        if (!$group) {
            return [];
        }
        $eligible = $this->groups->eligible_users();
        $peers = [];
        foreach ($eligible as $uid => $user) {
            if ($uid != $userid && in_array((int)$uid, $group->members, true)) {
                $peers[$uid] = $user;
            }
        }
        if ($this->pf->selfeval && isset($eligible[$userid])) {
            $peers[$userid] = $eligible[$userid];
        }
        return $peers;
    }

    /**
     * One response with ->answers (questionid => value).
     *
     * @param int $giverid
     * @param int $receiverid
     * @return \stdClass|null
     */
    public function get_response(int $giverid, int $receiverid): ?\stdClass {
        global $DB;
        $r = $DB->get_record('peerfeedback_response',
            ['peerfeedbackid' => $this->pf->id, 'giverid' => $giverid, 'receiverid' => $receiverid]);
        if (!$r) {
            return null;
        }
        $r->answers = $DB->get_records_menu('peerfeedback_answer', ['responseid' => $r->id], '', 'questionid, value');
        return $r;
    }

    /**
     * Whether the activity accepts answers now.
     *
     * @param int|null $now
     * @return bool
     */
    public function is_open(?int $now = null): bool {
        $now = $now ?? time();
        return (empty($this->pf->timeopen) || $this->pf->timeopen <= $now)
            && (empty($this->pf->timedue) || $now <= $this->pf->timedue);
    }

    /**
     * Whether giver may write or change feedback about receiver.
     *
     * @param int $giverid
     * @param int $receiverid
     * @return bool
     */
    public function can_edit(int $giverid, int $receiverid): bool {
        global $DB;
        if (!$this->is_open() || !isset($this->get_peers($giverid)[$receiverid])) {
            return false;
        }
        $status = $DB->get_field('peerfeedback_response', 'status',
            ['peerfeedbackid' => $this->pf->id, 'giverid' => $giverid, 'receiverid' => $receiverid]);
        return $status === false || (int)$status !== constants::STATUS_REVIEWED;
    }

    /**
     * Validate and store answers. Required questions are enforced for both save and submit.
     *
     * @param int $giverid
     * @param int $receiverid
     * @param array $rawanswers questionid => raw input
     * @param bool $submit
     * @return array questionid => error string key; empty when stored
     */
    public function save(int $giverid, int $receiverid, array $rawanswers, bool $submit): array {
        global $DB;
        if (!$this->can_edit($giverid, $receiverid)) {
            throw new \moodle_exception('err_notallowed', 'peerfeedback');
        }
        $values = [];
        $errors = [];
        foreach ((new question_manager($this->pf))->get_questions() as $q) {
            $type = base::get($q->qtype);
            if (!$type->is_answerable()) {
                continue;
            }
            $value = $type->normalise($q->config, $rawanswers[$q->id] ?? null);
            if ($error = $type->validate($q->config, $value, (bool)$q->required)) {
                $errors[$q->id] = $error;
            }
            $values[$q->id] = $value;
        }
        if ($errors) {
            return $errors;
        }

        $now = time();
        $group = $this->groups->get_group_of($giverid);
        $transaction = $DB->start_delegated_transaction();
        $existing = $DB->get_record('peerfeedback_response',
            ['peerfeedbackid' => $this->pf->id, 'giverid' => $giverid, 'receiverid' => $receiverid]);
        $record = $existing ?: (object)['peerfeedbackid' => $this->pf->id, 'giverid' => $giverid,
            'receiverid' => $receiverid, 'status' => constants::STATUS_SAVED, 'timecreated' => $now, 'editedaftergrade' => 0];
        $record->groupid = $group ? $group->id : 0;
        $record->timemodified = $now;
        if ($submit) {
            $record->status = constants::STATUS_SUBMITTED;
            $record->timesubmitted = $now;
        }
        if ($existing && $existing->grade !== null) {
            $record->editedaftergrade = 1;
        }
        if ($existing) {
            $DB->update_record('peerfeedback_response', $record);
        } else {
            $record->id = $DB->insert_record('peerfeedback_response', $record);
        }
        $DB->delete_records('peerfeedback_answer', ['responseid' => $record->id]);
        foreach ($values as $qid => $value) {
            if ($value !== null) {
                $DB->insert_record('peerfeedback_answer', ['responseid' => $record->id, 'questionid' => $qid, 'value' => $value]);
            }
        }
        $transaction->allow_commit();

        if ($submit) {
            \mod_peerfeedback\event\response_submitted::create([
                'objectid' => $record->id, 'context' => $this->context, 'relateduserid' => $receiverid,
                'userid' => $giverid,
            ])->trigger();
        }
        return [];
    }

    /**
     * Submitted count against number of peers.
     *
     * @param int $userid
     * @return array ['done' => int, 'total' => int]
     */
    public function progress(int $userid): array {
        global $DB;
        $peers = $this->get_peers($userid);
        $done = 0;
        if ($peers) {
            [$in, $params] = $DB->get_in_or_equal(array_keys($peers), SQL_PARAMS_NAMED);
            $params += ['pfid' => $this->pf->id, 'giver' => $userid, 'status' => constants::STATUS_SUBMITTED];
            $done = $DB->count_records_select('peerfeedback_response',
                "peerfeedbackid = :pfid AND giverid = :giver AND status >= :status AND receiverid $in", $params);
        }
        return ['done' => $done, 'total' => count($peers)];
    }
}
