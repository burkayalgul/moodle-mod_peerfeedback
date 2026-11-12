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

use mod_peerfeedback\local\group_manager;

/**
 * Tests for the group manager.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(group_manager::class)]
final class group_manager_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;
    /** @var \stdClass */
    private $pf;
    /** @var group_manager */
    private $gm;

    /**
     * Course with n students and an activity.
     *
     * @param int $n
     * @return \stdClass[] students
     */
    private function setup_students(int $n): array {
        global $DB;
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $users = [];
        for ($i = 0; $i < $n; $i++) {
            $users[] = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        }
        $mod = $this->getDataGenerator()->create_module('peerfeedback', ['course' => $this->course->id]);
        $this->pf = $DB->get_record('peerfeedback', ['id' => $mod->id]);
        $this->gm = new group_manager($this->pf, \context_module::instance($mod->cmid));
        return $users;
    }

    /**
     * Sorted group sizes.
     *
     * @return int[]
     */
    private function sizes(): array {
        $sizes = array_map(fn($g) => count($g->members), $this->gm->get_groups());
        sort($sizes);
        return array_values($sizes);
    }

    public function test_assign_moves_user_between_groups(): void {
        global $DB;
        [$a] = $this->setup_students(2);
        $g1 = $this->gm->create_group();
        $g2 = $this->gm->create_group();
        $this->gm->assign([$a->id], $g1);
        $this->gm->assign([$a->id], $g2);
        $this->assertEquals(1, $DB->count_records('peerfeedback_member', ['userid' => $a->id]));
        $this->assertEquals($g2, $this->gm->get_group_of($a->id)->id);
        $this->assertSame('Group 2', $this->gm->get_groups()[$g2]->name);
    }

    public function test_pair_all_odd_makes_one_triple(): void {
        $this->setup_students(7);
        $this->gm->pair_all(42);
        $this->assertSame([2, 2, 3], $this->sizes());
    }

    public function test_pair_all_single_user(): void {
        $this->setup_students(1);
        $this->gm->pair_all(1);
        $this->assertSame([1], $this->sizes());
    }

    public function test_random_by_size_balanced(): void {
        $this->setup_students(10);
        $this->gm->random_assign('size', 4, false, 7);
        $this->assertSame([3, 3, 4], $this->sizes());
    }

    public function test_random_by_groups_only_unassigned_without_reshuffle(): void {
        [$a, $b] = $this->setup_students(8);
        $g = $this->gm->create_group('Fixed');
        $this->gm->assign([$a->id, $b->id], $g);
        $this->gm->random_assign('groups', 3, false, 3);
        $groups = $this->gm->get_groups();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $groups[$g]->members);
        $this->assertSame([2, 2, 2, 2], $this->sizes());
        $this->gm->random_assign('groups', 2, true, 3);
        $this->assertSame([4, 4], $this->sizes());
    }

    public function test_eligible_users_excludes_suspended_and_teachers(): void {
        [$a, $b] = $this->setup_students(2);
        $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $s = $this->getDataGenerator()->create_and_enrol($this->course, 'student', null, 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], array_keys($this->gm->eligible_users()));
    }

    public function test_regroup_discards_orphan_saved_keeps_submitted(): void {
        global $DB;
        [$a, $b] = $this->setup_students(2);
        $g = $this->gm->create_group();
        $this->gm->assign([$a->id, $b->id], $g);
        $q = $this->getDataGenerator()->get_plugin_generator('mod_peerfeedback')->create_question($this->pf->id, 'shorttext');
        $mk = function(int $giver, int $receiver, int $status) use ($DB, $g, $q) {
            $id = $DB->insert_record('peerfeedback_response', ['peerfeedbackid' => $this->pf->id, 'giverid' => $giver,
                'receiverid' => $receiver, 'groupid' => $g, 'status' => $status, 'timecreated' => 1, 'timemodified' => 1]);
            $DB->insert_record('peerfeedback_answer', ['responseid' => $id, 'questionid' => $q->id, 'value' => 'x']);
            return $id;
        };
        $saved = $mk($a->id, $b->id, 0);
        $submitted = $mk($b->id, $a->id, 1);
        $this->gm->assign([$b->id], $this->gm->create_group());
        $this->assertFalse($DB->record_exists('peerfeedback_response', ['id' => $saved]));
        $this->assertFalse($DB->record_exists('peerfeedback_answer', ['responseid' => $saved]));
        $this->assertEquals($g, $DB->get_field('peerfeedback_response', 'groupid', ['id' => $submitted]));
    }

    public function test_delete_group_unassigns_members(): void {
        [$a] = $this->setup_students(1);
        $g = $this->gm->create_group();
        $this->gm->assign([$a->id], $g);
        $this->gm->delete_group($g);
        $this->assertNull($this->gm->get_group_of($a->id));
        $this->assertSame([], $this->gm->get_groups());
    }
}
