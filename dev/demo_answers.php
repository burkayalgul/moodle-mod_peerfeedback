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
 * Dev only: every grouped demo student submits sample feedback for their peers.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');

$course = $DB->get_record('course', ['shortname' => 'pfdemo'], '*', MUST_EXIST);
$pf = $DB->get_record('peerfeedback', ['course' => $course->id], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('peerfeedback', $pf->id);
$rm = new \mod_peerfeedback\local\response_manager($pf, context_module::instance($cm->id));
$qs = (new \mod_peerfeedback\local\question_manager($pf))->get_questions();
$samples = ['Share your notes a day earlier so we can plan.', 'Great research; try to speak up more in meetings.',
    'Very reliable. Could explain ideas more simply.'];
$i = 0;
foreach ((new \mod_peerfeedback\local\group_manager($pf, context_module::instance($cm->id)))->get_groups() as $g) {
    foreach ($g->members as $giver) {
        foreach ($rm->get_peers($giver) as $peer) {
            $raw = [];
            foreach ($qs as $q) {
                $raw[$q->id] = match ($q->qtype) {
                    'rating' => (string)(3 + $i % 3),
                    'grid' => ['0' => (string)(2 + $i % 4), '1' => '4', '2' => (string)(1 + $i % 5)],
                    'singlechoice' => (string)($i % 3),
                    'multichoice' => [($i % 4) => '1', (($i + 1) % 4) => '1'],
                    'number' => (string)(4 + $i),
                    'shorttext' => ['Organiser', 'Researcher', 'Presenter'][$i % 3],
                    'longtext' => $samples[$i % 3],
                    default => null,
                };
            }
            $errors = $rm->save($giver, $peer->id, $raw, $i % 4 !== 3);
            echo $errors ? 'ERR ' . json_encode($errors) . "\n" : '';
            $i++;
        }
    }
}
echo "DEMO ANSWERS: $i\n";
