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
 * Data generator for mod_peerfeedback.
 *
 * @package    mod_peerfeedback
 * @category   test
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_peerfeedback_generator extends testing_module_generator {

    #[\Override]
    public function create_instance($record = null, ?array $options = null) {
        $record = (object)(array)$record;
        $defaults = ['visibility' => 2, 'selfeval' => 0, 'grading' => 0, 'gradebook' => 1, 'grade' => 100,
            'timeopen' => 0, 'timedue' => 0, 'completionsubmit' => 0];
        foreach ($defaults as $k => $v) {
            if (!isset($record->$k)) {
                $record->$k = $v;
            }
        }
        return parent::create_instance($record, (array)$options);
    }

    /**
     * Create a question.
     *
     * @param int $pfid activity instance id
     * @param string $qtype question type
     * @param array $config type config
     * @param bool $required
     * @param string $text
     * @return stdClass the question record
     */
    public function create_question(int $pfid, string $qtype, array $config = [], bool $required = true,
            string $text = 'Q'): stdClass {
        global $DB;
        $sort = (int)$DB->get_field_sql('SELECT COALESCE(MAX(sortorder), 0) FROM {peerfeedback_question}
            WHERE peerfeedbackid = ?', [$pfid]) + 1;
        $rec = (object)['peerfeedbackid' => $pfid, 'qtype' => $qtype, 'questiontext' => $text,
            'required' => (int)$required, 'sortorder' => $sort, 'configdata' => json_encode((object)$config)];
        $rec->id = $DB->insert_record('peerfeedback_question', $rec);
        return $rec;
    }

    /**
     * Create a group with members (moving them out of any other group).
     *
     * @param int $pfid activity instance id
     * @param int[] $userids
     * @param string|null $name
     * @return stdClass the group record
     */
    public function create_group(int $pfid, array $userids, ?string $name = null): stdClass {
        global $DB;
        $count = $DB->count_records('peerfeedback_group', ['peerfeedbackid' => $pfid]);
        $rec = (object)['peerfeedbackid' => $pfid, 'name' => $name ?? 'Group ' . ($count + 1),
            'sortorder' => $count + 1];
        $rec->id = $DB->insert_record('peerfeedback_group', $rec);
        foreach ($userids as $uid) {
            $DB->delete_records('peerfeedback_member', ['peerfeedbackid' => $pfid, 'userid' => $uid]);
            $DB->insert_record('peerfeedback_member', ['peerfeedbackid' => $pfid, 'groupid' => $rec->id, 'userid' => $uid]);
        }
        return $rec;
    }

    /**
     * Behat: create a question from a table row (config as JSON).
     *
     * @param array $record peerfeedbackid, qtype, questiontext, config, required
     * @return stdClass
     */
    public function create_behat_question(array $record): stdClass {
        $config = json_decode($record['config'] ?? '{}', true) ?: [];
        return $this->create_question((int)$record['peerfeedbackid'], $record['qtype'], $config,
            (bool)($record['required'] ?? 1), $record['questiontext']);
    }

    /**
     * Behat: create a group from a table row (members as comma separated usernames).
     *
     * @param array $record peerfeedbackid, name, members
     * @return stdClass
     */
    public function create_behat_group(array $record): stdClass {
        global $DB;
        $ids = [];
        foreach (array_filter(array_map('trim', explode(',', $record['members']))) as $username) {
            $ids[] = (int)$DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        }
        return $this->create_group((int)$record['peerfeedbackid'], $ids, $record['name'] ?? null);
    }

    /**
     * Behat: submit a response (answers as JSON {"question text": "raw value"}).
     *
     * @param array $record peerfeedbackid, giver, receiver (usernames), answers
     * @return void
     */
    public function create_behat_response(array $record): void {
        global $DB;
        $pf = $DB->get_record('peerfeedback', ['id' => $record['peerfeedbackid']], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('peerfeedback', $pf->id, 0, false, MUST_EXIST);
        $rm = new \mod_peerfeedback\local\response_manager($pf, context_module::instance($cm->id));
        $byText = [];
        foreach ($DB->get_records('peerfeedback_question', ['peerfeedbackid' => $pf->id]) as $q) {
            $byText[$q->questiontext] = $q->id;
        }
        $raw = [];
        foreach (json_decode($record['answers'], true) as $text => $value) {
            $raw[$byText[$text]] = $value;
        }
        $giver = $DB->get_field('user', 'id', ['username' => $record['giver']], MUST_EXIST);
        $receiver = $DB->get_field('user', 'id', ['username' => $record['receiver']], MUST_EXIST);
        $errors = $rm->save((int)$giver, (int)$receiver, $raw, true);
        if ($errors) {
            throw new coding_exception('Invalid behat response: ' . json_encode($errors));
        }
    }
}
