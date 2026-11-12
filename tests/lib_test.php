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

/**
 * Tests for lib.php callbacks.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('peerfeedback_add_instance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('peerfeedback_delete_instance')]
final class lib_test extends \advanced_testcase {

    public function test_add_instance_stores_defaults(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $pf = $this->getDataGenerator()->create_module('peerfeedback', ['course' => $course->id]);
        $rec = $DB->get_record('peerfeedback', ['id' => $pf->id], '*', MUST_EXIST);
        $this->assertEquals(2, $rec->visibility);
        $this->assertEquals(0, $rec->selfeval);
        $this->assertEquals(0, $rec->grading);
        $this->assertEquals(1, $rec->gradebook);
        $this->assertEquals(100, $rec->grade);
    }

    public function test_delete_instance_removes_children(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $u = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $pf = $this->getDataGenerator()->create_module('peerfeedback', ['course' => $course->id]);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_peerfeedback');
        $gen->create_question($pf->id, 'rating', ['max' => 5]);
        $gen->create_group($pf->id, [$u->id]);
        $this->assertTrue(peerfeedback_delete_instance($pf->id));
        $this->assertEquals(0, $DB->count_records('peerfeedback_question', ['peerfeedbackid' => $pf->id]));
        $this->assertEquals(0, $DB->count_records('peerfeedback_group', ['peerfeedbackid' => $pf->id]));
        $this->assertEquals(0, $DB->count_records('peerfeedback_member', ['peerfeedbackid' => $pf->id]));
    }
}
