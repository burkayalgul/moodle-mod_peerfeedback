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
 * Activity-owned groups: manual assignment, random distribution and pairing.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_manager {
    /**
     * Constructor.
     *
     * @param \stdClass $pf activity record
     * @param \context_module $context
     */
    public function __construct(
        /** @var \stdClass activity record */
        protected \stdClass $pf,
        /** @var \context_module module context */
        protected \context_module $context,
    ) {
    }

    /**
     * Active enrolled users who can give feedback, sorted by last name.
     *
     * @return \stdClass[] userid => user
     */
    public function eligible_users(): array {
        $fields = 'u.id, ' . implode(', ', array_map(fn($f) => 'u.' . $f, \core_user\fields::get_name_fields()));
        return get_enrolled_users(
            $this->context,
            'mod/peerfeedback:submit',
            0,
            $fields,
            'u.lastname ASC, u.firstname ASC, u.id ASC',
            0,
            0,
            true
        );
    }

    /**
     * Groups ordered by sortorder, each with ->members (int[] userids).
     *
     * @return \stdClass[] groupid => group
     */
    public function get_groups(): array {
        global $DB;
        $groups = $DB->get_records('peerfeedback_group', ['peerfeedbackid' => $this->pf->id], 'sortorder ASC, id ASC');
        foreach ($groups as $g) {
            $g->members = [];
        }
        foreach ($DB->get_records('peerfeedback_member', ['peerfeedbackid' => $this->pf->id]) as $m) {
            if (isset($groups[$m->groupid])) {
                $groups[$m->groupid]->members[] = (int)$m->userid;
            }
        }
        return $groups;
    }

    /**
     * The group a user belongs to.
     *
     * @param int $userid
     * @return \stdClass|null
     */
    public function get_group_of(int $userid): ?\stdClass {
        global $DB;
        $groupid = $DB->get_field('peerfeedback_member', 'groupid', ['peerfeedbackid' => $this->pf->id, 'userid' => $userid]);
        return $groupid ? ($this->get_groups()[$groupid] ?? null) : null;
    }

    /**
     * Create an empty group.
     *
     * @param string|null $name null for the next free "Group N"
     * @return int group id
     */
    public function create_group(?string $name = null): int {
        global $DB;
        $groups = $this->get_groups();
        if ($name === null || trim($name) === '') {
            $used = array_map(fn($g) => $g->name, $groups);
            $n = 1;
            while (in_array(get_string('defaultgroupname', 'peerfeedback', $n), $used, true)) {
                $n++;
            }
            $name = get_string('defaultgroupname', 'peerfeedback', $n);
        }
        $sort = $groups ? max(array_map(fn($g) => (int)$g->sortorder, $groups)) + 1 : 1;
        return $DB->insert_record(
            'peerfeedback_group',
            ['peerfeedbackid' => $this->pf->id, 'name' => trim($name), 'sortorder' => $sort]
        );
    }

    /**
     * Rename a group.
     *
     * @param int $groupid
     * @param string $name
     */
    public function rename_group(int $groupid, string $name): void {
        global $DB;
        $this->require_group($groupid);
        if (trim($name) !== '') {
            $DB->set_field('peerfeedback_group', 'name', trim($name), ['id' => $groupid]);
        }
    }

    /**
     * Delete a group; its members become unassigned.
     *
     * @param int $groupid
     */
    public function delete_group(int $groupid): void {
        global $DB;
        $this->require_group($groupid);
        $DB->delete_records('peerfeedback_member', ['groupid' => $groupid]);
        $DB->delete_records('peerfeedback_group', ['id' => $groupid]);
        $this->cleanup_orphans();
    }

    /**
     * Move users into a group.
     *
     * @param int[] $userids
     * @param int $groupid
     */
    public function assign(array $userids, int $groupid): void {
        $this->require_group($groupid);
        $eligible = $this->eligible_users();
        foreach ($userids as $uid) {
            if (isset($eligible[(int)$uid])) {
                $this->put((int)$uid, $groupid);
            }
        }
        $this->cleanup_orphans();
    }

    /**
     * Remove a user from their group.
     *
     * @param int $userid
     */
    public function unassign(int $userid): void {
        global $DB;
        $DB->delete_records('peerfeedback_member', ['peerfeedbackid' => $this->pf->id, 'userid' => $userid]);
        $this->cleanup_orphans();
    }

    /**
     * Distribute users randomly into new groups with balanced sizes.
     *
     * @param string $mode 'groups' (value = number of groups) or 'size' (value = people per group)
     * @param int $value
     * @param bool $reshuffle true: delete all groups and use everyone; false: only unassigned users
     * @param int|null $seed for repeatable tests
     */
    public function random_assign(string $mode, int $value, bool $reshuffle, ?int $seed = null): void {
        if ($value < 1 || !in_array($mode, ['groups', 'size'], true)) {
            throw new \moodle_exception('err_randomvalue', 'peerfeedback');
        }
        if ($reshuffle) {
            $this->delete_all_groups();
        }
        $pool = $this->unassigned_userids();
        if (!$pool) {
            return;
        }
        $this->shuffle($pool, $seed);
        $count = $mode === 'groups' ? min($value, count($pool)) : (int)ceil(count($pool) / $value);
        $this->fill_groups($pool, max(1, $count));
    }

    /**
     * Replace all groups with random pairs (one group of three if the count is odd).
     *
     * @param int|null $seed
     */
    public function pair_all(?int $seed = null): void {
        $this->delete_all_groups();
        $pool = $this->unassigned_userids();
        if (!$pool) {
            return;
        }
        $this->shuffle($pool, $seed);
        $this->fill_groups($pool, max(1, intdiv(count($pool), 2)));
    }

    /**
     * Replace all groups with one group per student, named after that student.
     */
    public function alone_all(): void {
        $this->delete_all_groups();
        $users = $this->eligible_users();
        foreach ($this->unassigned_userids() as $uid) {
            $this->put($uid, $this->create_group(fullname($users[$uid])));
        }
        $this->cleanup_orphans();
    }

    /**
     * Put only the given students into random pairs (one group of three if the count is odd).
     * Other students and groups stay as they are; groups this empties are removed.
     *
     * @param int[] $userids
     * @param int|null $seed
     */
    public function pair_selected(array $userids, ?int $seed = null): void {
        $pool = $this->take_out($userids);
        if (!$pool) {
            return;
        }
        $this->shuffle($pool, $seed);
        $this->fill_groups($pool, max(1, intdiv(count($pool), 2)));
    }

    /**
     * Give each of the given students a group of their own, named after them.
     * Other students and groups stay as they are; groups this empties are removed.
     *
     * @param int[] $userids
     */
    public function alone_selected(array $userids): void {
        $users = $this->eligible_users();
        foreach ($this->take_out($userids) as $uid) {
            $this->put($uid, $this->create_group(fullname($users[$uid])));
        }
        $this->cleanup_orphans();
    }

    /**
     * Current membership as userid => groupid.
     *
     * @return int[]
     */
    public function membership(): array {
        global $DB;
        return array_map('intval', $DB->get_records_menu(
            'peerfeedback_member',
            ['peerfeedbackid' => $this->pf->id],
            '',
            'userid, groupid'
        ));
    }

    /**
     * How many saved-not-submitted drafts would be discarded if membership became $after. A draft survives only when
     * giver and receiver end up in the same group (a self-evaluation draft survives while its author has a group).
     *
     * @param int[] $after userid => groupid
     * @return int
     */
    public function drafts_lost(array $after): int {
        global $DB;
        $lost = 0;
        $drafts = $DB->get_records(
            'peerfeedback_response',
            ['peerfeedbackid' => $this->pf->id, 'status' => constants::STATUS_SAVED],
            '',
            'id, giverid, receiverid'
        );
        foreach ($drafts as $d) {
            $g = $after[(int)$d->giverid] ?? 0;
            if (!$g || $g !== ($after[(int)$d->receiverid] ?? 0)) {
                $lost++;
            }
        }
        return $lost;
    }

    /**
     * Delete saved (not submitted) responses whose receiver is no longer a peer of the giver.
     *
     * @return int number of responses deleted
     */
    public function cleanup_orphans(): int {
        global $DB;
        $sql = "SELECT r.id
                   FROM {peerfeedback_response} r
              LEFT JOIN {peerfeedback_member} mg ON mg.peerfeedbackid = r.peerfeedbackid AND mg.userid = r.giverid
              LEFT JOIN {peerfeedback_member} mr ON mr.peerfeedbackid = r.peerfeedbackid AND mr.userid = r.receiverid
                  WHERE r.peerfeedbackid = :pfid AND r.status = :saved
                        AND (mg.groupid IS NULL OR mr.groupid IS NULL OR mg.groupid <> mr.groupid
                             OR (r.giverid = r.receiverid AND :selfeval = 0))";
        $ids = $DB->get_fieldset_sql($sql, ['pfid' => $this->pf->id, 'saved' => constants::STATUS_SAVED,
            'selfeval' => (int)$this->pf->selfeval]);
        if ($ids) {
            $DB->delete_records_list('peerfeedback_answer', 'responseid', $ids);
            $DB->delete_records_list('peerfeedback_response', 'id', $ids);
        }
        return count($ids);
    }

    /**
     * Eligible users not in any group, in eligible order.
     *
     * @return int[]
     */
    public function unassigned_userids(): array {
        global $DB;
        $assigned = $DB->get_fieldset_select('peerfeedback_member', 'userid', 'peerfeedbackid = ?', [$this->pf->id]);
        return array_values(array_diff(array_map('intval', array_keys($this->eligible_users())), array_map('intval', $assigned)));
    }

    /**
     * Put users round-robin into $count new groups.
     *
     * @param int[] $pool
     * @param int $count
     */
    protected function fill_groups(array $pool, int $count): void {
        $groupids = [];
        for ($i = 0; $i < $count; $i++) {
            $groupids[] = $this->create_group();
        }
        foreach ($pool as $i => $uid) {
            $this->put($uid, $groupids[$i % $count]);
        }
        $this->cleanup_orphans();
    }

    /**
     * Shuffle in place, optionally seeded.
     *
     * @param array $items
     * @param int|null $seed
     */
    protected function shuffle(array &$items, ?int $seed): void {
        if ($seed === null) {
            for ($i = count($items) - 1; $i > 0; $i--) {
                $j = random_int(0, $i);
                [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
            }
            return;
        }
        mt_srand($seed);
        shuffle($items);
        mt_srand();
    }

    /**
     * Remove the given eligible users from their groups and delete the groups that leaves empty.
     *
     * @param int[] $userids
     * @return int[] the eligible users taken out, in eligible order
     */
    protected function take_out(array $userids): array {
        global $DB;
        $wanted = array_map('intval', $userids);
        $pool = array_values(array_filter(
            array_map('intval', array_keys($this->eligible_users())),
            fn($uid) => in_array($uid, $wanted, true)
        ));
        if (!$pool) {
            return [];
        }
        $membership = $this->membership();
        $oldgroups = array_unique(array_intersect_key($membership, array_flip($pool)));
        [$insql, $params] = $DB->get_in_or_equal($pool, SQL_PARAMS_NAMED);
        $DB->delete_records_select(
            'peerfeedback_member',
            "peerfeedbackid = :pfid AND userid $insql",
            $params + ['pfid' => $this->pf->id]
        );
        foreach ($oldgroups as $groupid) {
            if (!$DB->record_exists('peerfeedback_member', ['groupid' => $groupid])) {
                $DB->delete_records('peerfeedback_group', ['id' => $groupid, 'peerfeedbackid' => $this->pf->id]);
            }
        }
        return $pool;
    }

    /**
     * Set a user's single membership.
     *
     * @param int $userid
     * @param int $groupid
     */
    protected function put(int $userid, int $groupid): void {
        global $DB;
        $existing = $DB->get_record('peerfeedback_member', ['peerfeedbackid' => $this->pf->id, 'userid' => $userid]);
        if ($existing) {
            $DB->set_field('peerfeedback_member', 'groupid', $groupid, ['id' => $existing->id]);
        } else {
            $DB->insert_record(
                'peerfeedback_member',
                ['peerfeedbackid' => $this->pf->id, 'groupid' => $groupid, 'userid' => $userid]
            );
        }
    }

    /**
     * Remove every group and membership.
     */
    protected function delete_all_groups(): void {
        global $DB;
        $DB->delete_records('peerfeedback_member', ['peerfeedbackid' => $this->pf->id]);
        $DB->delete_records('peerfeedback_group', ['peerfeedbackid' => $this->pf->id]);
    }

    /**
     * Ensure a group belongs to this activity.
     *
     * @param int $groupid
     */
    protected function require_group(int $groupid): void {
        global $DB;
        if (!$DB->record_exists('peerfeedback_group', ['id' => $groupid, 'peerfeedbackid' => $this->pf->id])) {
            throw new \moodle_exception('invalidrecord', 'error', '', 'peerfeedback_group');
        }
    }
}
