# Moodle Plugins Directory listing — Peer Feedback (mod_peerfeedback)

Draft text for the plugin record on moodle.org/plugins. Keep `README.md` in sync with this.

## Plugin name
Peer feedback

## Short description (max ~2 sentences)
Students give structured feedback to each other inside teacher-defined groups. Teachers see everything, review and grade the quality of each piece of feedback, and export the results.

## Full description

**Peer feedback** is an activity module for structured peer evaluation in small groups — and for teaching students how to give good feedback.

The teacher writes a set of questions once. Every student then answers those questions about each other member of their group (and, optionally, about themselves). The teacher sees every response with full names, reviews each one, and can grade and comment on it — so the activity can be used purely for peer evaluation, or as an assignment that assesses how well students give feedback.

### Features

- **Groups that belong to the activity**, independent of course groups:
  - tick names and assign them to a group,
  - distribute students randomly (by number of groups or group size),
  - pair everyone in one click (odd numbers get one group of three).
- **Survey-style question types:** single choice, multiple choice, rating scale (1–N), number, short text, long text, criteria grid (several criteria on one scale), and section headings. Required questions are enforced.
- **A friendly answering screen:** students work through their peers one at a time, with progress shown. It works well on phones.
- **Flexible student visibility** (per activity):
  - nothing,
  - only the feedback they gave,
  - the feedback they received (giver hidden),
  - all feedback in their group (giver hidden).
- **Optional self-evaluation.**
- **Teacher review workflow:**
  - two views: feedback *given by* a student, and feedback *received by* a student;
  - "not reviewed" filter, and mark as reviewed, which locks the feedback;
  - reopen when needed;
  - optional grade and comment on every single feedback.
- **Gradebook integration (optional):** the student's grade is the average of the grades their feedback received from the teacher.
- **Export** to Excel, CSV or ODS, organised by giver or by receiver, including teacher grades and comments.
- Open and due dates, activity completion ("submitted feedback for all group members"), backup and restore, and the full Privacy API.

### Typical uses

- Team-project peer assessment.
- Practising and assessing feedback skills (e.g. communication, language or teacher-training courses).
- Pair work reflection.

### Requirements

Moodle 5.2 or later. No external services, no extra libraries.

### Privacy

The plugin stores, per activity:
- group membership,
- each student's answers about each peer, linked to giver and receiver,
- teacher grades and comments.

Nothing is sent outside your Moodle site. All data is covered by the Privacy API (export and deletion).

## Screenshots to capture (from the local test site, demo users only)

1. Group management: list with ticks, groups below.
2. Random / pair dialog.
3. Question editor with several question types.
4. Student answering screen (desktop).
5. Student answering screen (phone width).
6. Teacher review: "Feedback given by" with grade and comment.
7. Teacher review: "Feedback received by".
8. Activity settings: visibility options.
9. Exported spreadsheet.

## Record fields the owner must supply

- **Source control URL:** public GitHub repository `moodle-mod_peerfeedback` (owner's GitHub account).
- **Bug tracker URL:** GitHub Issues of that repository.
- **Documentation URL:** GitHub README or wiki.
- **Maintainer:** Burkay ALGUL (moodle.org account).
- **Licence:** GNU GPL v3 or later. The small footer signature ("eduzhub.ro, designed by Burkay ALGUL") is plain text in the Saira typeface (OFL), converted to outlines; sites can hide it in the admin settings.

## Release checklist

- [ ] moodle-plugin-ci green on GitHub Actions (Moodle 5.2, PHP versions supported by 5.2, PostgreSQL + MariaDB).
- [ ] `version.php`: component, version, requires, maturity `MATURITY_STABLE`, release `1.0.0`.
- [ ] Zip built with top folder `peerfeedback/`.
- [ ] Screenshots uploaded, descriptions pasted, URLs filled in.
