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
 * The teacher's feedback to a student about the feedback that student wrote: the comments and grades on each
 * reviewed feedback, plus one overall comment the teacher chooses when to show.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_feedback {
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
    }

    /**
     * The stored overall comment for a student, shown or not.
     *
     * @param int $userid
     * @return \stdClass|null record with comment and shown
     */
    public function get_overall(int $userid): ?\stdClass {
        global $DB;
        return $DB->get_record('peerfeedback_overall', ['peerfeedbackid' => $this->pf->id, 'userid' => $userid]) ?: null;
    }

    /**
     * Save the overall comment without changing whether the student sees it. An empty comment removes it.
     *
     * @param int $userid
     * @param string $comment
     * @param int $teacherid
     */
    public function save_overall(int $userid, string $comment, int $teacherid): void {
        global $DB;
        $comment = trim($comment);
        $existing = $this->get_overall($userid);
        if ($comment === '') {
            if ($existing) {
                $DB->delete_records('peerfeedback_overall', ['id' => $existing->id]);
            }
            return;
        }
        $record = (object)['comment' => $comment, 'teacherid' => $teacherid, 'timemodified' => time()];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('peerfeedback_overall', $record);
        } else {
            $record->peerfeedbackid = $this->pf->id;
            $record->userid = $userid;
            $record->shown = 0;
            $DB->insert_record('peerfeedback_overall', $record);
        }
    }

    /**
     * Show or hide the overall comment. The student is notified the first time it becomes visible after being hidden.
     *
     * @param int $userid
     * @param bool $shown
     * @param int $teacherid
     */
    public function set_overall_shown(int $userid, bool $shown, int $teacherid): void {
        global $DB;
        $existing = $this->get_overall($userid);
        if (!$existing || (bool)$existing->shown === $shown) {
            return;
        }
        $DB->update_record('peerfeedback_overall', (object)['id' => $existing->id, 'shown' => (int)$shown,
            'teacherid' => $teacherid, 'timemodified' => time()]);
        if ($shown) {
            $this->notify($userid, $teacherid);
        }
    }

    /**
     * Whether a reviewed response carries something for its giver to read.
     *
     * @param \stdClass $response
     * @return bool
     */
    public function has_feedback_for_giver(\stdClass $response): bool {
        return (int)$response->status === constants::STATUS_REVIEWED &&
            ($response->teachercomment !== null || ($this->pf->grading && $response->grade !== null));
    }

    /**
     * What the student sees under "Teacher feedback": the shown overall comment and each reviewed feedback they
     * wrote that has a teacher comment (or a grade, when grading is on).
     *
     * @param int $userid
     * @return array ['overall' => string|null, 'items' => list of ['responseid', 'receivername', 'self', 'comment',
     *         'grade', 'answers' => [['question', 'value']]]]
     */
    public function for_student(int $userid): array {
        global $DB;
        $overall = $this->get_overall($userid);
        $result = ['overall' => $overall && $overall->shown ? $overall->comment : null, 'items' => []];
        $responses = array_filter(
            $DB->get_records(
                'peerfeedback_response',
                ['peerfeedbackid' => $this->pf->id, 'giverid' => $userid, 'status' => constants::STATUS_REVIEWED],
                'timereviewed DESC, id DESC'
            ),
            fn($r) => $this->has_feedback_for_giver($r)
        );
        if (!$responses) {
            return $result;
        }
        $questions = (new question_manager($this->pf))->get_questions();
        [$in, $params] = $DB->get_in_or_equal(array_keys($responses));
        $answers = [];
        foreach ($DB->get_records_select('peerfeedback_answer', "responseid $in", $params) as $a) {
            $answers[$a->responseid][$a->questionid] = $a->value;
        }
        $users = user_get_users_by_id(array_unique(array_column($responses, 'receiverid')));
        foreach ($responses as $r) {
            $list = [];
            foreach ($questions as $q) {
                $type = base::get($q->qtype);
                if ($type->is_answerable()) {
                    $list[] = ['question' => question_manager::title($q->questiontext),
                        'value' => $type->display_value($q->config, $answers[$r->id][$q->id] ?? null)];
                }
            }
            $result['items'][] = [
                'responseid' => (int)$r->id,
                'receivername' => fullname($users[$r->receiverid]),
                'self' => (int)$r->receiverid === $userid,
                'comment' => $r->teachercomment,
                'grade' => $this->pf->grading && $r->grade !== null ? (float)$r->grade : null,
                'answers' => $list,
            ];
        }
        return $result;
    }

    /**
     * Tell a student that the teacher has left feedback for them.
     *
     * @param int $userid
     * @param int $teacherid
     */
    public function notify(int $userid, int $teacherid): void {
        $url = new \moodle_url('/mod/peerfeedback/view.php', ['id' => $this->context->instanceid], 'pf-teacherfeedback');
        $name = format_string($this->pf->name, true, ['context' => $this->context]);
        $a = (object)['name' => $name, 'url' => $url->out(false)];
        $message = new \core\message\message();
        $message->component = 'mod_peerfeedback';
        $message->name = 'teacherfeedback';
        $message->userfrom = \core_user::get_user($teacherid) ?: \core_user::get_noreply_user();
        $message->userto = \core_user::get_user($userid);
        $message->subject = get_string('notifysubject', 'peerfeedback', $name);
        $message->fullmessage = get_string('notifybody', 'peerfeedback', $a);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '<p>' . s(get_string('notifysubject', 'peerfeedback', $name)) . '</p><p>' .
            \html_writer::link($url, get_string('notifylink', 'peerfeedback')) . '</p>';
        $message->smallmessage = get_string('notifysubject', 'peerfeedback', $name);
        $message->notification = 1;
        $message->contexturl = $url->out(false);
        $message->contexturlname = $name;
        $message->courseid = $this->pf->course;
        message_send($message);
    }
}
