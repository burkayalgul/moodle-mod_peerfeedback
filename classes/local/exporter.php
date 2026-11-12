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
 * Export rows of submitted feedback, organised by giver or by receiver.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter {
    /** @var \stdClass[] questions with config */
    protected array $questions;

    /**
     * Constructor.
     *
     * @param \stdClass $pf activity record
     */
    public function __construct(
        /** @var \stdClass activity record */
        protected \stdClass $pf,
    ) {
        $this->questions = (new question_manager($pf))->get_questions();
    }

    /**
     * Column headers.
     *
     * @return string[]
     */
    public function headers(): array {
        $headers = [get_string('giver', 'peerfeedback'), get_string('receiver', 'peerfeedback'),
            get_string('group', 'peerfeedback')];
        $n = 0;
        foreach ($this->questions as $q) {
            $type = base::get($q->qtype);
            if (!$type->is_answerable()) {
                continue;
            }
            $text = trim(preg_replace('/\s+/', ' ', question_manager::title($q->questiontext)));
            if (\core_text::strlen($text) > 40) {
                $text = rtrim(\core_text::substr($text, 0, 39)) . '…';
            }
            $headers = array_merge($headers, $type->export_columns($q->config, 'Q' . (++$n) . ': ' . $text));
        }
        return array_merge($headers, [get_string('status'), get_string('statussubmitted', 'peerfeedback'),
            get_string('teachergrade', 'peerfeedback'), get_string('teachercomment', 'peerfeedback')]);
    }

    /**
     * Rows of submitted feedback.
     *
     * @param string $by 'giver' or 'receiver' (primary sort)
     * @return \Generator of string[] matching headers()
     */
    public function rows(string $by): \Generator {
        global $DB;
        $responses = $DB->get_records_select(
            'peerfeedback_response',
            'peerfeedbackid = ? AND status >= ?',
            [$this->pf->id, constants::STATUS_SUBMITTED]
        );
        if (!$responses) {
            return;
        }
        $users = user_get_users_by_id(array_unique(array_merge(
            array_column($responses, 'giverid'),
            array_column($responses, 'receiverid')
        )));
        $groups = $DB->get_records_menu('peerfeedback_group', ['peerfeedbackid' => $this->pf->id], '', 'id, name');
        $name = fn($uid) => isset($users[$uid]) ? fullname($users[$uid]) : '';
        [$first, $second] = $by === 'receiver' ? ['receiverid', 'giverid'] : ['giverid', 'receiverid'];
        usort($responses, fn($a, $b) => [\core_text::strtolower($name($a->$first)), \core_text::strtolower($name($a->$second))]
            <=> [\core_text::strtolower($name($b->$first)), \core_text::strtolower($name($b->$second))]);

        foreach ($responses as $r) {
            $answers = $DB->get_records_menu('peerfeedback_answer', ['responseid' => $r->id], '', 'questionid, value');
            $row = [$name($r->giverid), $name($r->receiverid),
                format_string($r->groupname ?? ($groups[$r->groupid] ?? ''), true, ['escape' => false])];
            foreach ($this->questions as $q) {
                $type = base::get($q->qtype);
                if ($type->is_answerable()) {
                    $row = array_merge($row, $type->export_values($q->config, $answers[$q->id] ?? null));
                }
            }
            $row[] = get_string(
                (int)$r->status === constants::STATUS_REVIEWED ? 'statusreviewed' : 'statussubmitted',
                'peerfeedback'
            );
            $row[] = $r->timesubmitted ? userdate($r->timesubmitted, get_string('strftimedatetimeshort', 'langconfig')) : '';
            $row[] = $r->grade === null ? '' : format_float($r->grade, 2, false, true);
            $row[] = (string)($r->teachercomment ?? '');
            yield $row;
        }
    }
}
