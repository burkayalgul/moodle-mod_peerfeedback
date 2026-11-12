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
 * Base class for question types. Values are stored as strings; display and export values are plain text.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /** @var string[] Supported types in display order. */
    public const TYPES = ['singlechoice', 'multichoice', 'rating', 'number', 'shorttext', 'longtext', 'grid', 'heading'];

    /**
     * Factory.
     *
     * @param string $qtype
     * @return base
     */
    public static function get(string $qtype): base {
        if (!in_array($qtype, self::TYPES, true)) {
            throw new \coding_exception('Unknown peerfeedback question type: ' . $qtype);
        }
        $class = '\\mod_peerfeedback\\local\\qtype\\' . $qtype;
        return new $class();
    }

    /**
     * All types with their localised names.
     *
     * @return array qtype => name
     */
    public static function all_types(): array {
        $out = [];
        foreach (self::TYPES as $t) {
            $out[$t] = get_string('qtype_' . $t, 'peerfeedback');
        }
        return $out;
    }

    /**
     * Whether the question collects an answer.
     *
     * @return bool
     */
    public function is_answerable(): bool {
        return true;
    }

    /**
     * Convert raw submitted input into the stored value.
     *
     * @param array $config
     * @param mixed $raw
     * @return string|null null when empty
     */
    public function normalise(array $config, mixed $raw): ?string {
        if (!is_scalar($raw)) {
            return null;
        }
        $raw = trim((string)$raw);
        return $raw === '' ? null : $raw;
    }

    /**
     * Validate a stored value.
     *
     * @param array $config
     * @param string|null $value
     * @param bool $required
     * @return string|null error string key or null
     */
    public function validate(array $config, ?string $value, bool $required): ?string {
        if ($value === null) {
            return $required ? 'err_required' : null;
        }
        return $this->check_value($config, $value, $required);
    }

    /**
     * Type-specific validation of a non-null value.
     *
     * @param array $config
     * @param string $value
     * @param bool $required
     * @return string|null
     */
    protected function check_value(array $config, string $value, bool $required): ?string {
        return null;
    }

    /**
     * Export column headers.
     *
     * @param array $config
     * @param string $qlabel
     * @return string[]
     */
    public function export_columns(array $config, string $qlabel): array {
        return [$qlabel];
    }

    /**
     * Export cell values, same length as export_columns().
     *
     * @param array $config
     * @param string|null $value
     * @return string[]
     */
    public function export_values(array $config, ?string $value): array {
        return [$this->display_value($config, $value)];
    }

    /**
     * Human readable plain-text value.
     *
     * @param array $config
     * @param string|null $value
     * @return string
     */
    public function display_value(array $config, ?string $value): string {
        return $value ?? '';
    }

    /**
     * Whether changing config from old to new keeps existing answers valid.
     *
     * @param array $old
     * @param array $new
     * @return bool
     */
    public function config_change_allowed(array $old, array $new): bool {
        return true;
    }

    /**
     * Validate a config.
     *
     * @param array $config
     * @return string|null error string key or null
     */
    public function validate_config(array $config): ?string {
        return null;
    }

    /**
     * True if every item of $old is still at the same position in $new.
     *
     * @param array $old
     * @param array $new
     * @return bool
     */
    protected static function list_extended(array $old, array $new): bool {
        return array_slice(array_values($new), 0, count($old)) === array_values($old);
    }
}
