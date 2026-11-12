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

use mod_peerfeedback\local\exporter;
use mod_peerfeedback\local\question_manager;
use mod_peerfeedback\local\response_manager;

/**
 * Tests for exporting feedback.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(exporter::class)]
final class exporter_test extends \advanced_testcase {
    /** @var \stdClass */
    private $pf;
    /** @var array question ids by key */
    private $q = [];
    /** @var response_manager */
    private $rm;
    /** @var \stdClass[] */
    private $u = [];

    /**
     * Activity with a multi choice, a long text and a grid; Anna, Ben, Cem in one group.
     */
    private function make(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        foreach (['Anna', 'Ben', 'Cem'] as $name) {
            $this->u[$name] = $this->getDataGenerator()->create_and_enrol(
                $course,
                'student',
                ['firstname' => $name, 'lastname' => 'Test']
            );
        }
        $mod = $this->getDataGenerator()->create_module('peerfeedback', ['course' => $course->id]);
        $this->pf = $DB->get_record('peerfeedback', ['id' => $mod->id]);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_peerfeedback');
        $gen->create_group($this->pf->id, array_column($this->u, 'id'));
        $gen->create_question($this->pf->id, 'heading', [], false, 'Intro');
        $this->q['multi'] = $gen->create_question(
            $this->pf->id,
            'multichoice',
            ['options' => ['A, x', '"B"', 'C']],
            true,
            'Strengths'
        )->id;
        $this->q['long'] = $gen->create_question($this->pf->id, 'longtext', [], true, 'Improve')->id;
        $this->q['grid'] = $gen->create_question(
            $this->pf->id,
            'grid',
            ['criteria' => ['Part', 'Resp'], 'max' => 5],
            true,
            'Rate'
        )->id;
        $this->rm = new response_manager($this->pf, \context_module::instance($mod->cmid));
    }

    /**
     * Submit from giver to receiver.
     *
     * @param string $giver
     * @param string $receiver
     */
    private function submit(string $giver, string $receiver): void {
        $this->assertSame([], $this->rm->save($this->u[$giver]->id, $this->u[$receiver]->id, [
            $this->q['multi'] => ['0' => '1', '1' => '1'],
            $this->q['long'] => "Line one\nLine, \"two\"",
            $this->q['grid'] => ['0' => '4', '1' => '2'],
        ], true));
    }

    public function test_headers_include_grid_columns(): void {
        $this->make();
        $h = (new exporter($this->pf))->headers();
        $this->assertSame('Giver', $h[0]);
        $this->assertSame('Receiver', $h[1]);
        $this->assertSame('Group', $h[2]);
        $this->assertSame('Q1: Strengths', $h[3]);
        $this->assertSame('Q2: Improve', $h[4]);
        $this->assertSame('Q3: Rate – Part', $h[5]);
        $this->assertSame('Q3: Rate – Resp', $h[6]);
        $this->assertSame(['Status', 'Submitted', 'Teacher grade', 'Teacher comment'], array_slice($h, 7));
    }

    public function test_export_flattens_multi_and_grid(): void {
        $this->make();
        $this->submit('Anna', 'Ben');
        $ex = new exporter($this->pf);
        $rows = iterator_to_array($ex->rows('giver'), false);
        $this->assertCount(1, $rows);
        $this->assertCount(count($ex->headers()), $rows[0]);
        $this->assertSame('Anna Test', $rows[0][0]);
        $this->assertSame('Ben Test', $rows[0][1]);
        $this->assertSame('A, x; "B"', $rows[0][3]);
        $this->assertSame("Line one\nLine, \"two\"", $rows[0][4]);
        $this->assertSame(['4', '2'], [$rows[0][5], $rows[0][6]]);
    }

    public function test_sort_by_receiver(): void {
        $this->make();
        $this->submit('Anna', 'Cem');
        $this->submit('Ben', 'Anna');
        $this->submit('Anna', 'Ben');
        $rows = iterator_to_array((new exporter($this->pf))->rows('receiver'), false);
        $this->assertSame(['Anna Test', 'Ben Test', 'Cem Test'], array_column($rows, 1));
        $rows = iterator_to_array((new exporter($this->pf))->rows('giver'), false);
        $this->assertSame(
            [['Anna Test', 'Ben Test'], ['Anna Test', 'Cem Test'], ['Ben Test', 'Anna Test']],
            array_map(fn($r) => [$r[0], $r[1]], $rows)
        );
    }

    public function test_option_added_after_submission_exports_old_answers_correctly(): void {
        $this->make();
        $this->submit('Anna', 'Ben');
        $qm = new question_manager($this->pf);
        $qm->update($this->q['multi'], 'Strengths', true, ['options' => ['A, x', '"B"', 'C', 'D']]);
        $qm->update($this->q['grid'], 'Rate', true, ['criteria' => ['Part', 'Resp', 'New'], 'max' => 5]);
        $ex = new exporter($this->pf);
        $rows = iterator_to_array($ex->rows('giver'), false);
        $this->assertCount(count($ex->headers()), $rows[0]);
        $this->assertSame('A, x; "B"', $rows[0][3]);
        $this->assertSame(['4', '2', ''], array_slice($rows[0], 5, 3));
    }

    public function test_saved_not_submitted_is_excluded(): void {
        $this->make();
        $this->rm->save($this->u['Anna']->id, $this->u['Ben']->id, [
            $this->q['multi'] => ['0' => '1'], $this->q['long'] => 'x', $this->q['grid'] => ['0' => '1', '1' => '1'],
        ], false);
        $this->assertSame([], iterator_to_array((new exporter($this->pf))->rows('giver'), false));
    }
}
