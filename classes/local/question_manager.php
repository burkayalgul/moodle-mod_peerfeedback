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
 * Create, edit, order and delete the questions of one activity.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_manager {

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
     * Questions ordered by sortorder, with decoded ->config.
     *
     * @return \stdClass[] keyed by id
     */
    public function get_questions(): array {
        global $DB;
        $questions = $DB->get_records('peerfeedback_question', ['peerfeedbackid' => $this->pf->id], 'sortorder ASC, id ASC');
        foreach ($questions as $q) {
            $q->config = json_decode($q->configdata ?? '{}', true) ?: [];
        }
        return $questions;
    }

    /**
     * Whether any feedback has been submitted in this activity.
     *
     * @return bool
     */
    public function has_submissions(): bool {
        global $DB;
        return $DB->record_exists_select('peerfeedback_response', 'peerfeedbackid = ? AND status >= ?',
            [$this->pf->id, constants::STATUS_SUBMITTED]);
    }

    /**
     * Add a question at the end.
     *
     * @param string $qtype
     * @param string $text
     * @param bool $required
     * @param array $config
     * @return int new question id
     */
    public function add(string $qtype, string $text, bool $required, array $config): int {
        global $DB;
        $type = base::get($qtype);
        if ($error = $type->validate_config($config)) {
            throw new \moodle_exception($error, 'peerfeedback');
        }
        $sort = (int)$DB->get_field_sql('SELECT COALESCE(MAX(sortorder), 0) FROM {peerfeedback_question}
            WHERE peerfeedbackid = ?', [$this->pf->id]) + 1;
        return $DB->insert_record('peerfeedback_question', (object)[
            'peerfeedbackid' => $this->pf->id, 'qtype' => $qtype, 'questiontext' => $text,
            'required' => $type->is_answerable() ? (int)$required : 0, 'sortorder' => $sort,
            'configdata' => json_encode((object)$config),
        ]);
    }

    /**
     * Update text, required flag and config. The type never changes.
     *
     * @param int $questionid
     * @param string $text
     * @param bool $required
     * @param array $config
     */
    public function update(int $questionid, string $text, bool $required, array $config): void {
        global $DB;
        $q = $this->get_question($questionid);
        $type = base::get($q->qtype);
        if ($error = $type->validate_config($config)) {
            throw new \moodle_exception($error, 'peerfeedback');
        }
        if ($this->has_submissions() && !$type->config_change_allowed($q->config, $config)) {
            throw new \moodle_exception('err_configlocked', 'peerfeedback');
        }
        $DB->update_record('peerfeedback_question', (object)[
            'id' => $q->id, 'questiontext' => $text,
            'required' => $type->is_answerable() ? (int)$required : 0,
            'configdata' => json_encode((object)$config),
        ]);
    }

    /**
     * Delete a question (only before any submission).
     *
     * @param int $questionid
     */
    public function delete(int $questionid): void {
        global $DB;
        $q = $this->get_question($questionid);
        if ($this->has_submissions()) {
            throw new \moodle_exception('err_deletelocked', 'peerfeedback');
        }
        $DB->delete_records('peerfeedback_answer', ['questionid' => $q->id]);
        $DB->delete_records('peerfeedback_question', ['id' => $q->id]);
        $this->renumber();
    }

    /**
     * Move a question up (-1) or down (+1).
     *
     * @param int $questionid
     * @param int $direction
     */
    public function move(int $questionid, int $direction): void {
        global $DB;
        $ids = array_map('intval', array_keys($this->get_questions()));
        $pos = array_search($questionid, $ids, true);
        $target = $pos === false ? false : $pos + ($direction < 0 ? -1 : 1);
        if ($pos === false || $target < 0 || $target >= count($ids)) {
            return;
        }
        [$ids[$pos], $ids[$target]] = [$ids[$target], $ids[$pos]];
        foreach ($ids as $i => $id) {
            $DB->set_field('peerfeedback_question', 'sortorder', $i + 1, ['id' => $id]);
        }
    }

    /**
     * Fetch one question of this activity.
     *
     * @param int $questionid
     * @return \stdClass
     */
    public function get_question(int $questionid): \stdClass {
        $questions = $this->get_questions();
        if (!isset($questions[$questionid])) {
            throw new \moodle_exception('invalidrecord', 'error', '', 'peerfeedback_question');
        }
        return $questions[$questionid];
    }

    /**
     * Rewrite sortorder as 1..n.
     */
    protected function renumber(): void {
        global $DB;
        $i = 0;
        foreach (array_keys($this->get_questions()) as $id) {
            $DB->set_field('peerfeedback_question', 'sortorder', ++$i, ['id' => $id]);
        }
    }
}
