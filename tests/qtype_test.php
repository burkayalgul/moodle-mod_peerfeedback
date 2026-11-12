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

use mod_peerfeedback\local\qtype\base;

/**
 * Tests for question types.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(base::class)]
final class qtype_test extends \basic_testcase {
    /** @var array sample configs per answerable type */
    private const CONFIGS = [
        'singlechoice' => ['options' => ['A', 'B']],
        'multichoice' => ['options' => ['A', 'B']],
        'rating' => ['max' => 5],
        'number' => ['min' => null, 'max' => null],
        'shorttext' => [],
        'longtext' => [],
        'grid' => ['criteria' => ['X', 'Y'], 'max' => 5],
    ];

    public function test_required_empty_rejected_for_every_answerable_type(): void {
        foreach (self::CONFIGS as $type => $cfg) {
            $q = base::get($type);
            $this->assertTrue($q->is_answerable(), $type);
            $this->assertSame('err_required', $q->validate($cfg, null, true), $type);
            $this->assertSame(null, $q->validate($cfg, null, false), $type);
            $this->assertSame(null, $q->normalise($cfg, ''), $type);
        }
        $this->assertFalse(base::get('heading')->is_answerable());
    }

    public function test_singlechoice_rejects_unknown_index(): void {
        $q = base::get('singlechoice');
        $this->assertSame('err_invalidoption', $q->validate(['options' => ['A', 'B']], '2', true));
        $this->assertNull($q->validate(['options' => ['A', 'B']], '1', true));
    }

    public function test_rating_range(): void {
        $q = base::get('rating');
        $this->assertSame('err_outofrange', $q->validate(['max' => 5], '0', true));
        $this->assertSame('err_outofrange', $q->validate(['max' => 5], '6', true));
        $this->assertNull($q->validate(['max' => 5], '5', true));
    }

    public function test_number_bounds(): void {
        $q = base::get('number');
        $cfg = ['min' => 1, 'max' => 10];
        $this->assertSame('err_outofrange', $q->validate($cfg, '11', true));
        $this->assertSame('err_notnumber', $q->validate($cfg, 'abc', true));
        $this->assertNull($q->validate($cfg, '2.5', true));
    }

    public function test_grid_requires_every_criterion_when_required(): void {
        $q = base::get('grid');
        $cfg = ['criteria' => ['a', 'b', 'c'], 'max' => 5];
        $this->assertSame('err_required', $q->validate($cfg, '{"0":3,"1":4}', true));
        $this->assertNull($q->validate($cfg, '{"0":3,"1":4,"2":1}', true));
        $this->assertSame('{"0":3,"1":4}', $q->normalise($cfg, ['0' => '3', '1' => '4', '2' => '']));
    }

    public function test_multichoice_export_joins_with_semicolon(): void {
        $q = base::get('multichoice');
        $cfg = ['options' => ['Red', 'Blue, dark', 'Green']];
        $this->assertSame('[0,1]', $q->normalise($cfg, ['0' => '1', '1' => '1']));
        $this->assertSame(['Red; Blue, dark'], $q->export_values($cfg, '[0,1]'));
    }

    public function test_grid_export_one_column_per_criterion(): void {
        $q = base::get('grid');
        $cfg = ['criteria' => ['Participation', 'Communication'], 'max' => 5];
        $this->assertSame(['Q3 – Participation', 'Q3 – Communication'], $q->export_columns($cfg, 'Q3'));
        $this->assertSame(['4', ''], $q->export_values($cfg, '{"0":4}'));
    }

    public function test_config_change_rules(): void {
        $c = base::get('singlechoice');
        $this->assertTrue($c->config_change_allowed(['options' => ['A']], ['options' => ['A', 'B']]));
        $this->assertFalse($c->config_change_allowed(['options' => ['A', 'B']], ['options' => ['A']]));
        $r = base::get('rating');
        $this->assertTrue($r->config_change_allowed(['max' => 5], ['max' => 7]));
        $this->assertFalse($r->config_change_allowed(['max' => 5], ['max' => 4]));
        $g = base::get('grid');
        $this->assertTrue($g->config_change_allowed(['criteria' => ['a'], 'max' => 5], ['criteria' => ['a', 'b'], 'max' => 5]));
        $this->assertFalse($g->config_change_allowed(['criteria' => ['a', 'b'], 'max' => 5], ['criteria' => ['a'], 'max' => 5]));
        $this->assertTrue($g->config_change_allowed(['criteria' => ['a'], 'max' => 5], ['criteria' => ['a'], 'max' => 6]));
        $this->assertFalse($g->config_change_allowed(['criteria' => ['a'], 'max' => 5], ['criteria' => ['a'], 'max' => 4]));
    }

    public function test_unknown_type_throws(): void {
        $this->expectException(\coding_exception::class);
        base::get('dragdrop');
    }
}
