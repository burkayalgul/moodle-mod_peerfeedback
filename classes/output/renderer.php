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

namespace mod_peerfeedback\output;

use mod_peerfeedback\local\qtype\base;

/**
 * Renderer for mod_peerfeedback.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {

    /**
     * Teacher navigation tabs.
     *
     * @param \cm_info $cm
     * @param string $current one of overview, questions, groups, review
     * @return string
     */
    public function tabs(\cm_info $cm, string $current): string {
        $context = \context_module::instance($cm->id);
        $tabs = [];
        $tabs[] = new \tabobject('overview', new \moodle_url('/mod/peerfeedback/view.php', ['id' => $cm->id]),
            get_string('overview', 'peerfeedback'));
        if (has_capability('mod/peerfeedback:managequestions', $context)) {
            $tabs[] = new \tabobject('questions', new \moodle_url('/mod/peerfeedback/questions.php', ['id' => $cm->id]),
                get_string('questions', 'peerfeedback'));
        }
        if (has_capability('mod/peerfeedback:managegroups', $context)) {
            $tabs[] = new \tabobject('groups', new \moodle_url('/mod/peerfeedback/groups.php', ['id' => $cm->id]),
                get_string('groups', 'peerfeedback'));
        }
        if (has_capability('mod/peerfeedback:review', $context)) {
            $tabs[] = new \tabobject('review', new \moodle_url('/mod/peerfeedback/review.php', ['id' => $cm->id]),
                get_string('review', 'peerfeedback'));
        }
        return $this->tabtree($tabs, $current);
    }

    /**
     * Template context for the question list.
     *
     * @param \cm_info $cm
     * @param \stdClass[] $questions
     * @param bool $locked
     * @return array
     */
    public function questions_context(\cm_info $cm, array $questions, bool $locked): array {
        $types = base::all_types();
        $items = [];
        $n = 0;
        $count = count($questions);
        $i = 0;
        foreach ($questions as $q) {
            $i++;
            $answerable = base::get($q->qtype)->is_answerable();
            $url = fn(array $p) => (new \moodle_url('/mod/peerfeedback/questions.php',
                ['id' => $cm->id, 'qid' => $q->id, 'sesskey' => sesskey()] + $p))->out(false);
            $items[] = [
                'number' => $answerable ? 'Q' . (++$n) : '',
                'text' => format_string($q->questiontext),
                'type' => $types[$q->qtype],
                'required' => $answerable && $q->required,
                'editurl' => $url(['action' => 'edit']),
                'upurl' => $i > 1 ? $url(['action' => 'move', 'dir' => -1]) : null,
                'downurl' => $i < $count ? $url(['action' => 'move', 'dir' => 1]) : null,
                'deleteurl' => $locked ? null : $url(['action' => 'delete']),
            ];
        }
        $addtypes = [];
        foreach ($types as $value => $label) {
            $addtypes[] = ['value' => $value, 'label' => $label];
        }
        return [
            'questions' => $items,
            'hasquestions' => !empty($items),
            'locked' => $locked,
            'cmid' => $cm->id,
            'addurl' => (new \moodle_url('/mod/peerfeedback/questions.php'))->out(false),
            'types' => $addtypes,
        ];
    }

    /**
     * Template context for the group management page (layout A).
     *
     * @param \cm_info $cm
     * @param \mod_peerfeedback\local\group_manager $gm
     * @return array
     */
    public function groups_context(\cm_info $cm, \mod_peerfeedback\local\group_manager $gm): array {
        $users = $gm->eligible_users();
        $groups = $gm->get_groups();
        $groupof = [];
        foreach ($groups as $g) {
            foreach ($g->members as $uid) {
                $groupof[$uid] = $g;
            }
        }
        $rows = [];
        foreach ($users as $u) {
            $g = $groupof[$u->id] ?? null;
            $rows[] = ['userid' => $u->id, 'fullname' => fullname($u), 'groupname' => $g ? format_string($g->name) : null];
        }
        $boxes = [];
        $options = [];
        foreach ($groups as $g) {
            $members = [];
            foreach ($g->members as $uid) {
                if (isset($users[$uid])) {
                    $members[] = ['userid' => $uid, 'fullname' => fullname($users[$uid])];
                }
            }
            $boxes[] = ['groupid' => $g->id, 'name' => format_string($g->name), 'count' => count($members),
                'members' => $members, 'empty' => !$members];
            $options[] = ['groupid' => $g->id, 'name' => format_string($g->name)];
        }
        return [
            'actionurl' => (new \moodle_url('/mod/peerfeedback/groups.php'))->out(false),
            'cmid' => $cm->id,
            'sesskey' => sesskey(),
            'users' => $rows,
            'hasusers' => !empty($rows),
            'groups' => $boxes,
            'groupoptions' => $options,
            'unassignedcount' => count($gm->unassigned_userids()),
            'pairurl' => (new \moodle_url('/mod/peerfeedback/groups.php', ['id' => $cm->id, 'action' => 'pair']))->out(false),
        ];
    }

    /**
     * Teacher landing page context.
     *
     * @param \cm_info $cm
     * @param \stdClass $pf
     * @param \context_module $context
     * @return array
     */
    public function overview_context(\cm_info $cm, \stdClass $pf, \context_module $context): array {
        global $DB;
        $gm = new \mod_peerfeedback\local\group_manager($pf, $context);
        $link = fn(string $page) => (new \moodle_url("/mod/peerfeedback/$page.php", ['id' => $cm->id]))->out(false);
        return [
            'students' => count($gm->eligible_users()),
            'unassigned' => count($gm->unassigned_userids()),
            'groups' => count($gm->get_groups()),
            'questions' => $DB->count_records('peerfeedback_question', ['peerfeedbackid' => $pf->id]),
            'submitted' => $DB->count_records_select('peerfeedback_response', 'peerfeedbackid = ? AND status >= 1', [$pf->id]),
            'reviewed' => $DB->count_records('peerfeedback_response', ['peerfeedbackid' => $pf->id, 'status' => 2]),
            'questionsurl' => $link('questions'),
            'groupsurl' => $link('groups'),
            'reviewurl' => $link('review'),
        ];
    }

    /**
     * Student answering screen context (layout A: one person at a time).
     *
     * @param \cm_info $cm
     * @param \stdClass $pf
     * @param \mod_peerfeedback\local\response_manager $rm
     * @param \stdClass[] $peers
     * @param int $peerid current peer
     * @param \stdClass[] $questions
     * @param array|null $posted raw posted answers when re-displaying after errors
     * @param array $errors questionid => error key
     * @return array
     */
    public function answer_context(\cm_info $cm, \stdClass $pf, \mod_peerfeedback\local\response_manager $rm,
            array $peers, int $peerid, array $questions, ?array $posted, array $errors): array {
        global $USER;
        $ctx = ['cmid' => $cm->id, 'sesskey' => sesskey(),
            'actionurl' => (new \moodle_url('/mod/peerfeedback/view.php'))->out(false)];
        if (!$peers) {
            $groups = new \mod_peerfeedback\local\group_manager($pf, \context_module::instance($cm->id));
            $ctx['notice'] = get_string($groups->get_group_of($USER->id) ? 'nopeers' : 'nogroup', 'peerfeedback');
            return $ctx;
        }
        $peerids = array_map('intval', array_keys($peers));
        $tabs = [];
        foreach ($peers as $uid => $u) {
            $r = $rm->get_response($USER->id, $uid);
            $tabs[] = [
                'name' => $uid == $USER->id ? get_string('yourself', 'peerfeedback') : fullname($u),
                'url' => (new \moodle_url('/mod/peerfeedback/view.php', ['id' => $cm->id, 'peer' => $uid]))->out(false),
                'active' => $uid == $peerid,
                'saved' => $r && (int)$r->status === 0,
                'done' => $r && (int)$r->status >= 1,
            ];
        }
        $progress = $rm->progress($USER->id);
        $peer = $peers[$peerid];
        $response = $rm->get_response($USER->id, $peerid);
        $stored = $response ? $response->answers : [];
        $canedit = $rm->can_edit($USER->id, $peerid);
        $notice = null;
        if (!$canedit) {
            if ($response && (int)$response->status === 2) {
                $notice = get_string('locked', 'peerfeedback');
            } else if (!empty($pf->timeopen) && $pf->timeopen > time()) {
                $notice = get_string('notopen', 'peerfeedback', userdate($pf->timeopen));
            } else {
                $notice = get_string('closed', 'peerfeedback');
            }
        }
        $items = [];
        $n = 0;
        foreach ($questions as $q) {
            $type = \mod_peerfeedback\local\qtype\base::get($q->qtype);
            $raw = $posted !== null ? ($posted[$q->id] ?? null) : null;
            $value = $posted !== null ? $type->normalise($q->config, $raw) : ($stored[$q->id] ?? null);
            $items[] = $this->question_item($q, $type->is_answerable() ? 'Q' . (++$n) : '', $value,
                isset($errors[$q->id]) ? get_string($errors[$q->id], 'peerfeedback') : null, !$canedit);
        }
        $pos = array_search($peerid, $peerids, true);
        $statuskey = !$response ? 'statusnotstarted' : ['statussaved', 'statussubmitted', 'statusreviewed'][(int)$response->status];
        $review = null;
        if ($pf->grading && $response && (int)$response->status === 2) {
            $review = [
                'grade' => $response->grade === null ? null : format_float($response->grade, 2, true, true) . ' / ' . $pf->grade,
                'comment' => $response->teachercomment === null ? null : format_text($response->teachercomment, FORMAT_PLAIN),
            ];
        }
        return $ctx + [
            'haspeers' => true,
            'tabs' => $tabs,
            'percent' => $progress['total'] ? round(100 * $progress['done'] / $progress['total']) : 0,
            'progresstext' => get_string('progress', 'peerfeedback', (object)$progress),
            'peerid' => $peerid,
            'peername' => $peerid == $USER->id ? get_string('yourself', 'peerfeedback') : fullname($peer),
            'initials' => \core_text::strtoupper(\core_text::substr($peer->firstname, 0, 1) . \core_text::substr($peer->lastname, 0, 1)),
            'position' => get_string('position', 'peerfeedback', (object)['n' => $pos + 1, 'total' => count($peers)]),
            'status' => get_string($statuskey, 'peerfeedback'),
            'questions' => $items,
            'readonly' => !$canedit,
            'lockednotice' => $notice,
            'review' => $review,
            'backurl' => $pos > 0 ? $tabs[$pos - 1]['url'] : null,
            'haserrors' => !empty($errors),
        ];
    }

    /**
     * Template data for one question on the answering screen.
     *
     * @param \stdClass $q question with ->config
     * @param string $number
     * @param string|null $value stored value
     * @param string|null $error
     * @param bool $readonly
     * @return array
     */
    protected function question_item(\stdClass $q, string $number, ?string $value, ?string $error, bool $readonly): array {
        $c = $q->config;
        $item = ['id' => $q->id, 'name' => 'q' . $q->id, 'number' => $number, 'text' => format_string($q->questiontext),
            'required' => (bool)$q->required, 'error' => $error, 'readonly' => $readonly, 'is' . $q->qtype => true];
        switch ($q->qtype) {
            case 'rating':
                $item['options'] = array_map(fn($i) => ['value' => $i, 'checked' => (string)$i === $value], range(1, (int)$c['max']));
                $item['minlabel'] = $c['minlabel'] ?? '';
                $item['maxlabel'] = $c['maxlabel'] ?? '';
                break;
            case 'singlechoice':
            case 'multichoice':
                $picked = $q->qtype === 'multichoice' ? (json_decode($value ?? '[]', true) ?: []) : [];
                $item['options'] = [];
                foreach ($c['options'] as $i => $label) {
                    $item['options'][] = ['index' => $i, 'label' => format_string($label),
                        'checked' => $q->qtype === 'singlechoice' ? (string)$i === $value : in_array($i, $picked, true)];
                }
                break;
            case 'grid':
                $ratings = json_decode($value ?? '{}', true) ?: [];
                $item['scale'] = range(1, (int)$c['max']);
                $item['criteria'] = [];
                foreach ($c['criteria'] as $i => $label) {
                    $item['criteria'][] = ['index' => $i, 'label' => format_string($label), 'options' => array_map(
                        fn($v) => ['value' => $v, 'checked' => (int)($ratings[(string)$i] ?? 0) === $v], range(1, (int)$c['max']))];
                }
                break;
            case 'number':
                $item['min'] = $c['min'] ?? null;
                $item['max'] = $c['max'] ?? null;
                $item['value'] = $value;
                break;
            default:
                $item['value'] = $value;
        }
        return $item;
    }

    /**
     * Context for the feedback a student may see; null when the mode is "nothing".
     *
     * @param \stdClass $pf
     * @param int $userid
     * @return array|null
     */
    public function student_results_context(\stdClass $pf, int $userid): ?array {
        $mode = (int)$pf->visibility;
        if ($mode === \mod_peerfeedback\local\constants::VIS_NONE) {
            return null;
        }
        $items = [];
        foreach ((new \mod_peerfeedback\local\student_view($pf))->for_user($userid) as $i => $item) {
            $title = $item['receivername'] === null
                ? get_string('feedbacknumber', 'peerfeedback', $i + 1)
                : get_string('feedbackabout', 'peerfeedback', $item['receivername']);
            $items[] = [
                'title' => $title,
                'answers' => $item['answers'],
                'grade' => $item['grade'] === null ? null : format_float($item['grade'], 2, true, true) . ' / ' . $pf->grade,
                'teachercomment' => $item['teachercomment'],
            ];
        }
        $heading = ['', 'resultsgiven', 'resultsreceived', 'resultsall'][$mode];
        return ['heading' => get_string($heading, 'peerfeedback'), 'items' => $items, 'hasitems' => !empty($items)];
    }

    /**
     * Small EduZ logo + signature shown at the bottom of every plugin page.
     *
     * @return string
     */
    public function branding_footer(): string {
        global $CFG;
        if (!get_config('mod_peerfeedback', 'showbranding')) {
            return '';
        }
        if (!file_exists($CFG->dirroot . '/mod/peerfeedback/pix/branding.svg')) {
            return '';
        }
        return $this->render_from_template('mod_peerfeedback/footer_branding', [
            'src' => (new \moodle_url('/mod/peerfeedback/pix/branding.svg'))->out(false),
            'alt' => get_string('brandingalt', 'peerfeedback'),
        ]);
    }
}
