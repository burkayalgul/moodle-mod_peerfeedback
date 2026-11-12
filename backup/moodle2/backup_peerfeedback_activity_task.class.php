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

require_once($CFG->dirroot . '/mod/peerfeedback/backup/moodle2/backup_peerfeedback_stepslib.php');

/**
 * Backup task for a peer feedback activity.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_peerfeedback_activity_task extends backup_activity_task {

    /**
     * No particular settings.
     */
    protected function define_my_settings() {
    }

    /**
     * One structure step writing peerfeedback.xml.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_peerfeedback_activity_structure_step('peerfeedback_structure', 'peerfeedback.xml'));
    }

    /**
     * Encode links to the activity scripts.
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;
        $base = preg_quote($CFG->wwwroot, '/');
        $content = preg_replace('/(' . $base . '\/mod\/peerfeedback\/index.php\?id\=)([0-9]+)/', '$@PEERFEEDBACKINDEX*$2@$',
            $content);
        return preg_replace('/(' . $base . '\/mod\/peerfeedback\/view.php\?id\=)([0-9]+)/', '$@PEERFEEDBACKVIEWBYID*$2@$',
            $content);
    }
}
