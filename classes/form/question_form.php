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

namespace mod_peerfeedback\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use mod_peerfeedback\local\qtype\base;

/**
 * Form to add or edit one question. Custom data: qtype, locked (bool).
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_form extends \moodleform {
    #[\Override]
    protected function definition() {
        $mform = $this->_form;
        $qtype = $this->_customdata['qtype'];
        $type = base::get($qtype);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'qid');
        $mform->setType('qid', PARAM_INT);
        $mform->addElement('hidden', 'qtype', $qtype);
        $mform->setType('qtype', PARAM_ALPHA);
        $mform->addElement('hidden', 'action', 'save');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement('static', 'typename', get_string('questiontype', 'peerfeedback'), base::all_types()[$qtype]);
        $label = $qtype === 'heading' ? 'headingtext' : 'questiontext';
        $mform->addElement('textarea', 'questiontext', get_string($label, 'peerfeedback'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('questiontext', PARAM_TEXT);
        $mform->addRule('questiontext', null, 'required', null, 'client');

        if ($type->is_answerable()) {
            $mform->addElement('advcheckbox', 'required', get_string('requiredquestion', 'peerfeedback'));
            $mform->setDefault('required', 1);
        }

        $scale = array_combine(range(2, 10), range(2, 10));
        switch ($qtype) {
            case 'singlechoice':
            case 'multichoice':
                $mform->addElement('textarea', 'options', get_string('optionslines', 'peerfeedback'), ['rows' => 5, 'cols' => 40]);
                $mform->setType('options', PARAM_TEXT);
                $mform->addRule('options', null, 'required', null, 'client');
                break;
            case 'rating':
                $mform->addElement('select', 'max', get_string('scalemax', 'peerfeedback'), $scale);
                $mform->setDefault('max', 5);
                $mform->addElement('text', 'minlabel', get_string('minlabel', 'peerfeedback'));
                $mform->setType('minlabel', PARAM_TEXT);
                $mform->addElement('text', 'maxlabel', get_string('maxlabel', 'peerfeedback'));
                $mform->setType('maxlabel', PARAM_TEXT);
                break;
            case 'number':
                $mform->addElement('text', 'min', get_string('minvalue', 'peerfeedback'));
                $mform->setType('min', PARAM_RAW_TRIMMED);
                $mform->addElement('text', 'max', get_string('maxvalue', 'peerfeedback'));
                $mform->setType('max', PARAM_RAW_TRIMMED);
                break;
            case 'grid':
                $mform->addElement(
                    'textarea',
                    'criteria',
                    get_string('criterialines', 'peerfeedback'),
                    ['rows' => 5, 'cols' => 40]
                );
                $mform->setType('criteria', PARAM_TEXT);
                $mform->addRule('criteria', null, 'required', null, 'client');
                $mform->addElement('select', 'max', get_string('scalemax', 'peerfeedback'), $scale);
                $mform->setDefault('max', 5);
                break;
        }
        if (!empty($this->_customdata['locked'])) {
            $mform->addElement('static', 'lockednote', '', get_string('questionlockednote', 'peerfeedback'));
        }
        $this->add_action_buttons();
    }

    #[\Override]
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $error = base::get($data['qtype'])->validate_config(self::config_from_data((object)$data));
        if ($error) {
            $field = match ($data['qtype']) {
                'singlechoice', 'multichoice' => 'options',
                'grid' => 'criteria',
                default => 'max',
            };
            $errors[$field] = get_string($error, 'peerfeedback');
        }
        return $errors;
    }

    /**
     * Build the type config array from submitted form data.
     *
     * @param \stdClass $data
     * @return array
     */
    public static function config_from_data(\stdClass $data): array {
        $lines = fn(string $s) => array_values(array_filter(array_map('trim', preg_split('/\R/', $s)), 'strlen'));
        return match ($data->qtype) {
            'singlechoice', 'multichoice' => ['options' => $lines($data->options ?? '')],
            'rating' => ['max' => (int)($data->max ?? 5), 'minlabel' => trim($data->minlabel ?? ''),
                'maxlabel' => trim($data->maxlabel ?? '')],
            'number' => ['min' => ($data->min ?? '') === '' ? null : (float)$data->min,
                'max' => ($data->max ?? '') === '' ? null : (float)$data->max],
            'grid' => ['criteria' => $lines($data->criteria ?? ''), 'max' => (int)($data->max ?? 5)],
            default => [],
        };
    }

    /**
     * Form defaults from a stored question.
     *
     * @param \stdClass $q question with ->config
     * @return array
     */
    public static function data_from_question(\stdClass $q): array {
        $c = $q->config;
        $d = ['qid' => $q->id, 'questiontext' => $q->questiontext, 'required' => $q->required];
        if (isset($c['options'])) {
            $d['options'] = implode("\n", $c['options']);
        }
        if (isset($c['criteria'])) {
            $d['criteria'] = implode("\n", $c['criteria']);
        }
        foreach (['max', 'min', 'minlabel', 'maxlabel'] as $k) {
            if (array_key_exists($k, $c)) {
                $d[$k] = $c[$k];
            }
        }
        return $d;
    }
}
