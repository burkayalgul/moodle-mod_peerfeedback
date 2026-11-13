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
}
