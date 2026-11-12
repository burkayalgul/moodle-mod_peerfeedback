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

/**
 * Backup and restore tests.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class backup_restore_test extends \advanced_testcase {

    /**
     * Build a course with one activity, 2 questions, 1 group of 2 and one submitted response; duplicate it.
     *
     * @param bool $userdata
     * @return array [old pf id, new pf id, new course id]
     */
    private function roundtrip(bool $userdata): array {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $a = $this->getDataGenerator()->create_and_enrol($course);
        $b = $this->getDataGenerator()->create_and_enrol($course);
        $mod = $this->getDataGenerator()->create_module('peerfeedback', ['course' => $course->id, 'grading' => 1]);
        $pf = $DB->get_record('peerfeedback', ['id' => $mod->id]);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_peerfeedback');
        $gen->create_group($pf->id, [$a->id, $b->id], 'Team A');
        $q = $gen->create_question($pf->id, 'grid', ['criteria' => ['X', 'Y'], 'max' => 5], true, 'Rate');
        $gen->create_question($pf->id, 'heading', [], false, 'Intro');
        $rm = new response_manager($pf, \context_module::instance($mod->cmid));
        $rm->save($a->id, $b->id, [$q->id => ['0' => '3', '1' => '4']], true);
        $rm->review((int)$rm->get_response($a->id, $b->id)->id, 75, 'ok', true, $USER->id);

        $bc = new \backup_controller(\backup::TYPE_1COURSE, $course->id, \backup::FORMAT_MOODLE, \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT, $USER->id);
        $bc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value($userdata);
        $bc->execute_plan();
        $backupid = $bc->get_backupid();
        $bc->destroy();

        $newcourseid = \restore_dbops::create_new_course('Copy', 'copy', $course->category);
        $rc = new \restore_controller($backupid, $newcourseid, \backup::INTERACTIVE_NO, \backup::MODE_IMPORT, $USER->id,
            \backup::TARGET_NEW_COURSE);
        $rc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('users')->set_value($userdata);
        if ($userdata) {
            // Enrolments are needed so restored user ids map; import mode keeps the same users.
            $rc->get_plan()->get_setting('enrolments')->set_value(\backup::ENROL_ALWAYS);
        }
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();
        $newpf = $DB->get_record('peerfeedback', ['course' => $newcourseid], '*', MUST_EXIST);
        return [$pf->id, $newpf->id, $newcourseid];
    }

    public function test_backup_restore_with_userdata_keeps_responses(): void {
        global $DB;
        [$old, $new] = $this->roundtrip(true);
        $this->assertEquals(2, $DB->count_records('peerfeedback_question', ['peerfeedbackid' => $new]));
        $this->assertEquals('Team A', $DB->get_field('peerfeedback_group', 'name', ['peerfeedbackid' => $new]));
        $this->assertEquals(2, $DB->count_records('peerfeedback_member', ['peerfeedbackid' => $new]));
        $r = $DB->get_record('peerfeedback_response', ['peerfeedbackid' => $new], '*', MUST_EXIST);
        $this->assertEquals(75, $r->grade);
        $newgroup = $DB->get_field('peerfeedback_group', 'id', ['peerfeedbackid' => $new]);
        $this->assertEquals($newgroup, $r->groupid);
        $answer = $DB->get_record('peerfeedback_answer', ['responseid' => $r->id], '*', MUST_EXIST);
        $this->assertSame('{"0":3,"1":4}', $answer->value);
        $this->assertEquals('Rate', $DB->get_field('peerfeedback_question', 'questiontext', ['id' => $answer->questionid]));
        $this->assertEquals($new, $DB->get_field('peerfeedback_question', 'peerfeedbackid', ['id' => $answer->questionid]));
    }

    public function test_backup_restore_without_userdata_keeps_questions_and_groups_only(): void {
        global $DB;
        [, $new] = $this->roundtrip(false);
        $this->assertEquals(2, $DB->count_records('peerfeedback_question', ['peerfeedbackid' => $new]));
        $this->assertEquals(1, $DB->count_records('peerfeedback_group', ['peerfeedbackid' => $new]));
        $this->assertEquals(0, $DB->count_records('peerfeedback_member', ['peerfeedbackid' => $new]));
        $this->assertEquals(0, $DB->count_records('peerfeedback_response', ['peerfeedbackid' => $new]));
    }
}
