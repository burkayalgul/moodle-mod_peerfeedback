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
 * Section heading / instructions. Collects no answer.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class heading extends base {

    #[\Override]
    public function is_answerable(): bool {
        return false;
    }

    #[\Override]
    public function normalise(array $config, mixed $raw): ?string {
        return null;
    }

    #[\Override]
    public function validate(array $config, ?string $value, bool $required): ?string {
        return null;
    }

    #[\Override]
    public function export_columns(array $config, string $qlabel): array {
        return [];
    }

    #[\Override]
    public function export_values(array $config, ?string $value): array {
        return [];
    }
}
