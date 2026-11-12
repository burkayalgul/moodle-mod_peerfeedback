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
