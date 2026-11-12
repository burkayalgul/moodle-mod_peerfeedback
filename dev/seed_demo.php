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
 * Create a demo course for trying mod_peerfeedback locally. Idempotent.
 * Run inside the container: php public/mod/peerfeedback/dev/seed_demo.php
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/enrol/manual/lib.php');

\core\session\manager::set_user(get_admin());

$course = $DB->get_record('course', ['shortname' => 'pfdemo']);
if (!$course) {
    $course = create_course((object)['fullname' => 'Peer feedback demo', 'shortname' => 'pfdemo', 'category' => 1,
        'format' => 'topics', 'numsections' => 1]);
}
$enrol = enrol_get_plugin('manual');
$instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
$roles = ['teacher1' => 'editingteacher'];
$names = ['Ayşe Kaya', 'Mehmet Demir', 'Zeynep Arslan', 'Can Yılmaz', 'Elif Şahin', 'Burak Çelik', 'Deniz Aydın'];
foreach ($names as $i => $n) {
    $roles['student' . ($i + 1)] = 'student';
}
foreach ($roles as $username => $role) {
    $user = $DB->get_record('user', ['username' => $username]);
    if (!$user) {
        [$first, $last] = $username === 'teacher1' ? ['Teacher', 'One'] : explode(' ', $names[(int)substr($username, 7) - 1]);
        $id = user_create_user((object)['username' => $username, 'password' => 'Test123!', 'firstname' => $first,
            'lastname' => $last, 'email' => $username . '@example.com', 'auth' => 'manual', 'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id]);
        $user = $DB->get_record('user', ['id' => $id]);
    }
    $roleid = $DB->get_field('role', 'id', ['shortname' => $role]);
    $enrol->enrol_user($instance, $user->id, $roleid);
}

if (!$DB->record_exists('peerfeedback', ['course' => $course->id])) {
    $moduleinfo = (object)['modulename' => 'peerfeedback', 'course' => $course->id, 'section' => 1, 'visible' => 1,
        'name' => 'Team project feedback',
        'introeditor' => ['text' => 'Give honest, specific feedback to each member of your team.', 'format' => FORMAT_HTML,
            'itemid' => 0], 'visibility' => 2, 'selfeval' => 1, 'grading' => 1, 'gradebook' => 1,
        'grade' => 100, 'timeopen' => 0, 'timedue' => 0, 'completionsubmit' => 0];
    $moduleinfo = create_module($moduleinfo);
    $pf = $DB->get_record('peerfeedback', ['id' => $moduleinfo->instance]);
    $qm = new \mod_peerfeedback\local\question_manager($pf);
    $qm->add('heading', 'Think about the last two weeks of teamwork.', false, []);
    $qm->add('rating', 'How well did this person communicate?', true, ['max' => 5, 'minlabel' => 'Poorly', 'maxlabel' => 'Very well']);
    $qm->add('grid', 'Rate their contribution', true, ['criteria' => ['Participation', 'Responsibility', 'Quality of work'], 'max' => 5]);
    $qm->add('singlechoice', 'Would you work with them again?', true, ['options' => ['Yes', 'Maybe', 'No']]);
    $qm->add('multichoice', 'What were their strengths?', false, ['options' => ['Ideas', 'Organisation', 'Research', 'Presenting']]);
    $qm->add('number', 'Hours they contributed per week (estimate)', false, ['min' => 0, 'max' => 60]);
    $qm->add('shorttext', 'One word to describe their role', false, []);
    $qm->add('longtext', 'What should they improve, and how?', true, []);
}
$cm = get_coursemodule_from_instance('peerfeedback', $DB->get_field('peerfeedback', 'id', ['course' => $course->id]));
echo "SEED: course id {$course->id}, activity cmid {$cm->id}\n";
