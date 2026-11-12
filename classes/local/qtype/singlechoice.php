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
 * Single choice (radio buttons).
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class singlechoice extends base {

    #[\Override]
    protected function check_value(array $config, string $value, bool $required): ?string {
        $options = $config['options'] ?? [];
        if (!ctype_digit($value) || (int)$value >= count($options)) {
            return 'err_invalidoption';
        }
        return null;
    }

    #[\Override]
    public function display_value(array $config, ?string $value): string {
        if ($value === null) {
            return '';
        }
        return (string)($config['options'][(int)$value] ?? '');
    }

    #[\Override]
    public function config_change_allowed(array $old, array $new): bool {
        return self::list_extended($old['options'] ?? [], $new['options'] ?? []);
    }

    #[\Override]
    public function validate_config(array $config): ?string {
        return count($config['options'] ?? []) >= 2 ? null : 'err_needoptions';
    }
}
