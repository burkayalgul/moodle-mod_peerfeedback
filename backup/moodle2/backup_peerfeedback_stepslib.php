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
 * Backup structure: the activity, its questions and groups always; memberships, responses and answers with user data.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_peerfeedback_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the XML tree.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $pf = new backup_nested_element('peerfeedback', ['id'], ['name', 'intro', 'introformat', 'timeopen', 'timedue',
            'visibility', 'selfeval', 'grading', 'gradebook', 'grade', 'completionsubmit', 'timecreated', 'timemodified']);
        $questions = new backup_nested_element('questions');
        $question = new backup_nested_element('question', ['id'], ['qtype', 'questiontext', 'required', 'sortorder',
            'configdata']);
        $groups = new backup_nested_element('groups');
        $group = new backup_nested_element('group', ['id'], ['name', 'sortorder']);
        $members = new backup_nested_element('members');
        $member = new backup_nested_element('member', ['id'], ['userid']);
        $responses = new backup_nested_element('responses');
        $response = new backup_nested_element('response', ['id'], ['giverid', 'receiverid', 'groupid', 'status', 'grade',
            'teachercomment', 'editedaftergrade', 'reviewerid', 'timecreated', 'timemodified', 'timesubmitted',
            'timereviewed']);
        $answers = new backup_nested_element('answers');
        $answer = new backup_nested_element('answer', ['id'], ['questionid', 'value']);

        $pf->add_child($questions);
        $questions->add_child($question);
        $pf->add_child($groups);
        $groups->add_child($group);
        $group->add_child($members);
        $members->add_child($member);
        $pf->add_child($responses);
        $responses->add_child($response);
        $response->add_child($answers);
        $answers->add_child($answer);

        $pf->set_source_table('peerfeedback', ['id' => backup::VAR_ACTIVITYID]);
        $question->set_source_table('peerfeedback_question', ['peerfeedbackid' => backup::VAR_PARENTID], 'sortorder ASC');
        $group->set_source_table('peerfeedback_group', ['peerfeedbackid' => backup::VAR_PARENTID], 'sortorder ASC');
        if ($userinfo) {
            $member->set_source_table('peerfeedback_member', ['groupid' => backup::VAR_PARENTID]);
            $response->set_source_table('peerfeedback_response', ['peerfeedbackid' => backup::VAR_PARENTID], 'id ASC');
            $answer->set_source_table('peerfeedback_answer', ['responseid' => backup::VAR_PARENTID]);
        }

        $member->annotate_ids('user', 'userid');
        $response->annotate_ids('user', 'giverid');
        $response->annotate_ids('user', 'receiverid');
        $response->annotate_ids('user', 'reviewerid');
        $pf->annotate_files('mod_peerfeedback', 'intro', null);

        return $this->prepare_activity_structure($pf);
    }
}
