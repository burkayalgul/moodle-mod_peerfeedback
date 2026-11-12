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

namespace mod_peerfeedback\local;

/**
 * Shared constants.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class constants {
    /** @var int Students see nothing. */
    public const VIS_NONE = 0;
    /** @var int Students see only feedback they gave. */
    public const VIS_GIVEN = 1;
    /** @var int Students see feedback about themselves (giver hidden). */
    public const VIS_RECEIVED = 2;
    /** @var int Students see all feedback in their group (givers hidden). */
    public const VIS_ALL = 3;

    /** @var int Saved, complete, not yet sent to the teacher. */
    public const STATUS_SAVED = 0;
    /** @var int Submitted to the teacher. */
    public const STATUS_SUBMITTED = 1;
    /** @var int Reviewed by the teacher (locked for the student). */
    public const STATUS_REVIEWED = 2;
}
