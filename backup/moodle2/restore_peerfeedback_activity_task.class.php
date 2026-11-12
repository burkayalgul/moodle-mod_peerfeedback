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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/peerfeedback/backup/moodle2/restore_peerfeedback_stepslib.php');

/**
 * Restore task for a peer feedback activity.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_peerfeedback_activity_task extends restore_activity_task {

    /**
     * No particular settings.
     */
    protected function define_my_settings() {
    }

    /**
     * One structure step reading peerfeedback.xml.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_peerfeedback_activity_structure_step('peerfeedback_structure', 'peerfeedback.xml'));
    }

    /**
     * Content processed by the link decoder.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        return [new restore_decode_content('peerfeedback', ['intro'], 'peerfeedback')];
    }

    /**
     * Link decoding rules.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('PEERFEEDBACKVIEWBYID', '/mod/peerfeedback/view.php?id=$1', 'course_module'),
            new restore_decode_rule('PEERFEEDBACKINDEX', '/mod/peerfeedback/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Log rules for this activity.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules() {
        return [];
    }

    /**
     * Course-level log rules.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules_for_course() {
        return [];
    }
}
