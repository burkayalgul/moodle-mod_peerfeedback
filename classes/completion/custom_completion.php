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

namespace mod_peerfeedback\completion;

use core_completion\activity_custom_completion;
use mod_peerfeedback\local\response_manager;

/**
 * Custom completion rule: the student submitted feedback for everyone they must evaluate.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Complete when the user has at least one person to evaluate and has submitted feedback for all of them.
     *
     * @param string $rule
     * @return int
     */
    public function get_state(string $rule): int {
        global $DB;
        $this->validate_rule($rule);
        $pf = $DB->get_record('peerfeedback', ['id' => $this->cm->instance], '*', MUST_EXIST);
        $progress = (new response_manager($pf, \context_module::instance($this->cm->id)))->progress($this->userid);
        return ($progress['total'] > 0 && $progress['done'] >= $progress['total']) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Rules defined by this plugin.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionsubmit'];
    }

    /**
     * Rule descriptions shown on the activity.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        return ['completionsubmit' => get_string('completiondetail:submit', 'peerfeedback')];
    }

    /**
     * Display order of rules.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return ['completionview', 'completionsubmit'];
    }
}
