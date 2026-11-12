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
use mod_peerfeedback\local\student_view;

/**
 * Tests for what students can see.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(student_view::class)]
final class student_view_test extends \advanced_testcase {
    /** @var \stdClass */
    private $pf;
    /** @var \stdClass[] */
    private $u;

    /**
     * a, b, c in group 1; d, e in group 2. Everyone submits about everyone in their group.
     *
     * @param int $visibility
     * @param int $grading
     */
    private function make(int $visibility, int $grading = 0): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        foreach (['a', 'b', 'c', 'd', 'e'] as $k) {
            $this->u[$k] = $this->getDataGenerator()->create_and_enrol(
                $course,
                'student',
                ['firstname' => 'First' . $k, 'lastname' => 'Last' . $k]
            );
        }
        $mod = $this->getDataGenerator()->create_module(
            'peerfeedback',
            ['course' => $course->id, 'visibility' => $visibility, 'grading' => $grading]
        );
        $this->pf = $DB->get_record('peerfeedback', ['id' => $mod->id]);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_peerfeedback');
        $gen->create_group($this->pf->id, [$this->u['a']->id, $this->u['b']->id, $this->u['c']->id]);
        $gen->create_group($this->pf->id, [$this->u['d']->id, $this->u['e']->id]);
        $q = $gen->create_question($this->pf->id, 'shorttext', [], true, 'Word');
        $rm = new response_manager($this->pf, \context_module::instance($mod->cmid));
        foreach ($this->u as $giver) {
            foreach ($rm->get_peers($giver->id) as $peer) {
                $rm->save($giver->id, $peer->id, [$q->id => 'from ' . $giver->id . ' to ' . $peer->id], true);
            }
        }
    }

    public function test_none_returns_nothing(): void {
        $this->make(constants::VIS_NONE);
        $this->assertSame([], (new student_view($this->pf))->for_user($this->u['a']->id));
    }

    public function test_given_lists_own_with_receiver(): void {
        $this->make(constants::VIS_GIVEN);
        $items = (new student_view($this->pf))->for_user($this->u['a']->id);
        $this->assertCount(2, $items);
        $this->assertEqualsCanonicalizing(['Firstb Lastb', 'Firstc Lastc'], array_column($items, 'receivername'));
        $this->assertSame('Word', $items[0]['answers'][0]['question']);
    }

    public function test_received_view_has_no_giver_identity(): void {
        foreach ([constants::VIS_RECEIVED, constants::VIS_ALL] as $mode) {
            $this->make($mode);
            $items = (new student_view($this->pf))->for_user($this->u['a']->id);
            $this->assertNotEmpty($items);
            foreach ($items as $item) {
                $this->assertNull($item['givername']);
            }
            $json = json_encode($items);
            $this->assertStringNotContainsString('giverid', $json);
            foreach ($items as $item) {
                $this->assertArrayNotHasKey('giverid', $item);
            }
            if ($mode === constants::VIS_RECEIVED) {
                $this->assertCount(2, $items);
                $this->assertStringNotContainsString('Lastb', $json);
                $this->assertStringNotContainsString('Lastc', $json);
                $this->assertStringNotContainsString('Firstb', $json);
            }
        }
    }

    public function test_all_limited_to_own_group(): void {
        $this->make(constants::VIS_ALL);
        $items = (new student_view($this->pf))->for_user($this->u['a']->id);
        $this->assertCount(6, $items);
        $json = json_encode($items);
        $this->assertStringNotContainsString('Lastd', $json);
        $this->assertStringNotContainsString('Laste', $json);
    }

    public function test_teacher_grade_only_on_own_given(): void {
        global $DB;
        $this->make(constants::VIS_ALL, 1);
        $DB->set_field('peerfeedback_response', 'grade', 77);
        $DB->set_field('peerfeedback_response', 'teachercomment', 'Nice');
        $DB->set_field('peerfeedback_response', 'status', constants::STATUS_REVIEWED);
        $this->pf->visibility = constants::VIS_GIVEN;
        $given = (new student_view($this->pf))->for_user($this->u['a']->id);
        $this->assertEquals(77, $given[0]['grade']);
        $this->assertSame('Nice', $given[0]['teachercomment']);
        $this->pf->visibility = constants::VIS_ALL;
        $graded = array_filter((new student_view($this->pf))->for_user($this->u['a']->id), fn($i) => $i['grade'] !== null);
        $this->assertCount(2, $graded);
        $this->pf->visibility = constants::VIS_RECEIVED;
        foreach ((new student_view($this->pf))->for_user($this->u['a']->id) as $item) {
            $this->assertNull($item['grade']);
            $this->assertNull($item['teachercomment']);
        }
    }
}
