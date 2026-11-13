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

use mod_peerfeedback\local\response_manager;
use mod_peerfeedback\local\teacher_feedback;

/**
 * Tests for the teacher's feedback to a student: per-feedback comments, the overall comment and notifications.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(teacher_feedback::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(response_manager::class)]
final class teacher_feedback_test extends \advanced_testcase {
    /** @var \stdClass */
    private $pf;
    /** @var \stdClass[] */
    private $u;
    /** @var response_manager */
    private $rm;
    /** @var teacher_feedback */
    private $tf;
    /** @var \context_module */
    private $context;
    /** @var \stdClass */
    private $teacher;

    /**
     * a, b, c in one group; a submitted about b and c.
     *
     * @param array $settings
     */
    private function make(array $settings = ['grading' => 0]): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        foreach (['a', 'b', 'c'] as $k) {
            $this->u[$k] = $this->getDataGenerator()->create_and_enrol($course, 'student', ['firstname' => 'F' . $k,
                'lastname' => 'L' . $k]);
        }
        $this->teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $mod = $this->getDataGenerator()->create_module('peerfeedback', ['course' => $course->id] + $settings);
        $this->pf = $DB->get_record('peerfeedback', ['id' => $mod->id]);
        $this->context = \context_module::instance($mod->cmid);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_peerfeedback');
        $gen->create_group($this->pf->id, [$this->u['a']->id, $this->u['b']->id, $this->u['c']->id]);
        $q = $gen->create_question($this->pf->id, 'shorttext');
        $this->rm = new response_manager($this->pf, $this->context);
        $this->rm->save($this->u['a']->id, $this->u['b']->id, [$q->id => 'about b'], true);
        $this->rm->save($this->u['a']->id, $this->u['c']->id, [$q->id => 'about c'], true);
        $this->tf = new teacher_feedback($this->pf, $this->context);
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

    public function test_comment_shown_only_after_reviewed_even_with_grading_off(): void {
        $this->make();
        $this->rm->review($this->rid('b'), null, 'Too short', false, $this->teacher->id);
        $this->assertSame([], $this->tf->for_student($this->u['a']->id)['items']);
        $this->rm->review($this->rid('b'), null, 'Too short', true, $this->teacher->id);
        $items = $this->tf->for_student($this->u['a']->id)['items'];
        $this->assertCount(1, $items);
        $this->assertSame('Fb Lb', $items[0]['receivername']);
        $this->assertSame('Too short', $items[0]['comment']);
        $this->assertNull($items[0]['grade']);
        $this->assertSame('about b', $items[0]['answers'][0]['value']);
    }

    public function test_reviewed_without_comment_or_grade_is_not_listed(): void {
        $this->make();
        $this->rm->review($this->rid('b'), null, '', true, $this->teacher->id);
        $this->assertSame([], $this->tf->for_student($this->u['a']->id)['items']);
    }

    public function test_grade_listed_only_when_grading_on(): void {
        global $DB;
        $this->make(['grading' => 1]);
        $this->rm->review($this->rid('c'), 75, null, true, $this->teacher->id);
        $items = $this->tf->for_student($this->u['a']->id)['items'];
        $this->assertCount(1, $items);
        $this->assertEquals(75, $items[0]['grade']);
        $this->assertNull($items[0]['comment']);
        $this->pf->grading = 0;
        $DB->update_record('peerfeedback', $this->pf);
        $this->assertSame([], (new teacher_feedback($this->pf, $this->context))->for_student($this->u['a']->id)['items']);
    }

    public function test_receiver_never_sees_teacher_comment(): void {
        $this->make();
        $this->rm->review($this->rid('b'), null, 'Too short', true, $this->teacher->id);
        $this->assertSame([], $this->tf->for_student($this->u['b']->id)['items']);
    }

    public function test_overall_comment_hidden_until_shown(): void {
        $this->make();
        $this->tf->save_overall($this->u['a']->id, ' Be more specific. ', $this->teacher->id);
        $this->assertSame('Be more specific.', $this->tf->get_overall($this->u['a']->id)->comment);
        $this->assertNull($this->tf->for_student($this->u['a']->id)['overall']);
        $this->tf->set_overall_shown($this->u['a']->id, true, $this->teacher->id);
        $this->assertSame('Be more specific.', $this->tf->for_student($this->u['a']->id)['overall']);
        $this->assertNull($this->tf->for_student($this->u['b']->id)['overall']);
        $this->tf->set_overall_shown($this->u['a']->id, false, $this->teacher->id);
        $this->assertNull($this->tf->for_student($this->u['a']->id)['overall']);
    }

    public function test_saving_empty_overall_removes_it(): void {
        $this->make();
        $this->tf->save_overall($this->u['a']->id, 'Hello', $this->teacher->id);
        $this->tf->set_overall_shown($this->u['a']->id, true, $this->teacher->id);
        $this->tf->save_overall($this->u['a']->id, '   ', $this->teacher->id);
        $this->assertNull($this->tf->get_overall($this->u['a']->id));
        $this->assertNull($this->tf->for_student($this->u['a']->id)['overall']);
    }

    public function test_cannot_show_empty_overall(): void {
        $this->make();
        $sink = $this->redirectMessages();
        $this->tf->set_overall_shown($this->u['a']->id, true, $this->teacher->id);
        $this->assertNull($this->tf->get_overall($this->u['a']->id));
        $this->assertSame(0, $sink->count());
    }

    public function test_notification_when_comment_reviewed(): void {
        $this->make();
        $this->preventResetByRollback();
        $sink = $this->redirectMessages();
        $this->rm->review($this->rid('b'), null, 'Too short', false, $this->teacher->id);
        $this->assertSame(0, $sink->count());
        $this->rm->review($this->rid('b'), null, 'Too short', true, $this->teacher->id);
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($this->u['a']->id, $messages[0]->useridto);
        $this->assertSame('mod_peerfeedback', $messages[0]->component);
        $this->assertSame('teacherfeedback', $messages[0]->eventtype);
        $this->assertStringContainsString('view.php?id=' . $this->context->instanceid, $messages[0]->contexturl);
        // Saving again on an already reviewed feedback does not notify a second time.
        $this->rm->review($this->rid('b'), null, 'Too short!', false, $this->teacher->id);
        $this->assertCount(1, $sink->get_messages());
    }

    public function test_no_notification_when_reviewed_without_comment_or_grade(): void {
        $this->make();
        $this->preventResetByRollback();
        $sink = $this->redirectMessages();
        $this->rm->review($this->rid('b'), null, null, true, $this->teacher->id);
        $this->assertSame(0, $sink->count());
    }

    public function test_notification_when_overall_shown_once(): void {
        $this->make();
        $this->preventResetByRollback();
        $sink = $this->redirectMessages();
        $this->tf->save_overall($this->u['a']->id, 'Hello', $this->teacher->id);
        $this->assertSame(0, $sink->count());
        $this->tf->set_overall_shown($this->u['a']->id, true, $this->teacher->id);
        $this->tf->set_overall_shown($this->u['a']->id, true, $this->teacher->id);
        $this->tf->save_overall($this->u['a']->id, 'Hello again', $this->teacher->id);
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($this->u['a']->id, $messages[0]->useridto);
    }
}
