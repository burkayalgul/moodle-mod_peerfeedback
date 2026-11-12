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
 * Restore structure step for a peer feedback activity.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_peerfeedback_activity_structure_step extends restore_activity_structure_step {
    /**
     * Paths to process.
     *
     * @return restore_path_element[]
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');
        $paths = [
            new restore_path_element('peerfeedback', '/activity/peerfeedback'),
            new restore_path_element('peerfeedback_question', '/activity/peerfeedback/questions/question'),
            new restore_path_element('peerfeedback_group', '/activity/peerfeedback/groups/group'),
        ];
        if ($userinfo) {
            $paths[] = new restore_path_element('peerfeedback_member', '/activity/peerfeedback/groups/group/members/member');
            $paths[] = new restore_path_element('peerfeedback_response', '/activity/peerfeedback/responses/response');
            $paths[] = new restore_path_element(
                'peerfeedback_answer',
                '/activity/peerfeedback/responses/response/answers/answer'
            );
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore the activity record.
     *
     * @param array $data
     */
    protected function process_peerfeedback($data) {
        global $DB;
        $data = (object)$data;
        $data->course = $this->get_courseid();
        $data->timeopen = $this->apply_date_offset($data->timeopen);
        $data->timedue = $this->apply_date_offset($data->timedue);
        $newid = $DB->insert_record('peerfeedback', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Restore a question.
     *
     * @param array $data
     */
    protected function process_peerfeedback_question($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->peerfeedbackid = $this->get_new_parentid('peerfeedback');
        $this->set_mapping('peerfeedback_question', $oldid, $DB->insert_record('peerfeedback_question', $data));
    }

    /**
     * Restore a group.
     *
     * @param array $data
     */
    protected function process_peerfeedback_group($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->peerfeedbackid = $this->get_new_parentid('peerfeedback');
        $this->set_mapping('peerfeedback_group', $oldid, $DB->insert_record('peerfeedback_group', $data));
    }

    /**
     * Restore a membership; skipped when the user is not restored.
     *
     * @param array $data
     */
    protected function process_peerfeedback_member($data) {
        global $DB;
        $data = (object)$data;
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!$data->userid) {
            return;
        }
        $data->peerfeedbackid = $this->get_new_parentid('peerfeedback');
        $data->groupid = $this->get_new_parentid('peerfeedback_group');
        $DB->insert_record('peerfeedback_member', $data);
    }

    /**
     * Restore a response; skipped when giver or receiver is not restored.
     *
     * @param array $data
     */
    protected function process_peerfeedback_response($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->giverid = $this->get_mappingid('user', $data->giverid);
        $data->receiverid = $this->get_mappingid('user', $data->receiverid);
        if (!$data->giverid || !$data->receiverid) {
            return;
        }
        $data->peerfeedbackid = $this->get_new_parentid('peerfeedback');
        $data->groupid = $data->groupid ? (int)$this->get_mappingid('peerfeedback_group', $data->groupid) : 0;
        $data->reviewerid = $data->reviewerid ? ($this->get_mappingid('user', $data->reviewerid) ?: null) : null;
        $this->set_mapping('peerfeedback_response', $oldid, $DB->insert_record('peerfeedback_response', $data));
    }

    /**
     * Restore an answer; skipped when its response or question was not restored.
     *
     * @param array $data
     */
    protected function process_peerfeedback_answer($data) {
        global $DB;
        $data = (object)$data;
        $data->responseid = $this->get_new_parentid('peerfeedback_response');
        $data->questionid = $this->get_mappingid('peerfeedback_question', $data->questionid);
        if (!$data->responseid || !$data->questionid) {
            return;
        }
        $DB->insert_record('peerfeedback_answer', $data);
    }

    /**
     * Restore intro files.
     */
    protected function after_execute() {
        $this->add_related_files('mod_peerfeedback', 'intro', null);
    }
}
