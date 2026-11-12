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

use mod_peerfeedback\local\question_manager;

/**
 * Tests for the question manager.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(question_manager::class)]
final class question_manager_test extends \advanced_testcase {
    /**
     * Create an activity and its manager.
     *
     * @return array [pf record, question_manager]
     */
    private function setup_pf(): array {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $mod = $this->getDataGenerator()->create_module('peerfeedback', ['course' => $course->id]);
        $pf = $DB->get_record('peerfeedback', ['id' => $mod->id]);
        return [$pf, new question_manager($pf)];
    }

    /**
     * Insert a submitted response so the activity counts as having submissions.
     *
     * @param int $pfid
     */
    private function add_submission(int $pfid): void {
        global $DB;
        $DB->insert_record('peerfeedback_response', ['peerfeedbackid' => $pfid, 'giverid' => 2, 'receiverid' => 3,
            'groupid' => 0, 'status' => 1, 'timecreated' => time(), 'timemodified' => time()]);
    }

    public function test_add_and_order(): void {
        [, $qm] = $this->setup_pf();
        $a = $qm->add('shorttext', 'A', true, []);
        $b = $qm->add('rating', 'B', true, ['max' => 5]);
        $c = $qm->add('longtext', 'C', false, []);
        $qm->move($c, -1);
        $this->assertSame([$a, $c, $b], array_values(array_map(fn($q) => (int)$q->id, $qm->get_questions())));
        $this->assertSame(['max' => 5], $qm->get_questions()[$b]->config);
    }

    public function test_delete_blocked_after_submission(): void {
        [$pf, $qm] = $this->setup_pf();
        $a = $qm->add('shorttext', 'A', true, []);
        $this->add_submission($pf->id);
        $this->assertTrue($qm->has_submissions());
        $this->expectException(\moodle_exception::class);
        $qm->delete($a);
    }

    public function test_update_wording_allowed_after_submission_but_not_option_removal(): void {
        [$pf, $qm] = $this->setup_pf();
        $a = $qm->add('singlechoice', 'Pick', true, ['options' => ['A', 'B']]);
        $this->add_submission($pf->id);
        $qm->update($a, 'Pick one', false, ['options' => ['A', 'B', 'C']]);
        $q = $qm->get_questions()[$a];
        $this->assertSame('Pick one', $q->questiontext);
        $this->assertEquals(0, $q->required);
        $this->expectException(\moodle_exception::class);
        $qm->update($a, 'Pick one', false, ['options' => ['A']]);
    }

    public function test_type_cannot_change(): void {
        [, $qm] = $this->setup_pf();
        $a = $qm->add('rating', 'R', true, ['max' => 5]);
        $qm->update($a, 'R2', true, ['max' => 7]);
        $this->assertSame('rating', $qm->get_questions()[$a]->qtype);
    }

    public function test_delete_allowed_before_submissions(): void {
        [, $qm] = $this->setup_pf();
        $a = $qm->add('shorttext', 'A', true, []);
        $qm->delete($a);
        $this->assertSame([], $qm->get_questions());
    }

    public function test_split_text_first_line_is_title_rest_keeps_line_breaks(): void {
        $text = "Step 1: Clarify\r\n\r\nAsk clarifying questions.\r\nAre there aspects?\n\n---\n\nPasul 1: Clarificați";
        [$title, $body] = question_manager::split_text($text);
        $this->assertSame('Step 1: Clarify', $title);
        $this->assertSame("Ask clarifying questions.\nAre there aspects?\n\n---\n\nPasul 1: Clarificați", $body);
        $this->assertSame(
            ['How well did they communicate?', ''],
            question_manager::split_text("  How well did they communicate?  \n")
        );
        $this->assertSame(['Title', ''], question_manager::split_text("\n\nTitle"));
    }
}
