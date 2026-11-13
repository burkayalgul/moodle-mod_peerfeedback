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

    public function test_alone_all_puts_each_student_in_own_named_group(): void {
        $users = $this->setup_students(3);
        $g = $this->gm->create_group('Old team');
        $this->gm->assign([$users[0]->id, $users[1]->id], $g);
        $this->gm->alone_all();
        $this->assertSame([1, 1, 1], $this->sizes());
        $names = array_map(fn($grp) => $grp->name, $this->gm->get_groups());
        sort($names);
        $expected = array_map('fullname', $users);
        sort($expected);
        $this->assertSame($expected, $names);
        foreach ($users as $u) {
            $this->assertSame(fullname($u), $this->gm->get_group_of($u->id)->name);
        }
    }

    public function test_pair_selected_moves_only_ticked_students(): void {
        $users = $this->setup_students(6);
        $g = $this->gm->create_group('Team A');
        $this->gm->assign(array_map(fn($u) => $u->id, $users), $g);
        $this->gm->pair_selected([$users[0]->id, $users[1]->id, $users[2]->id, $users[3]->id], 5);
        $this->assertSame([2, 2, 2], $this->sizes());
        $this->assertSame($g, (int)$this->gm->get_group_of($users[4]->id)->id);
        $this->assertSame($g, (int)$this->gm->get_group_of($users[5]->id)->id);
        $this->assertNotEquals($g, (int)$this->gm->get_group_of($users[0]->id)->id);
    }

    public function test_pair_selected_odd_makes_one_triple(): void {
        $users = $this->setup_students(4);
        $this->gm->pair_selected([$users[0]->id, $users[1]->id, $users[2]->id], 3);
        $this->assertSame([3], $this->sizes());
        $this->assertNull($this->gm->get_group_of($users[3]->id));
    }

    public function test_alone_selected_gives_ticked_students_own_named_groups(): void {
        $users = $this->setup_students(4);
        $g = $this->gm->create_group('Team A');
        $this->gm->assign(array_map(fn($u) => $u->id, $users), $g);
        $this->gm->alone_selected([$users[0]->id, $users[1]->id]);
        $this->assertSame([1, 1, 2], $this->sizes());
        $this->assertSame(fullname($users[0]), $this->gm->get_group_of($users[0]->id)->name);
        $this->assertSame('Team A', $this->gm->get_group_of($users[2]->id)->name);
    }

    public function test_selected_actions_remove_groups_they_empty_and_ignore_strangers(): void {
        $users = $this->setup_students(2);
        $stranger = $this->getDataGenerator()->create_user();
        $g = $this->gm->create_group('Team A');
        $keep = $this->gm->create_group('Already empty');
        $this->gm->assign([$users[0]->id, $users[1]->id], $g);
        $this->gm->alone_selected([$users[0]->id, $users[1]->id, $stranger->id]);
        $names = array_map(fn($grp) => $grp->name, $this->gm->get_groups());
        $this->assertNotContains('Team A', $names);
        $this->assertContains('Already empty', $names);
        $this->assertNull($this->gm->get_group_of($stranger->id));
        $this->assertCount(3, $names);
    }

    public function test_eligible_users_sorted_by_displayed_name(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        foreach ([['Zeynep', 'Arslan'], ['ayşe', 'Kaya'], ['Can', 'Yılmaz'], ['Ayla', 'Demir']] as [$first, $last]) {
            $u = $this->getDataGenerator()->create_user(['firstname' => $first, 'lastname' => $last]);
            $this->getDataGenerator()->enrol_user($u->id, $course->id, 'student');
        }
        $mod = $this->getDataGenerator()->create_module('peerfeedback', ['course' => $course->id]);
        $pf = $DB->get_record('peerfeedback', ['id' => $mod->id]);
        $gm = new group_manager($pf, \context_module::instance($mod->cmid));
        $names = array_values(array_map('fullname', $gm->eligible_users()));
        $this->assertSame(['Ayla Demir', 'ayşe Kaya', 'Can Yılmaz', 'Zeynep Arslan'], $names);
    }

    public function test_grouped_users_lists_only_grouped_students_group_by_group(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $u = [];
        foreach (['Zeynep', 'Ayla', 'Can', 'Deniz', 'Ece'] as $first) {
            $u[$first] = $this->getDataGenerator()->create_and_enrol($course, 'student', ['firstname' => $first,
                'lastname' => 'X']);
        }
        $mod = $this->getDataGenerator()->create_module('peerfeedback', ['course' => $course->id]);
        $pf = $DB->get_record('peerfeedback', ['id' => $mod->id]);
        $gm = new group_manager($pf, \context_module::instance($mod->cmid));
        $g1 = $gm->create_group('Team B');
        $g2 = $gm->create_group('Team A');
        $gm->assign([$u['Zeynep']->id, $u['Can']->id], $g1);
        $gm->assign([$u['Deniz']->id], $g2);
        $gm->create_group('Empty');

        $sections = $gm->grouped_users();
        $this->assertSame(['Team B', 'Team A'], array_column($sections, 'name'));
        $this->assertSame($g1, $sections[0]['id']);
        $this->assertSame(['Can X', 'Zeynep X'], array_values(array_map('fullname', $sections[0]['users'])));
        $this->assertSame(['Deniz X'], array_values(array_map('fullname', $sections[1]['users'])));

        $only = $gm->grouped_users($g2);
        $this->assertCount(1, $only);
        $this->assertSame('Team A', $only[0]['name']);
        $this->assertSame([], $gm->grouped_users(999999));
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
        $mk = function (int $giver, int $receiver, int $status) use ($DB, $g, $q) {
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

    public function test_drafts_lost_counts_without_changing_anything(): void {
        global $DB;
        [$a, $b] = $this->setup_students(2);
        $gid = $this->gm->create_group();
        $this->gm->assign([$a->id, $b->id], $gid);
        $q = $this->getDataGenerator()->get_plugin_generator('mod_peerfeedback')->create_question($this->pf->id, 'shorttext');
        $rm = new \mod_peerfeedback\local\response_manager(
            $this->pf,
            \context_module::instance(get_coursemodule_from_instance('peerfeedback', $this->pf->id)->id)
        );
        $rm->save($a->id, $b->id, [$q->id => 'draft'], false);
        $now = $this->gm->membership();
        $this->assertSame(0, $this->gm->drafts_lost($now));
        $without = $now;
        unset($without[(int)$b->id]);
        $this->assertSame(1, $this->gm->drafts_lost($without));
        $moved = $now;
        $moved[(int)$b->id] = $this->gm->create_group();
        $this->assertSame(1, $this->gm->drafts_lost($moved));
        $both = $moved;
        $both[(int)$a->id] = $moved[(int)$b->id];
        $this->assertSame(0, $this->gm->drafts_lost($both));
        $this->assertEquals(1, $DB->count_records('peerfeedback_response'));
        $this->assertTrue($DB->record_exists('peerfeedback_member', ['userid' => $b->id]));
    }
}
