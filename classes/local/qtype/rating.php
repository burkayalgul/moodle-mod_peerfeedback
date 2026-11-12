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
 * Rating scale 1..max.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rating extends base {

    #[\Override]
    protected function check_value(array $config, string $value, bool $required): ?string {
        $max = (int)($config['max'] ?? 5);
        if (!preg_match('/^\d+$/', $value) || (int)$value < 1 || (int)$value > $max) {
            return 'err_outofrange';
        }
        return null;
    }

    #[\Override]
    public function display_value(array $config, ?string $value): string {
        return $value === null ? '' : $value . ' / ' . (int)($config['max'] ?? 5);
    }

    #[\Override]
    public function export_values(array $config, ?string $value): array {
        return [$value ?? ''];
    }

    #[\Override]
    public function config_change_allowed(array $old, array $new): bool {
        return (int)($new['max'] ?? 5) >= (int)($old['max'] ?? 5);
    }

    #[\Override]
    public function validate_config(array $config): ?string {
        $max = (int)($config['max'] ?? 0);
        return ($max >= 2 && $max <= 10) ? null : 'err_ratingmax';
    }
}
