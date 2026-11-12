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

namespace mod_peerfeedback\event;

/**
 * A teacher marked a peer feedback as reviewed.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_reviewed extends \core\event\base {
    #[\Override]
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'peerfeedback_response';
    }

    #[\Override]
    public static function get_name() {
        return get_string('eventresponsereviewed', 'peerfeedback');
    }

    #[\Override]
    public function get_description() {
        return "The user with id '$this->userid' reviewed peer feedback with id '$this->objectid' written by the user with id " .
            "'$this->relateduserid' in the peer feedback activity with course module id '$this->contextinstanceid'.";
    }

    #[\Override]
    public function get_url() {
        return new \moodle_url(
            '/mod/peerfeedback/review.php',
            ['id' => $this->contextinstanceid, 'userid' => $this->relateduserid]
        );
    }

    #[\Override]
    public static function get_objectid_mapping() {
        return ['db' => 'peerfeedback_response', 'restore' => 'peerfeedback_response'];
    }
}
