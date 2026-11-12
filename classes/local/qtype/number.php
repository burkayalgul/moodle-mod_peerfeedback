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
 * Number with optional bounds.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class number extends base {
    #[\Override]
    public function normalise(array $config, mixed $raw): ?string {
        $value = parent::normalise($config, $raw);
        return $value === null ? null : str_replace(',', '.', $value);
    }

    #[\Override]
    protected function check_value(array $config, string $value, bool $required): ?string {
        if (!is_numeric($value)) {
            return 'err_notnumber';
        }
        $n = (float)$value;
        $min = $config['min'] ?? null;
        $max = $config['max'] ?? null;
        if (($min !== null && $min !== '' && $n < (float)$min) || ($max !== null && $max !== '' && $n > (float)$max)) {
            return 'err_outofrange';
        }
        return null;
    }

    #[\Override]
    public function validate_config(array $config): ?string {
        $min = $config['min'] ?? null;
        $max = $config['max'] ?? null;
        if ($min !== null && $min !== '' && $max !== null && $max !== '' && (float)$min > (float)$max) {
            return 'err_minmax';
        }
        return null;
    }
}
