# Peer Feedback — Moodle activity plugin design

Date: 2026-11-12 · Owner: Burkay ALGUL · Target: Moodle 5.2.3+ (Build 20260916), full site-admin access

## Why

The teacher wants students to evaluate each other inside small groups the teacher sets up — and, just as important, to check **whether students know how to give good feedback**. So the plugin is two things at once:

1. A peer-evaluation activity: every student answers the same set of questions about each other member of their group.
2. An assignment-like activity on the *quality of the feedback given*: the teacher reviews each piece of feedback, can grade it and comment on it, and the average goes to the gradebook.

Success: the teacher can set up an activity in a few minutes (groups by ticking names, random, or pairs), students find the answering screen pleasant, the teacher works through a "not reviewed" queue, and exports everything with grades and comments.

## Goals

- New activity module (`mod_peerfeedback`), added to a course like any activity. UI language: **English**.
- Activity-owned groups, **independent of course groups**.
- Teacher-defined questions using survey-style types (below).
- Each student evaluates every other member of their group; optional self-evaluation.
- Teacher sees everything, never anonymous to the teacher.
- Per-feedback review status, grade and comment; optional gradebook average.
- Export in two shapes (by giver / by receiver) including grades and comments.
- Small EduZ logo + "designed by Burkay ALGUL" signature at the bottom of every plugin page.

## Non-goals / anti-goals

- **No quiz question bank / quiz question types.** Agreed with the owner: drag-and-drop, matching, calculated, cloze and "correct answer" short-answer make no sense for peer evaluation.
- No use of course groups/groupings.
- No anonymity toward the teacher.
- Must not touch the live site until the owner installs it; development and testing happen on a local Moodle.

## Activity settings (on the activity form)

| Setting | Values | Default |
|---|---|---|
| Name, description | standard | — |
| Open date / due date | optional, like Assignment | off |
| What students can see | **Nothing** · **Only feedback they gave** · **Feedback they received** · **Everyone's feedback** | Feedback they received |
| Self-evaluation | on / off | off |
| Teacher grading | on / off (when off: no grade column in review, nothing sent to gradebook) | off |
| Send average to gradebook | on / off (only when teacher grading is on) | on |
| Maximum grade | number | 100 |

Visibility rules for students:
- In "Feedback they received" and "Everyone's feedback", the **giver's name is hidden**. Receiver names are shown in "Everyone's feedback".
- "Everyone's feedback" = all submitted feedback **within the student's own group**.
- Visibility applies as soon as feedback is submitted; the teacher can change the setting at any time to hide or reveal.
- Pair caveat (told to the owner): in groups of 2, received feedback is anonymous in name only.

## Question types

Teacher builds the question list per activity (add, edit, reorder, delete; mark required/optional):

- Single choice (radio)
- Multiple choice (checkboxes)
- Rating scale (1–N, N set per question, e.g. 1–5 or 1–10; optional end labels)
- Number (optional min/max)
- Short text
- Long text (comment)
- Criteria grid: several criteria rated on the same scale in one block (e.g. Participation / Communication / Responsibility, each 1–5)
- Section heading / instruction text (no answer)

Every question is asked once per person being evaluated. *Builder's call:* after the first submission, question wording may still be edited but questions cannot be deleted or change type (the form warns and blocks).

## Group management screen (layout A, approved)

- One list of enrolled students (those who can submit) with a checkbox per name and a "Group" column (Unassigned shown in orange).
- Toolbar: **Assign selected to [group ▾] [Assign]**, **+ New group**, **Random…**, **Pair everyone**, rename/delete group.
- Below the list: each group as a box with member chips; ✕ removes a member.
- **Random…** asks for either number of groups or people per group, then distributes all *unassigned* students (option: "re-shuffle everyone").
- **Pair everyone**: pairs all students; with an odd count one group gets 3.
- A student is in at most one group per activity.
- Students in no group see "You have not been assigned to a group yet."
- *Builder's call:* changing groups after submissions keeps existing feedback; feedback whose giver/receiver are no longer in the same group stays visible to the teacher marked "outside current group" and still counts for grading.

## Student answering screen (layout A, approved)

- Peers shown as pill tabs at the top (plus "Yourself" when self-evaluation is on), with a progress bar; finished people get a green tick.
- One person at a time: avatar/initials, name, "2 of 3", then all questions; **Back** / **Save & next**.
- Saving requires all required questions answered; the student can leave and come back. **Submit** per person sends that feedback to the teacher.
- A student may edit a submitted feedback until the teacher marks it reviewed (then it is locked) or the due date passes.
- When teacher grading is on, a student sees the teacher's grade and comment on each feedback they gave, once reviewed.
- Must look good and work on a phone.

## Teacher review screen (approved)

- Toggle at top: **Feedback given by** / **Feedback received by**.
- Left: student list with review progress per student ("1/2", "not submitted").
- Right: one card per feedback, header **"Giver → Receiver"**, the answers, then a review bar: grade box (if grading on) · comment box · **Mark as reviewed**. Status pill Reviewed / Not reviewed.
- Filter: All / Not reviewed / Reviewed.
- Under the cards: the selected student's average grade.
- Grading per individual feedback (giver→receiver pair). Gradebook grade = **average of that student's graded feedbacks** (ungraded ones excluded).

## Export

- From the review screen: **Export ▾ → by giver / by receiver**.
- One row per feedback: giver, receiver, group, each question's answer, submitted time, review status, teacher grade, teacher comment.
- *Builder's call:* use Moodle's built-in download formats (Excel .xlsx default; CSV/ODS also available).

## Branding footer

