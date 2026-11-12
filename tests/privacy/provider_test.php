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

namespace mod_peerfeedback\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_peerfeedback\local\response_manager;

/**
 * Privacy provider tests.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass[] */
    private $u = [];
    /** @var \context_module */
    private $context;
    /** @var \stdClass */
    private $pf;

    /**
     * a gives feedback to b, teacher t reviews it; c is in the group but silent; outsider o is in no group.
     */
    private function make(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        foreach (['a', 'b', 'c', 'o'] as $k) {
            $this->u[$k] = $this->getDataGenerator()->create_and_enrol($course);
        }
        $this->u['t'] = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $mod = $this->getDataGenerator()->create_module('peerfeedback', ['course' => $course->id, 'grading' => 1]);
        $this->pf = $DB->get_record('peerfeedback', ['id' => $mod->id]);
        $this->context = \context_module::instance($mod->cmid);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_peerfeedback');
        $gen->create_group($this->pf->id, [$this->u['a']->id, $this->u['b']->id, $this->u['c']->id]);
        $q = $gen->create_question($this->pf->id, 'shorttext', [], true, 'Word');
        $rm = new response_manager($this->pf, $this->context);
        $rm->save($this->u['a']->id, $this->u['b']->id, [$q->id => 'kind'], true);
        $rm->review((int)$rm->get_response($this->u['a']->id, $this->u['b']->id)->id, 90, 'Fine', true, $this->u['t']->id);
    }

    public function test_get_contexts_for_userid(): void {
        $this->make();
        foreach (['a', 'b', 'c', 't'] as $k) {
            $this->assertEquals([$this->context->id], provider::get_contexts_for_userid($this->u[$k]->id)->get_contextids(), $k);
        }
        $this->assertEmpty(provider::get_contexts_for_userid($this->u['o']->id)->get_contextids());
    }

    public function test_get_users_in_context(): void {
        $this->make();
        $list = new userlist($this->context, 'mod_peerfeedback');
        provider::get_users_in_context($list);
        $this->assertEqualsCanonicalizing(
            [$this->u['a']->id, $this->u['b']->id, $this->u['c']->id, $this->u['t']->id],
            $list->get_userids()
        );
    }

    public function test_export_user_data(): void {
        $this->make();
        $this->setUser($this->u['a']);
        provider::export_user_data(new approved_contextlist($this->u['a'], 'mod_peerfeedback', [$this->context->id]));
        $w = writer::with_context($this->context);
        $this->assertTrue($w->has_any_data());
        $given = $w->get_data([get_string('privacy:given', 'mod_peerfeedback')]);
        $this->assertSame('kind', $given->feedback[0]->answers[0]->value);
        $this->assertEquals(90, $given->feedback[0]->teachergrade);
        writer::reset();
        provider::export_user_data(new approved_contextlist($this->u['b'], 'mod_peerfeedback', [$this->context->id]));
        $received = writer::with_context($this->context)->get_data([get_string('privacy:received', 'mod_peerfeedback')]);
        $this->assertSame('kind', $received->feedback[0]->answers[0]->value);
        $this->assertObjectNotHasProperty('giver', $received->feedback[0]);
    }

    public function test_delete_data_for_user(): void {
        global $DB;
        $this->make();
        provider::delete_data_for_user(new approved_contextlist($this->u['b'], 'mod_peerfeedback', [$this->context->id]));
        $this->assertEquals(0, $DB->count_records('peerfeedback_response'));
        $this->assertEquals(0, $DB->count_records('peerfeedback_answer'));
        $this->assertFalse($DB->record_exists('peerfeedback_member', ['userid' => $this->u['b']->id]));
        $this->assertTrue($DB->record_exists('peerfeedback_member', ['userid' => $this->u['a']->id]));
    }

    public function test_delete_data_for_users(): void {
        global $DB;
        $this->make();
        provider::delete_data_for_users(new approved_userlist($this->context, 'mod_peerfeedback', [$this->u['c']->id]));
        $this->assertEquals(1, $DB->count_records('peerfeedback_response'));
        $this->assertFalse($DB->record_exists('peerfeedback_member', ['userid' => $this->u['c']->id]));
        provider::delete_data_for_all_users_in_context($this->context);
        $this->assertEquals(0, $DB->count_records('peerfeedback_response'));
        $this->assertEquals(0, $DB->count_records('peerfeedback_member'));
    }
}
