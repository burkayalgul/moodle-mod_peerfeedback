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
 * Criteria grid: several criteria rated 1..max. Stored as JSON object {criterionindex: rating}.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grid extends base {

    #[\Override]
    public function normalise(array $config, mixed $raw): ?string {
        if (!is_array($raw)) {
            return null;
        }
        $out = [];
        foreach ($raw as $index => $rating) {
            if (ctype_digit((string)$index) && is_scalar($rating) && trim((string)$rating) !== '') {
                $out[(string)(int)$index] = (int)$rating;
            }
        }
        ksort($out);
        return $out ? json_encode((object)$out) : null;
    }

    #[\Override]
    protected function check_value(array $config, string $value, bool $required): ?string {
        $ratings = json_decode($value, true);
        if (!is_array($ratings)) {
            return 'err_outofrange';
        }
        $criteria = $config['criteria'] ?? [];
        $max = (int)($config['max'] ?? 5);
        foreach ($ratings as $i => $r) {
            if (!isset($criteria[(int)$i]) || !is_int($r) || $r < 1 || $r > $max) {
                return 'err_outofrange';
            }
        }
        if ($required && count($ratings) < count($criteria)) {
            return 'err_required';
        }
        return null;
    }

    #[\Override]
    public function export_columns(array $config, string $qlabel): array {
        return array_map(fn($c) => $qlabel . ' – ' . $c, array_values($config['criteria'] ?? []));
    }

    #[\Override]
    public function export_values(array $config, ?string $value): array {
        $ratings = $value === null ? [] : (json_decode($value, true) ?: []);
        $out = [];
        foreach (array_keys(array_values($config['criteria'] ?? [])) as $i) {
            $out[] = isset($ratings[(string)$i]) ? (string)$ratings[(string)$i] : '';
        }
        return $out;
    }

    #[\Override]
    public function display_value(array $config, ?string $value): string {
        $ratings = $value === null ? [] : (json_decode($value, true) ?: []);
        $max = (int)($config['max'] ?? 5);
        $parts = [];
        foreach (array_values($config['criteria'] ?? []) as $i => $c) {
            $parts[] = $c . ': ' . (isset($ratings[(string)$i]) ? $ratings[(string)$i] . ' / ' . $max : '–');
        }
        return implode("\n", $parts);
    }

    #[\Override]
    public function config_change_allowed(array $old, array $new): bool {
        return self::list_extended($old['criteria'] ?? [], $new['criteria'] ?? [])
            && (int)($new['max'] ?? 5) >= (int)($old['max'] ?? 5);
    }

    #[\Override]
    public function validate_config(array $config): ?string {
        if (count($config['criteria'] ?? []) < 1) {
            return 'err_needcriteria';
        }
        $max = (int)($config['max'] ?? 0);
        return ($max >= 2 && $max <= 10) ? null : 'err_ratingmax';
    }
}
