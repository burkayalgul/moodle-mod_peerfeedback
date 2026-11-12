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

use mod_peerfeedback\local\constants;
use mod_peerfeedback\local\group_manager;
use mod_peerfeedback\local\response_manager;

/**
 * Tests for teacher review, grading and the gradebook.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(response_manager::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('peerfeedback_update_grades')]
final class review_test extends \advanced_testcase {
    /** @var \stdClass */
    private $pf;
    /** @var \stdClass */
    private $course;
    /** @var \stdClass[] */
    private $u;
    /** @var response_manager */
    private $rm;
    /** @var \context_module */
    private $context;
    /** @var \stdClass */
    private $teacher;

    /**
     * a, b, c in one group; a submitted about b and c.
     *
     * @param array $settings
     */
    private function make(array $settings = ['grading' => 1]): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        foreach (['a', 'b', 'c'] as $k) {
            $this->u[$k] = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['lastname' => 'L' . $k]);
        }
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $mod = $this->getDataGenerator()->create_module('peerfeedback', ['course' => $this->course->id] + $settings);
        $this->pf = $DB->get_record('peerfeedback', ['id' => $mod->id]);
        $this->context = \context_module::instance($mod->cmid);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_peerfeedback');
        $gen->create_group($this->pf->id, [$this->u['a']->id, $this->u['b']->id, $this->u['c']->id]);
        $q = $gen->create_question($this->pf->id, 'shorttext');
        $this->rm = new response_manager($this->pf, $this->context);
        $this->rm->save($this->u['a']->id, $this->u['b']->id, [$q->id => 'x'], true);
        $this->rm->save($this->u['a']->id, $this->u['c']->id, [$q->id => 'y'], true);
    }

    /**
     * Response id of a's feedback about k.
     *
     * @param string $k
     * @return int
     */
    private function rid(string $k): int {
        return (int)$this->rm->get_response($this->u['a']->id, $this->u[$k]->id)->id;
    }

    /**
     * Gradebook grade of user a.
     *
     * @return float|null
     */
    private function gradebook(): ?float {
        $grades = grade_get_grades($this->course->id, 'mod', 'peerfeedback', $this->pf->id, $this->u['a']->id);
        if (empty($grades->items)) {
            return null;
        }
        $g = $grades->items[0]->grades[$this->u['a']->id]->grade;
        return $g === null ? null : (float)$g;
    }

    public function test_review_marks_and_locks(): void {
        $this->make();
        $sink = $this->redirectEvents();
        $this->rm->review($this->rid('b'), 80, 'Good', true, $this->teacher->id);
        $r = $this->rm->get_response($this->u['a']->id, $this->u['b']->id);
        $this->assertEquals(constants::STATUS_REVIEWED, $r->status);
        $this->assertEquals(80, $r->grade);
        $this->assertSame('Good', $r->teachercomment);
        $this->assertEquals($this->teacher->id, $r->reviewerid);
        $this->assertFalse($this->rm->can_edit($this->u['a']->id, $this->u['b']->id));
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof event\response_reviewed);
        $this->assertCount(1, $events);
    }

    public function test_reopen_unlocks_and_keeps_grade(): void {
        $this->make();
        $this->rm->review($this->rid('b'), 80, 'Good', true, $this->teacher->id);
        $this->rm->reopen($this->rid('b'));
        $r = $this->rm->get_response($this->u['a']->id, $this->u['b']->id);
        $this->assertEquals(constants::STATUS_SUBMITTED, $r->status);
        $this->assertEquals(80, $r->grade);
        $this->assertTrue($this->rm->can_edit($this->u['a']->id, $this->u['b']->id));
    }

    public function test_average_ignores_ungraded(): void {
        $this->make();
        $this->rm->review($this->rid('b'), 80, null, false, $this->teacher->id);
        $this->assertEquals(80, $this->rm->average_grade($this->u['a']->id));
        $this->rm->review($this->rid('c'), 60, null, false, $this->teacher->id);
        $this->assertEquals(70, $this->rm->average_grade($this->u['a']->id));
        $this->assertNull($this->rm->average_grade($this->u['b']->id));
    }

    public function test_gradebook_receives_average(): void {
        $this->make();
        $this->rm->review($this->rid('b'), 80, null, true, $this->teacher->id);
        $this->rm->review($this->rid('c'), 60, null, true, $this->teacher->id);
        $this->assertEquals(70.0, $this->gradebook());
    }

    public function test_gradebook_untouched_when_disabled(): void {
        $this->make(['grading' => 1, 'gradebook' => 0]);
        $this->rm->review($this->rid('b'), 80, null, true, $this->teacher->id);
        $this->assertFalse(\grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'peerfeedback',
            'iteminstance' => $this->pf->id, 'courseid' => $this->course->id]));
    }

    public function test_grade_out_of_range_rejected(): void {
        $this->make();
        $this->expectException(\moodle_exception::class);
        $this->rm->review($this->rid('b'), 101, null, false, $this->teacher->id);
    }

    public function test_no_graded_feedback_gives_null_grade(): void {
        $this->make();
        \peerfeedback_update_grades($this->pf, $this->u['a']->id);
        $this->assertNull($this->gradebook());
    }

    public function test_list_flags_outside_group_after_regroup(): void {
        $this->make();
        $gm = new group_manager($this->pf, $this->context);
        $gm->assign([$this->u['a']->id], $gm->create_group());
        $list = $this->rm->list_for($this->u['a']->id, 'given', 'all');
        $this->assertCount(2, $list);
        foreach ($list as $r) {
            $this->assertTrue($r->outsidegroup);
            $this->assertNotEmpty($r->givername);
            $this->assertNotEmpty($r->receivername);
        }
    }

    public function test_list_filters_and_directions(): void {
        $this->make();
        $this->rm->review($this->rid('b'), 80, null, true, $this->teacher->id);
        $this->assertCount(1, $this->rm->list_for($this->u['a']->id, 'given', 'reviewed'));
        $this->assertCount(1, $this->rm->list_for($this->u['a']->id, 'given', 'notreviewed'));
        $this->assertCount(1, $this->rm->list_for($this->u['b']->id, 'received', 'all'));
        $this->assertCount(0, $this->rm->list_for($this->u['b']->id, 'given', 'all'));
    }
}
