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
 * Behat data generator for mod_peerfeedback.
 *
 * @package    mod_peerfeedback
 * @category   test
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_peerfeedback_generator extends behat_generator_base {

    #[\Override]
    protected function get_creatable_entities(): array {
        return [
            'questions' => [
                'singular' => 'question',
                'datagenerator' => 'behat_question',
                'required' => ['peerfeedback', 'qtype', 'questiontext'],
                'switchids' => ['peerfeedback' => 'peerfeedbackid'],
            ],
            'responses' => [
                'singular' => 'response',
                'datagenerator' => 'behat_response',
                'required' => ['peerfeedback', 'giver', 'receiver', 'answers'],
                'switchids' => ['peerfeedback' => 'peerfeedbackid'],
            ],
            'groups' => [
                'singular' => 'group',
                'datagenerator' => 'behat_group',
                'required' => ['peerfeedback', 'members'],
                'switchids' => ['peerfeedback' => 'peerfeedbackid'],
            ],
        ];
    }

    /**
     * Activity instance id from idnumber or name.
     *
     * @param string $idnumberorname
     * @return int
     */
    protected function get_peerfeedback_id(string $idnumberorname): int {
        return $this->get_cm_by_activity_name('peerfeedback', $idnumberorname)->instance;
    }
}
