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
 * Activity settings form.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Settings form for a peer feedback activity.
 */
class mod_peerfeedback_mod_form extends moodleform_mod {
    #[\Override]
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();

        $mform->addElement('header', 'availability', get_string('availability'));
        $mform->addElement('date_time_selector', 'timeopen', get_string('timeopen', 'peerfeedback'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'timedue', get_string('timedue', 'peerfeedback'), ['optional' => true]);

        $mform->addElement('header', 'feedbacksettings', get_string('feedbacksettings', 'peerfeedback'));
        $mform->setExpanded('feedbacksettings');
        $mform->addElement('select', 'visibility', get_string('visibility', 'peerfeedback'), [
            0 => get_string('visnone', 'peerfeedback'),
            1 => get_string('visgiven', 'peerfeedback'),
            2 => get_string('visreceived', 'peerfeedback'),
            3 => get_string('visall', 'peerfeedback'),
        ]);
        $mform->setDefault('visibility', 2);
        $mform->addHelpButton('visibility', 'visibility', 'peerfeedback');
        $mform->addElement('advcheckbox', 'selfeval', get_string('selfeval', 'peerfeedback'));
        $mform->addHelpButton('selfeval', 'selfeval', 'peerfeedback');

        $mform->addElement('header', 'gradingheader', get_string('teachergrading', 'peerfeedback'));
        $mform->addElement('advcheckbox', 'grading', get_string('grading', 'peerfeedback'));
        $mform->addHelpButton('grading', 'grading', 'peerfeedback');
        $mform->addElement('text', 'grade', get_string('maxgrade', 'peerfeedback'), ['size' => 5]);
        $mform->setType('grade', PARAM_INT);
        $mform->setDefault('grade', 100);
        $mform->disabledIf('grade', 'grading', 'notchecked');
        $mform->addElement('advcheckbox', 'gradebook', get_string('gradebook', 'peerfeedback'));
        $mform->setDefault('gradebook', 1);
        $mform->disabledIf('gradebook', 'grading', 'notchecked');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    #[\Override]
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!empty($data['timeopen']) && !empty($data['timedue']) && $data['timedue'] <= $data['timeopen']) {
            $errors['timedue'] = get_string('err_dueafteropen', 'peerfeedback');
        }
        if (!empty($data['grading']) && ($data['grade'] < 1 || $data['grade'] > 10000)) {
            $errors['grade'] = get_string('err_maxgrade', 'peerfeedback');
        }
        return $errors;
    }

    #[\Override]
    public function add_completion_rules() {
        $mform = $this->_form;
        $el = 'completionsubmit' . $this->get_suffix();
        $mform->addElement('advcheckbox', $el, '', get_string('completionsubmit', 'peerfeedback'));
        return [$el];
    }

    #[\Override]
    public function completion_rule_enabled($data) {
        return !empty($data['completionsubmit' . $this->get_suffix()]);
    }
}
