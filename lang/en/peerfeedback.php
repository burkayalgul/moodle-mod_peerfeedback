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
 * English strings for mod_peerfeedback.
 *
 * @package    mod_peerfeedback
 * @copyright  2026 Burkay ALGUL
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['completionsubmit'] = 'Student must submit feedback for all group members';
$string['err_dueafteropen'] = 'The due date must be after the open date.';
$string['err_maxgrade'] = 'Enter a whole number between 1 and 10000.';
$string['feedbacksettings'] = 'Feedback settings';
$string['gradebook'] = 'Send average grade to gradebook';
$string['grading'] = 'Teacher grades each feedback';
$string['grading_help'] = 'When enabled, the teacher can give a grade to every piece of feedback a student writes. Use this to assess how well students give feedback.';
$string['maxgrade'] = 'Maximum grade';
$string['modulename'] = 'Peer feedback';
$string['modulename_help'] = 'Students answer a set of questions about each other member of their group. Teachers see all feedback, can review and grade it, and can export the results.';
$string['modulenameplural'] = 'Peer feedback';
$string['peerfeedback:addinstance'] = 'Add a new peer feedback activity';
$string['peerfeedback:export'] = 'Export peer feedback';
$string['peerfeedback:managegroups'] = 'Manage peer feedback groups';
$string['peerfeedback:managequestions'] = 'Manage peer feedback questions';
$string['peerfeedback:review'] = 'Review and grade peer feedback';
$string['peerfeedback:submit'] = 'Give peer feedback';
$string['peerfeedback:view'] = 'View peer feedback activity';
$string['pluginadministration'] = 'Peer feedback administration';
$string['pluginname'] = 'Peer feedback';
$string['selfeval'] = 'Students also evaluate themselves';
$string['selfeval_help'] = 'When enabled, each student answers the questions about themselves as well as about their group members.';
$string['showbranding'] = 'Show branding footer';
$string['showbranding_desc'] = 'Show the small EduZ logo and author signature at the bottom of peer feedback pages.';
$string['teachergrading'] = 'Teacher grading';
$string['timedue'] = 'Due date';
$string['timeopen'] = 'Open date';
$string['visall'] = 'All feedback in their group (names of givers hidden)';
$string['visgiven'] = 'Only feedback they gave';
$string['visibility'] = 'What students can see';
$string['visibility_help'] = 'Choose what students see after feedback is submitted. Givers are always hidden from students when they look at feedback about someone. Teachers always see everything.';
$string['visnone'] = 'Nothing';
$string['visreceived'] = 'Feedback they received (names of givers hidden)';
$string['err_invalidoption'] = 'Choose one of the listed options.';
$string['err_minmax'] = 'The minimum must not be larger than the maximum.';
$string['err_needcriteria'] = 'Enter at least one criterion.';
$string['err_needoptions'] = 'Enter at least two options.';
$string['err_notnumber'] = 'Enter a number.';
$string['err_outofrange'] = 'This value is out of range.';
$string['err_ratingmax'] = 'The scale must go up to a number between 2 and 10.';
$string['err_required'] = 'This question is required.';
$string['err_toolong'] = 'This answer is too long (maximum 255 characters).';
$string['qtype_grid'] = 'Criteria grid';
$string['qtype_heading'] = 'Section heading';
$string['qtype_longtext'] = 'Long text';
$string['qtype_multichoice'] = 'Multiple choice (checkboxes)';
$string['qtype_number'] = 'Number';
$string['qtype_rating'] = 'Rating scale';
$string['qtype_shorttext'] = 'Short text';
$string['qtype_singlechoice'] = 'Single choice';
$string['err_configlocked'] = 'Feedback has already been submitted, so options, criteria and scales can only be extended, not removed or reduced.';
$string['err_deletelocked'] = 'Feedback has already been submitted, so questions can no longer be deleted.';
$string['addquestion'] = 'Add question';
$string['brandingalt'] = 'EduZ Learning × Development HUB, designed by Burkay ALGUL';
$string['confirmdeletequestion'] = 'Delete this question?';
$string['criterialines'] = 'Criteria (one per line)';
$string['editquestion'] = 'Edit question';
$string['groups'] = 'Groups';
$string['headingtext'] = 'Heading or instructions';
$string['maxlabel'] = 'Label for the highest value (optional)';
$string['maxvalue'] = 'Maximum (optional)';
$string['minlabel'] = 'Label for the lowest value (optional)';
$string['minvalue'] = 'Minimum (optional)';
$string['noquestions'] = 'No questions yet. Add the first one above.';
$string['optionslines'] = 'Options (one per line)';
$string['overview'] = 'Overview';
$string['questionlockednote'] = 'Feedback has already been submitted. You can edit the wording and add options or criteria, but not remove them.';
$string['questions'] = 'Questions';
$string['questionslocked'] = 'Feedback has already been submitted. Questions can be edited but no longer deleted.';
$string['questiontext'] = 'Question';
$string['questiontype'] = 'Question type';
$string['requiredquestion'] = 'Required';
$string['review'] = 'Review';
$string['scalemax'] = 'Scale goes from 1 to';
$string['defaultgroupname'] = 'Group {$a}';
$string['err_randomvalue'] = 'Enter a number of 1 or more.';
$string['anewgroup'] = 'A new group';
$string['assign'] = 'Assign';
$string['assignselectedto'] = 'Assign selected to';
$string['confirmpair'] = 'This replaces all current groups with random pairs (one group of three if the number of students is odd). Continue?';
$string['deletegroup'] = 'Delete group';
$string['distribute'] = 'Distribute';
$string['draftsdiscarded'] = '{$a} saved but not submitted feedback no longer matched the new groups and was discarded.';
$string['emptygroup'] = 'Empty';
$string['group'] = 'Group';
$string['groupname'] = 'Group name';
$string['newgroup'] = 'New group';
$string['noneselected'] = 'Select at least one student first.';
$string['nostudents'] = 'There are no students enrolled who can give feedback.';
$string['number'] = 'Number';
$string['paireveryone'] = 'Pair everyone';
$string['random'] = 'Random…';
$string['randomgroups'] = 'Number of groups';
$string['randomhelp'] = 'Distributes the {$a} unassigned students into new groups of balanced size. Tick "Re-shuffle everyone" to start over with all students.';
$string['randomsize'] = 'People per group';
$string['removefromgroup'] = 'Remove from group';
$string['rename'] = 'Rename';
$string['reshuffle'] = 'Re-shuffle everyone';
$string['student'] = 'Student';
$string['unassigned'] = 'Unassigned';
$string['err_notallowed'] = 'You cannot change this feedback now.';
$string['eventresponsesubmitted'] = 'Peer feedback submitted';
$string['back'] = 'Back';
$string['closed'] = 'This activity is closed. You can no longer change your feedback.';
$string['feedbacksubmitted'] = 'Feedback submitted';
$string['locked'] = 'Your teacher has reviewed this feedback, so it can no longer be changed.';
$string['nogroup'] = 'You have not been assigned to a group yet.';
$string['nopeers'] = 'There is nobody else in your group to give feedback to yet.';
$string['notopen'] = 'This activity opens on {$a}.';
$string['overviewgroups'] = 'Groups';
$string['overviewquestions'] = 'Questions';
$string['overviewreviewed'] = 'Reviewed';
$string['overviewstudents'] = 'Students';
$string['overviewsubmitted'] = 'Feedback submitted';
$string['overviewunassigned'] = '{$a} not in a group';
$string['position'] = '{$a->n} of {$a->total}';
$string['progress'] = '{$a->done} of {$a->total} submitted';
$string['savenext'] = 'Save and next';
$string['statusnotstarted'] = 'Not started';
$string['statusreviewed'] = 'Reviewed';
$string['statussaved'] = 'Saved, not submitted';
$string['statussubmitted'] = 'Submitted';
$string['submit'] = 'Submit';
$string['teachercomment'] = 'Teacher comment';
$string['teachergrade'] = 'Teacher grade';
$string['teacherreview'] = 'Teacher review';
$string['yourself'] = 'Yourself';
$string['fixerrors'] = 'Please answer the questions marked below.';
$string['peers'] = 'People to give feedback to';
$string['feedbackabout'] = 'About {$a}';
$string['feedbacknumber'] = 'Feedback {$a}';
$string['nofeedbackyet'] = 'Nothing to show yet.';
$string['resultsall'] = 'Feedback in your group';
$string['resultsgiven'] = 'Feedback you gave';
$string['resultsreceived'] = 'Feedback about you';
