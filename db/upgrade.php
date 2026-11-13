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

/**
 * Upgrade steps for mod_peerfeedback.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute mod_peerfeedback upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_peerfeedback_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026111201) {
        // Keep the group name with each feedback so exports still show it after regrouping.
        $table = new xmldb_table('peerfeedback_response');
        $field = new xmldb_field('groupname', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'groupid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $DB->execute("UPDATE {peerfeedback_response}
                         SET groupname = (SELECT g.name FROM {peerfeedback_group} g WHERE g.id = {peerfeedback_response}.groupid)
                       WHERE groupid > 0");
        upgrade_mod_savepoint(true, 2026111201, 'peerfeedback');
    }

    if ($oldversion < 2026111303) {
        // The teacher's overall comment to each student.
        $table = new xmldb_table('peerfeedback_overall');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('peerfeedbackid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('comment', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('shown', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('teacherid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('peerfeedbackid', XMLDB_KEY_FOREIGN, ['peerfeedbackid'], 'peerfeedback', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('teacherid', XMLDB_KEY_FOREIGN, ['teacherid'], 'user', ['id']);
        $table->add_index('pfuser', XMLDB_INDEX_UNIQUE, ['peerfeedbackid', 'userid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 2026111303, 'peerfeedback');
    }

    return true;
}