- On every plugin page, centered at the bottom: the full EduZ logo (colour version, top-left of `Logos.svg` sheet) with "designed by Burkay ALGUL" directly beneath, set in **Saira Medium**, centred, spanning **half the logo's width**. Rendered small (~30 px tall overall).
- Shipped as one combined SVG with the text converted to outlines (no font dependency). Source: `/Users/mk/Downloads/2025-2026/Branding Identity Visual - EduZ - Xite and Heroshot/Logos.svg`, crop viewBox ≈ `186 132 1110 298`.

## Roles and permissions (builder's call)

- Teacher / editing teacher / manager: manage questions, manage groups, review & grade, export.
- Student: submit and view according to the visibility setting.
- Standard Moodle capabilities so admins can adjust.

## Also included (builder's call)

- Activity completion option: "Student must submit feedback for all group members".
- Privacy (GDPR) provider, as Moodle requires for plugins storing personal data.
- Backup/restore support so courses can be copied with the activity (groups and questions; student data with user data).
- Basic event logging (viewed, submitted, reviewed) via standard Moodle events.

## Detailed rules (builder's calls, settled after the builder check)

**Review / grade lifecycle**
- States per feedback: Draft → Submitted → Reviewed. Grade and comment can be saved at any time once Submitted; the gradebook updates on every grade save.
- "Mark as reviewed" locks the feedback for the student. The teacher can **Reopen** a reviewed feedback (unlocks editing, keeps grade and comment).
- A student editing a submitted (not yet reviewed) feedback keeps the existing grade; the card shows "edited after grading".
- The grade always belongs to the **giver** (quality of the feedback they wrote). The average shown in both teacher views is the selected student's average as giver. A student with no graded feedback gets no grade (not 0). Grades are entered on 0..max grade; the gradebook receives the average on the same scale.

**Visibility details**
- "Only feedback they gave": student sees their own feedback with receiver names.
- Students only ever see teacher grades/comments on feedback **they** gave — never on others' feedback.

**Validation and dates**
- **Owner decision:** no empty answers. Both "Save & next" and "Submit" enforce required questions; a saved-but-not-submitted feedback is complete but still editable and not yet sent to the teacher.
- Before the open date the activity is visible but answering is blocked. After the due date, submitting and editing are blocked (no late submissions); drafts stay visible to the teacher as "not submitted".

**Groups**
- Feedback rows store the giver, receiver and the group at the time of submission.
- Drafts for someone no longer in the same group are discarded when groups change (the group screen warns before saving).
- New group members simply appear as new people to evaluate; completion is recomputed against current group membership.
- A group of 1 is allowed (only self-evaluation, if on). "Pair everyone" with one student leaves them alone in a group. Hand-assigned groups may be any size.
- "Students" = active enrolled users with the submit capability; suspended enrolments are hidden from the group screen and lists (their data is kept).

**Question edits after submissions**
- Allowed: wording, help text, end labels, required→optional, adding new choices / criteria, raising scale N.
- Blocked: deleting questions, changing type, removing choices or criteria, lowering scale N.
- New questions may be added; they apply to drafts and future submissions only — submitted feedback is not reopened.

**Export**
- By giver: rows sorted by giver then receiver. By receiver: sorted by receiver then giver. Same columns.
- Multiple-choice answers: one cell, options joined with "; ". Criteria grid: one column per criterion ("Q3 – Communication").

**Branding asset**
- Built from the owner's local `Logos.svg` (path above) and Saira Medium (Google Fonts, OFL), text converted to outlines, committed as `pix/branding.svg`.

## Moodle Plugins Directory (marketplace) release — owner requirement

The owner will publish the plugin on moodle.org/plugins. Everything must meet the Marketplace submission guidelines:
- Component `mod_peerfeedback` (no existing directory plugin with this name found 2026-11-12); repository name `moodle-mod_peerfeedback`.
- GPL v3-or-later boilerplate in every PHP/JS file; `LICENSE` file; `README.md` (what it does, install, post-install steps, supported versions, DB support); `CHANGES.md`.
- English strings only, all via `get_string()`, sentence case (no Capitalised Titles).
- Privacy API provider describing every stored personal field.
- Supported versions declared in `version.php` (`requires` = Moodle 5.2); passes `moodle-plugin-ci` (phplint, phpcs Moodle standard, phpdoc, validate, savepoints, mustache, grunt, PHPUnit, Behat) via a GitHub Actions workflow.
- Listing text (short + full description, features, privacy note), screenshot list, and the URLs the owner must provide (public source repo, bug tracker, documentation) prepared in `docs/marketplace.md`.
- **Branding:** the footer logo is the owner's own trademark. It ships as a separate asset with its licence stated in `README.md` / `thirdpartylibs.xml`-style note; an admin setting **"Show branding footer"** (default on) lets sites hide it, which keeps directory review simple.
- Prior art the reviewers may compare with: *360° feedback* (`mod_threesixo`) and *Workshop* (core). Differentiators to state in the listing: activity-owned groups with random/pair tools, any survey-style question type, teacher grading of feedback quality, two-way export.

## How it gets made

- Plain Moodle activity plugin (PHP, Moodle APIs, Mustache templates, a little JavaScript for the answering and group screens), following Moodle 5.2 coding style.
- Developed and tested against a **local Moodle 5.2 in Docker**; automated tests with PHPUnit (and Behat where useful).
- Delivered as an installable zip for Site administration → Plugins → Install plugins.

## Left to the builder

Database table design, exact file layout, JS framework choice within Moodle's standard tooling (AMD modules), visual styling details within Moodle's Boost theme look.
