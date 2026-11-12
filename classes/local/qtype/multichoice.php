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

namespace mod_peerfeedback\local\qtype;

/**
 * Multiple choice (checkboxes). Stored as a JSON array of option indexes.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class multichoice extends singlechoice {

    #[\Override]
    public function normalise(array $config, mixed $raw): ?string {
        if (!is_array($raw)) {
            return null;
        }
        $picked = [];
        foreach ($raw as $index => $on) {
            if (!empty($on) && ctype_digit((string)$index)) {
                $picked[] = (int)$index;
            }
        }
        sort($picked);
        return $picked ? json_encode(array_values(array_unique($picked))) : null;
    }

    #[\Override]
    protected function check_value(array $config, string $value, bool $required): ?string {
        $picked = json_decode($value, true);
        if (!is_array($picked)) {
            return 'err_invalidoption';
        }
        if (!$picked) {
            return $required ? 'err_required' : null;
        }
        $count = count($config['options'] ?? []);
        foreach ($picked as $i) {
            if (!is_int($i) || $i < 0 || $i >= $count) {
                return 'err_invalidoption';
            }
        }
        return null;
    }

    #[\Override]
    public function display_value(array $config, ?string $value): string {
        $picked = $value === null ? [] : (json_decode($value, true) ?: []);
        $labels = [];
        foreach ($picked as $i) {
            if (isset($config['options'][$i])) {
                $labels[] = $config['options'][$i];
            }
        }
        return implode('; ', $labels);
    }
}
