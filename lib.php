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
        FEATURE_GRADE_HAS_GRADE => true,
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
    peerfeedback_grade_item_update($DB->get_record('peerfeedback', ['id' => $data->id]));
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
    $pf = $DB->get_record('peerfeedback', ['id' => $data->id]);
    peerfeedback_grade_item_update($pf);
    peerfeedback_update_grades($pf);
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
    $DB->delete_records('peerfeedback_overall', ['peerfeedbackid' => $id]);
    $DB->delete_records('peerfeedback_member', ['peerfeedbackid' => $id]);
    $DB->delete_records('peerfeedback_group', ['peerfeedbackid' => $id]);
    $DB->delete_records('peerfeedback_question', ['peerfeedbackid' => $id]);
    peerfeedback_grade_item_delete($DB->get_record('peerfeedback', ['id' => $id]));
    $DB->delete_records('peerfeedback', ['id' => $id]);
    return true;
}

/**
 * Whether this activity sends grades to the gradebook.
 *
 * @param stdClass $pf
 * @return bool
 */
function peerfeedback_uses_gradebook(stdClass $pf): bool {
    return !empty($pf->grading) && !empty($pf->gradebook) && (int)$pf->grade > 0;
}

/**
 * Create, update or remove the grade item.
 *
 * @param stdClass $pf activity record (needs ->cmidnumber optionally)
 * @param mixed $grades grades to push, 'reset' or null
 * @return int GRADE_UPDATE_OK etc.
 */
function peerfeedback_grade_item_update($pf, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    if (!peerfeedback_uses_gradebook($pf)) {
        return peerfeedback_grade_item_delete($pf);
    }
    $params = ['itemname' => $pf->name, 'gradetype' => GRADE_TYPE_VALUE, 'grademax' => (int)$pf->grade, 'grademin' => 0];
    if (isset($pf->cmidnumber)) {
        $params['idnumber'] = $pf->cmidnumber;
    }
    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }
    return grade_update('mod/peerfeedback', $pf->course, 'mod', 'peerfeedback', $pf->id, 0, $grades, $params);
}

/**
 * Delete the grade item.
 *
 * @param stdClass $pf
 * @return int
 */
function peerfeedback_grade_item_delete($pf) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update('mod/peerfeedback', $pf->course, 'mod', 'peerfeedback', $pf->id, 0, null, ['deleted' => 1]);
}

/**
 * Push the average teacher grade of each giver to the gradebook.
 *
 * @param stdClass $pf
 * @param int $userid 0 for everyone who has given feedback
 * @param bool $nullifnone
 */
function peerfeedback_update_grades($pf, $userid = 0, $nullifnone = true) {
    global $DB;
    if (!peerfeedback_uses_gradebook($pf)) {
        peerfeedback_grade_item_update($pf);
        return;
    }
    $cm = get_coursemodule_from_instance('peerfeedback', $pf->id, $pf->course, false, MUST_EXIST);
    $rm = new \mod_peerfeedback\local\response_manager($pf, context_module::instance($cm->id));
    $userids = $userid ? [$userid] : $DB->get_fieldset_select(
        'peerfeedback_response',
        'DISTINCT giverid',
        'peerfeedbackid = ?',
        [$pf->id]
    );
    $grades = [];
    foreach ($userids as $uid) {
        $grades[$uid] = (object)['userid' => $uid, 'rawgrade' => $rm->average_grade((int)$uid)];
    }
    peerfeedback_grade_item_update($pf, $grades ?: null);
}

/**
 * Cached course-module info, including the custom completion rule values.
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info|false
 */
function peerfeedback_get_coursemodule_info($coursemodule) {
    global $DB;
    $pf = $DB->get_record(
        'peerfeedback',
        ['id' => $coursemodule->instance],
        'id, name, intro, introformat, completionsubmit'
    );
    if (!$pf) {
        return false;
    }
    $result = new cached_cm_info();
    $result->name = $pf->name;
    if ($coursemodule->showdescription) {
        $result->content = format_module_intro('peerfeedback', $pf, $coursemodule->id, false);
    }
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionsubmit'] = $pf->completionsubmit;
    }
    return $result;
}

/**
 * Descriptions of the active custom completion rules.
 *
 * @param cm_info|stdClass $cm
 * @return array
 */
function mod_peerfeedback_get_completion_active_rule_descriptions($cm) {
    if (empty($cm->customdata['customcompletionrules']) || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }
    $descriptions = [];
    if (!empty($cm->customdata['customcompletionrules']['completionsubmit'])) {
        $descriptions[] = get_string('completionsubmit', 'peerfeedback');
    }
    return $descriptions;
}

/**
 * Recompute the "submitted for all" completion state for some users (after a submit or a group change).
 *
 * @param stdClass $pf
 * @param int[] $userids
 */
function peerfeedback_update_completion(stdClass $pf, array $userids): void {
    global $CFG;
    if (empty($pf->completionsubmit) || !$userids) {
        return;
    }
    require_once($CFG->libdir . '/completionlib.php');
    [$course, $cm] = get_course_and_cm_from_instance($pf->id, 'peerfeedback');
    $completion = new completion_info($course);
    if (!$completion->is_enabled($cm)) {
        return;
    }
    foreach (array_unique($userids) as $userid) {
        $completion->update_state($cm, COMPLETION_UNKNOWN, (int)$userid);
    }
}
