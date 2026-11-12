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
use mod_peerfeedback\local\response_manager;

/**
 * Tests for saving and submitting feedback.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(response_manager::class)]
final class response_manager_test extends \advanced_testcase {

    /** @var \stdClass */
    private $pf;
    /** @var \stdClass[] */
    private $users;
    /** @var \stdClass rating question */
    private $q;

    /**
     * Activity with students a, b, c in one group and d in another.
     *
     * @param array $settings activity overrides
     * @return response_manager
     */
    private function make(array $settings = []): response_manager {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        foreach (['a', 'b', 'c', 'd'] as $k) {
            $this->users[$k] = $this->getDataGenerator()->create_and_enrol($course, 'student', ['lastname' => strtoupper($k)]);
        }
        $mod = $this->getDataGenerator()->create_module('peerfeedback', ['course' => $course->id] + $settings);
        $this->pf = $DB->get_record('peerfeedback', ['id' => $mod->id]);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_peerfeedback');
        $gen->create_group($this->pf->id, [$this->users['a']->id, $this->users['b']->id, $this->users['c']->id]);
        $gen->create_group($this->pf->id, [$this->users['d']->id]);
        $this->q = $gen->create_question($this->pf->id, 'rating', ['max' => 5], true);
        return new response_manager($this->pf, \context_module::instance($mod->cmid));
    }

    public function test_peers_exclude_self_unless_selfeval(): void {
        $rm = $this->make();
        $a = (int)$this->users['a']->id;
        $this->assertSame([(int)$this->users['b']->id, (int)$this->users['c']->id], array_map('intval', array_keys($rm->get_peers($a))));
        $rm2 = $this->make(['selfeval' => 1]);
        $a = (int)$this->users['a']->id;
        $this->assertSame([(int)$this->users['b']->id, (int)$this->users['c']->id, $a],
            array_map('intval', array_keys($rm2->get_peers($a))));
    }

    public function test_peers_empty_without_group(): void {
        global $DB;
        $rm = $this->make();
        $DB->delete_records('peerfeedback_member', ['userid' => $this->users['a']->id]);
        $this->assertSame([], $rm->get_peers($this->users['a']->id));
    }

    public function test_save_requires_required_answers(): void {
        global $DB;
        $rm = $this->make();
        $errors = $rm->save($this->users['a']->id, $this->users['b']->id, [$this->q->id => ''], false);
        $this->assertSame([$this->q->id => 'err_required'], $errors);
        $this->assertEquals(0, $DB->count_records('peerfeedback_response'));
    }

    public function test_save_then_submit_sets_status_and_time(): void {
        $rm = $this->make();
        [$a, $b] = [$this->users['a']->id, $this->users['b']->id];
        $this->assertSame([], $rm->save($a, $b, [$this->q->id => '4'], false));
        $r = $rm->get_response($a, $b);
        $this->assertEquals(constants::STATUS_SAVED, $r->status);
        $this->assertSame('4', $r->answers[$this->q->id]);
        $this->assertNull($r->timesubmitted);
        $sink = $this->redirectEvents();
        $this->assertSame([], $rm->save($a, $b, [$this->q->id => '5'], true));
        $r = $rm->get_response($a, $b);
        $this->assertEquals(constants::STATUS_SUBMITTED, $r->status);
        $this->assertNotNull($r->timesubmitted);
        $this->assertSame('5', $r->answers[$this->q->id]);
        $this->assertInstanceOf(event\response_submitted::class, $sink->get_events()[0]);
        $this->assertSame(['done' => 1, 'total' => 2], $rm->progress($a));
        // Saving again after submit keeps it submitted.
        $rm->save($a, $b, [$this->q->id => '3'], false);
        $this->assertEquals(constants::STATUS_SUBMITTED, $rm->get_response($a, $b)->status);
    }

    public function test_save_rejects_receiver_outside_group(): void {
        global $DB;
        $rm = $this->make();
        try {
            $rm->save($this->users['a']->id, $this->users['d']->id, [$this->q->id => '4'], true);
            $this->fail('Expected exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_notallowed', $e->errorcode);
        }
        $this->assertEquals(0, $DB->count_records('peerfeedback_response'));
    }

    public function test_save_rejected_when_reviewed_or_closed(): void {
        global $DB;
        $rm = $this->make();
        [$a, $b, $c] = [$this->users['a']->id, $this->users['b']->id, $this->users['c']->id];
        $rm->save($a, $b, [$this->q->id => '4'], true);
        $DB->set_field('peerfeedback_response', 'status', constants::STATUS_REVIEWED, ['giverid' => $a]);
        $this->assertFalse($rm->can_edit($a, $b));
        $this->assertThrows(fn() => $rm->save($a, $b, [$this->q->id => '1'], true));

        $this->pf->timedue = time() - 60;
        $this->assertFalse($rm->can_edit($a, $c));
        $this->assertThrows(fn() => $rm->save($a, $c, [$this->q->id => '1'], true));

        $this->pf->timedue = 0;
        $this->pf->timeopen = time() + 3600;
        $this->assertThrows(fn() => $rm->save($a, $c, [$this->q->id => '1'], true));
        $this->assertEquals(1, $DB->count_records('peerfeedback_response'));
    }

    public function test_edit_after_grade_flags_response(): void {
        global $DB;
        $rm = $this->make();
        [$a, $b] = [$this->users['a']->id, $this->users['b']->id];
        $rm->save($a, $b, [$this->q->id => '4'], true);
        $DB->set_field('peerfeedback_response', 'grade', 80, ['giverid' => $a]);
        $rm->save($a, $b, [$this->q->id => '2'], true);
        $r = $rm->get_response($a, $b);
        $this->assertEquals(1, $r->editedaftergrade);
        $this->assertEquals(80, $r->grade);
    }

    public function test_heading_questions_are_ignored_on_save(): void {
        global $DB;
        $rm = $this->make();
        $h = $this->getDataGenerator()->get_plugin_generator('mod_peerfeedback')->create_question($this->pf->id, 'heading', [], false);
        $this->assertSame([], $rm->save($this->users['a']->id, $this->users['b']->id,
            [$this->q->id => '4', $h->id => 'junk'], true));
        $this->assertFalse($DB->record_exists('peerfeedback_answer', ['questionid' => $h->id]));
    }

    public function test_stores_givers_group(): void {
        global $DB;
        $rm = $this->make();
        $rm->save($this->users['a']->id, $this->users['b']->id, [$this->q->id => '4'], true);
        $groupid = $DB->get_field('peerfeedback_member', 'groupid', ['userid' => $this->users['a']->id]);
        $this->assertEquals($groupid, $DB->get_field('peerfeedback_response', 'groupid', ['giverid' => $this->users['a']->id]));
    }

    /**
     * Assert the callable throws a moodle_exception.
     *
     * @param callable $fn
     */
    private function assertThrows(callable $fn): void {
        try {
            $fn();
            $this->fail('Expected moodle_exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_notallowed', $e->errorcode);
        }
    }
}
