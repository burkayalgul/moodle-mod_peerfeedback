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

namespace mod_peerfeedback;

use mod_peerfeedback\completion\custom_completion;
use mod_peerfeedback\local\response_manager;

/**
 * Tests for the "submitted feedback for all group members" completion rule.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(custom_completion::class)]
final class completion_test extends \advanced_testcase {

    public function test_completion_complete_after_all_peers_submitted(): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $CFG->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        [$a, $b, $c] = [$this->getDataGenerator()->create_and_enrol($course), $this->getDataGenerator()->create_and_enrol($course),
            $this->getDataGenerator()->create_and_enrol($course)];
        $mod = $this->getDataGenerator()->create_module('peerfeedback', ['course' => $course->id, 'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionsubmit' => 1]);
        $pf = $DB->get_record('peerfeedback', ['id' => $mod->id]);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_peerfeedback');
        $gen->create_group($pf->id, [$a->id, $b->id, $c->id]);
        $q = $gen->create_question($pf->id, 'shorttext');
        $cm = get_fast_modinfo($course)->get_cm($mod->cmid);
        $rm = new response_manager($pf, \context_module::instance($cm->id));

        $this->assertSame(COMPLETION_INCOMPLETE, (new custom_completion($cm, (int)$a->id))->get_state('completionsubmit'));
        $rm->save($a->id, $b->id, [$q->id => 'x'], true);
        $this->assertSame(COMPLETION_INCOMPLETE, (new custom_completion($cm, (int)$a->id))->get_state('completionsubmit'));
        $rm->save($a->id, $c->id, [$q->id => 'x'], true);
        $this->assertSame(COMPLETION_COMPLETE, (new custom_completion($cm, (int)$a->id))->get_state('completionsubmit'));
        $completion = new \completion_info($course);
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_data($cm, false, $a->id)->completionstate);
    }

    public function test_completion_incomplete_without_peers(): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $CFG->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $a = $this->getDataGenerator()->create_and_enrol($course);
        $mod = $this->getDataGenerator()->create_module('peerfeedback', ['course' => $course->id, 'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionsubmit' => 1]);
        $cm = get_fast_modinfo($course)->get_cm($mod->cmid);
        $this->assertSame(COMPLETION_INCOMPLETE, (new custom_completion($cm, (int)$a->id))->get_state('completionsubmit'));
    }
}
