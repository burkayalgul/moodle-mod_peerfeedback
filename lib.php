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
 * Library of callbacks for mod_peerfeedback.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Report which Moodle features this module supports.
 *
 * @param string $feature FEATURE_xx constant
 * @return mixed
 */
function peerfeedback_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_INTRO => true,
        FEATURE_BACKUP_MOODLE2 => true,
        FEATURE_COMPLETION_HAS_RULES => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_GRADE_HAS_GRADE => false,
        FEATURE_GRADE_OUTCOMES => false,
        FEATURE_GROUPS => false,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_COLLABORATION,
        default => null,
    };
}

/**
 * Normalise form data before saving an instance.
 *
 * @param stdClass $data
 * @return stdClass
 */
function peerfeedback_prepare_data(stdClass $data): stdClass {
    foreach (['timeopen', 'timedue', 'selfeval', 'grading', 'gradebook', 'completionsubmit'] as $f) {
        $data->$f = empty($data->$f) ? 0 : (int)$data->$f;
    }
    $data->visibility = isset($data->visibility) ? (int)$data->visibility : 2;
    $data->grade = isset($data->grade) ? max(1, (int)$data->grade) : 100;
    $data->timemodified = time();
    return $data;
}

/**
 * Create a new activity instance.
 *
 * @param stdClass $data form data
 * @param mod_peerfeedback_mod_form|null $mform
 * @return int new instance id
 */
function peerfeedback_add_instance($data, $mform = null) {
    global $DB;
    $data = peerfeedback_prepare_data($data);
    $data->timecreated = $data->timemodified;
    $data->id = $DB->insert_record('peerfeedback', $data);
    return $data->id;
}

/**
 * Update an existing activity instance.
 *
 * @param stdClass $data form data
 * @param mod_peerfeedback_mod_form|null $mform
 * @return bool
 */
function peerfeedback_update_instance($data, $mform = null) {
    global $DB;
    $data = peerfeedback_prepare_data($data);
    $data->id = $data->instance;
    $DB->update_record('peerfeedback', $data);
    return true;
}

/**
 * Delete an activity instance and all its data.
 *
 * @param int $id instance id
 * @return bool
 */
function peerfeedback_delete_instance($id) {
    global $DB;
    if (!$DB->record_exists('peerfeedback', ['id' => $id])) {
        return false;
    }
    $responseids = $DB->get_fieldset_select('peerfeedback_response', 'id', 'peerfeedbackid = ?', [$id]);
    if ($responseids) {
        $DB->delete_records_list('peerfeedback_answer', 'responseid', $responseids);
    }
    $DB->delete_records('peerfeedback_response', ['peerfeedbackid' => $id]);
    $DB->delete_records('peerfeedback_member', ['peerfeedbackid' => $id]);
    $DB->delete_records('peerfeedback_group', ['peerfeedbackid' => $id]);
    $DB->delete_records('peerfeedback_question', ['peerfeedbackid' => $id]);
    $DB->delete_records('peerfeedback', ['id' => $id]);
    return true;
}
