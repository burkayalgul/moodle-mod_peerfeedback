# Peer feedback (mod_peerfeedback)

Students give structured feedback to each other inside teacher-defined groups. Teachers see everything, review and grade the quality of each piece of feedback, and export the results.

## What it does

- **Groups owned by the activity** (independent of course groups): tick names and assign (alphabetical list with instant search by name or email), distribute randomly (by number of groups or group size), pair everyone in one click, or give everyone a group of their own (for self-evaluation). Pair and "alone" can also be applied to ticked students only.
- **Survey-style questions:** single choice, multiple choice, rating scale (1–N), number, short text, long text, criteria grid and section headings. Required questions are enforced.
- **Answering screen:** students evaluate their group members one at a time, with progress shown. Optional self-evaluation.
- **What students can see** (per activity): nothing, only the feedback they gave, the feedback they received (giver hidden), or all feedback in their group (giver hidden).
- **Teacher review:** students listed group by group with a group filter, "Feedback given by" and "Feedback received by" views, a "not reviewed" filter, an optional grade and comment on each feedback, and "Mark as reviewed" (locks it) / "Reopen".
- **Teacher feedback for students:** an overall comment per student that the teacher chooses when to show, and a "Teacher feedback" list on the student's page with every comment and grade on the feedback they wrote. Students get a Moodle notification when new teacher feedback appears.
- **Gradebook (optional):** a student's grade is the average of the grades their feedback received.
- **Export** by giver or by receiver (Excel, CSV, ODS and the other Moodle formats), including teacher grades and comments.
- Open and due dates, activity completion, backup and restore, Privacy API.

## Requirements

- Moodle 5.2 or later.
- PostgreSQL, MariaDB, MySQL or any other database Moodle supports. No extra libraries, no external services.

## Installation

1. Site administration → Plugins → Install plugins, upload the zip. Or unzip it into `mod/peerfeedback` (`public/mod/peerfeedback` on the Moodle 5.1+ layout).
2. Visit Site administration → Notifications to finish the installation.
3. Optional: Site administration → Plugins → Activity modules → Peer feedback → *Show branding footer*.

## Getting started

1. Add a *Peer feedback* activity to a course and choose what students can see and whether teacher grading is on.
2. Open the **Questions** tab and add your questions.
3. Open the **Groups** tab and build groups (tick names, Random…, or Pair everyone).
4. Students open the activity and give feedback. Teachers use the **Review** tab and **Export**.

## Licence

- GNU GPL v3 or later (see `LICENSE`).
- The footer signature `pix/branding.svg` ("eduzhub.ro, designed by Burkay ALGUL") is text drawn from the Saira typeface (SIL Open Font License 1.1), converted to outlines. Sites can hide it under *Site administration → Plugins → Activity modules → Peer feedback → Show branding footer*.

## Copyright

2026 Burkay ALGUL
