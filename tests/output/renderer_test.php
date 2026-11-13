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

namespace mod_peerfeedback\output;

use mod_peerfeedback\local\group_manager;

/**
 * Tests for the data the group screen is built from.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(renderer::class)]
final class renderer_test extends \advanced_testcase {
    /**
     * Group screen rows for a teacher, with one student whose email is set.
     *
     * @return array rows
     */
    private function rows(): array {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->getDataGenerator()->create_and_enrol(
            $course,
            'student',
            ['firstname' => 'Ayşe', 'lastname' => 'Kaya', 'email' => 'ayse@example.com']
        );
        $mod = $this->getDataGenerator()->create_module('peerfeedback', ['course' => $course->id]);
        $pf = $DB->get_record('peerfeedback', ['id' => $mod->id]);
        $this->setUser($teacher);
        $cm = get_fast_modinfo($course)->get_cm($mod->cmid);
        $context = \context_module::instance($mod->cmid);
        $PAGE->set_context($context);
        $renderer = $PAGE->get_renderer('mod_peerfeedback');
        return $renderer->groups_context($cm, new group_manager($pf, $context))['users'];
    }

    public function test_group_rows_carry_email_for_search_when_identity_is_shown(): void {
        set_config('showuseridentity', 'email');
        $rows = $this->rows();
        $this->assertSame('ayse@example.com', $rows[0]['identity']);
        $this->assertSame('ayşe kaya ayse@example.com', $rows[0]['search']);
    }

    public function test_group_rows_hide_email_when_identity_is_not_shown(): void {
        set_config('showuseridentity', '');
        $rows = $this->rows();
        $this->assertSame('', $rows[0]['identity']);
        $this->assertSame('ayşe kaya', $rows[0]['search']);
    }

    public function test_group_box_members_are_alphabetical(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $ids = [];
        foreach ([['Zeynep', 'Arslan'], ['Can', 'Yılmaz'], ['Ayşe', 'Kaya']] as [$first, $last]) {
            $ids[] = $this->getDataGenerator()->create_and_enrol(
                $course,
                'student',
                ['firstname' => $first, 'lastname' => $last]
            )->id;
        }
        $mod = $this->getDataGenerator()->create_module('peerfeedback', ['course' => $course->id]);
        $pf = $DB->get_record('peerfeedback', ['id' => $mod->id]);
        $context = \context_module::instance($mod->cmid);
        $gm = new group_manager($pf, $context);
        $gm->assign($ids, $gm->create_group('Team'));
        $this->setUser($teacher);
        $PAGE->set_context($context);
        $cm = get_fast_modinfo($course)->get_cm($mod->cmid);
        $boxes = $PAGE->get_renderer('mod_peerfeedback')->groups_context($cm, $gm)['groups'];
        $this->assertSame(['Ayşe Kaya', 'Can Yılmaz', 'Zeynep Arslan'], array_column($boxes[0]['members'], 'fullname'));
    }

    /**
     * Activity where a and b are in Team A, c is in no group; a wrote about b and about themself.
     *
     * @param array $settings
     * @return array [renderer, cm, pf, context, users]
     */
    private function feedback_setup(array $settings = []): array {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $u = [];
        foreach (['a' => 'Ayşe', 'b' => 'Can', 'c' => 'Deniz'] as $k => $first) {
            $u[$k] = $this->getDataGenerator()->create_and_enrol($course, 'student', ['firstname' => $first,
                'lastname' => 'X']);
        }
        $mod = $this->getDataGenerator()->create_module('peerfeedback', ['course' => $course->id, 'selfeval' => 1] +
            $settings);
        $pf = $DB->get_record('peerfeedback', ['id' => $mod->id]);
        $context = \context_module::instance($mod->cmid);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_peerfeedback');
        $gen->create_group($pf->id, [$u['a']->id, $u['b']->id], 'Team A');
        $q = $gen->create_question($pf->id, 'shorttext');
        $rm = new \mod_peerfeedback\local\response_manager($pf, $context);
        $rm->save($u['a']->id, $u['b']->id, [$q->id => 'kind'], true);
        $rm->save($u['a']->id, $u['a']->id, [$q->id => 'me'], true);
        $rm->review((int)$rm->get_response($u['a']->id, $u['b']->id)->id, 80, 'Add examples', true, $teacher->id);
        $rm->review((int)$rm->get_response($u['a']->id, $u['a']->id)->id, null, 'Honest', true, $teacher->id);
        $this->setUser($teacher);
        $PAGE->set_context($context);
        $cm = get_fast_modinfo($course)->get_cm($mod->cmid);
        return [$PAGE->get_renderer('mod_peerfeedback'), $cm, $pf, $context, $u];
    }

    public function test_teacher_feedback_section_lists_comments_and_overall(): void {
        [$renderer, , $pf, $context, $u] = $this->feedback_setup(['grading' => 1, 'grade' => 100]);
        $tf = new \mod_peerfeedback\local\teacher_feedback($pf, $context);
        $tf->save_overall($u['a']->id, 'Good start', 2);
        $tf->set_overall_shown($u['a']->id, true, 2);
        $ctx = $renderer->teacher_feedback_context($pf, $context, $u['a']->id);
        $this->assertSame('Good start', $ctx['overall']);
        $titles = array_column($ctx['items'], 'title');
        $this->assertContains('Your feedback about Can X', $titles);
        $this->assertContains('Your self-evaluation', $titles);
        $item = $ctx['items'][array_search('Your feedback about Can X', $titles)];
        $this->assertSame('80 / 100', $item['grade']);
        $this->assertStringContainsString('Add examples', $item['comment']);
        $this->assertNull($renderer->teacher_feedback_context($pf, $context, $u['b']->id));
    }

    public function test_review_list_shows_only_grouped_students_by_group(): void {
        [$renderer, $cm, $pf, $context, $u] = $this->feedback_setup();
        $gm = new group_manager($pf, $context);
        $rm = new \mod_peerfeedback\local\response_manager($pf, $context);
        $tf = new \mod_peerfeedback\local\teacher_feedback($pf, $context);
        $ctx = $renderer->review_context(
            $cm,
            $pf,
            $rm,
            $tf,
            $gm->get_groups(),
            $gm->grouped_users(),
            0,
            (int)$u['a']->id,
            'given',
            'all'
        );
        $this->assertSame(['Team A'], array_column($ctx['sections'], 'name'));
        $this->assertSame(['Ayşe X', 'Can X'], array_column($ctx['sections'][0]['users'], 'fullname'));
        $this->assertSame('Overall comment for Ayşe X', $ctx['overall']['heading']);
        $this->assertFalse($ctx['overall']['saved']);
        $this->assertSame(['All groups', 'Team A'], array_column($ctx['groupoptions'], 'label'));
        $received = $renderer->review_context(
            $cm,
            $pf,
            $rm,
            $tf,
            $gm->get_groups(),
            $gm->grouped_users(),
            0,
            (int)$u['a']->id,
            'received',
            'all'
        );
        $this->assertNull($received['overall']);
    }
}
