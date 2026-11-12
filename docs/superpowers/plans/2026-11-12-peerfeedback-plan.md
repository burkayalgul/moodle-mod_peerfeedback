# Peer Feedback (mod_peerfeedback) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a Moodle 5.2 activity module where students answer teacher-defined questions about each other inside activity-owned groups. Teachers review, grade and export the answers. Ship it to a local Docker Moodle so the owner can try it before marketplace publishing.

**Architecture:**
- A standard activity module with these PHP layers:
  - Pages: `view.php`, `groups.php`, `questions.php`, `review.php`, `export.php`.
  - Logic: managers in `classes/local/`.
  - Question types: one class per type under `classes/local/qtype/`.
  - Moodle integrations: grades, completion, privacy, backup and events.
- Pages are thin. They check capabilities, call a manager, then render Mustache templates.
- No custom JavaScript framework. Forms are plain POST, with a few lines of AMD JS where noted.
- The plugin code lives in this repo's root. It is bind-mounted into a moodle-docker site.

**Tech Stack:**
- Moodle 5.2 (MOODLE_502_STABLE) and PHP 8.3.
- PostgreSQL, through moodlehq/moodle-docker.
- PHPUnit and Behat as Moodle runs them.
- Mustache, Moodle forms and `core\dataformat`.
- Python 3 + fontTools for the one-off branding build.

**Spec:** `docs/superpowers/specs/2026-11-12-peerfeedback-design.md` (read it; the "Detailed rules" section is binding). Listing draft: `docs/marketplace.md`.

## Global Constraints

- Component: `mod_peerfeedback`. Every table, class namespace, setting and capability is prefixed with it, the frankenstyle convention.
- `version.php` sets `requires` to the Moodle 5.2 version number from the docker checkout's `version.php`, maturity `MATURITY_ALPHA`, and release `0.1.0`. Until the marketplace release (out of scope here), no other values.
- The UI is in English only. Every string goes through `get_string()`, uses sentence case, and contains no concatenation inside `lang/en/peerfeedback.php`.
- Every PHP/JS/Mustache file carries the Moodle GPL v3-or-later boilerplate header.
- Defaults:

  | Setting | Default |
  |---|---|
  | Visibility | 2 (feedback they received) |
  | Self-evaluation | off |
  | Teacher grading | off |
  | Send to gradebook | on |
  | Max grade | 100 |

- Visibility constants are 0 none, 1 given, 2 received, 3 all (own group).
- Response status constants are 0 saved, 1 submitted, 2 reviewed.
- No empty answers. Save and Submit both enforce required questions.
- Students never receive the giver's identity for feedback about them in visibility modes 2 and 3. That means not in the HTML and not in the template context.
- Teachers are never anonymous-filtered.
- The live site is never touched. Everything runs against `http://localhost:8000`.

## Review Focus

1. **Forged POST.** A student posts a `receiverid` outside their current group (or someone else's `giverid`). This must be rejected with an exception, and nothing may be stored. Covered in Task 5, `test_save_rejects_receiver_outside_group`.
2. **Locked feedback.** Editing after the teacher marked a response reviewed, after `timedue`, or before `timeopen` must be rejected. Covered in Task 5, `test_save_rejected_when_reviewed_or_closed`.
3. **Regrouping after work exists.** When groups change after submissions:
   - submitted responses keep their stored `groupid` and still count for grading;
   - saved-not-submitted responses for people who are no longer peers are deleted.

   Covered in Task 4, `test_regroup_discards_orphan_saved_keeps_submitted`.
4. **Giver identity leak.** In visibility modes 2 and 3, the student-facing data must not contain the giver id or name. Covered in Task 7, `test_received_view_has_no_giver_identity`.
5. **Awkward answers in export.** Multi-select and grid answers, commas, quotes, newlines and options that were later added must export without shifting columns. Covered in Task 9, `test_export_flattens_multi_and_grid`.

---

## File map

| File | Responsibility |
|---|---|
| `version.php`, `lib.php`, `mod_form.php`, `index.php`, `settings.php` | Moodle activity plumbing, settings form, grade callbacks, admin setting `showbranding` |
| `db/install.xml`, `db/access.php`, `db/upgrade.php` | Schema, capabilities |
| `lang/en/peerfeedback.php` | All strings |
| `classes/local/qtype/{base,singlechoice,multichoice,rating,number,shorttext,longtext,grid,heading}.php` | Per-type validation, normalisation, export columns, config-change rules |
| `classes/local/question_manager.php` | CRUD + ordering of questions |
| `classes/local/group_manager.php` | Activity groups, assign, random, pairs, orphan cleanup |
| `classes/local/response_manager.php` | Peers, save/submit, review, reopen, grade average |
| `classes/local/student_view.php` | What a student may see per visibility mode |
| `classes/local/exporter.php` | Export rows by giver/receiver |
| `classes/form/question_form.php` | Moodle form for one question |
| `classes/output/renderer.php` + `templates/*.mustache` | Pages + branding footer |
| `classes/completion/custom_completion.php` | "Submitted for all group members" rule |
| `classes/privacy/provider.php` | Privacy API |
| `classes/event/{course_module_viewed,response_submitted,response_reviewed}.php` | Events |
| `backup/moodle2/*` | Backup/restore |
| `view.php`, `groups.php`, `questions.php`, `review.php`, `export.php` | Pages |
| `styles.css`, `pix/icon.svg`, `pix/branding.svg` | Styling, activity icon, footer image |
| `tools/build_branding.py` | Builds `pix/branding.svg` from Logos.svg + Saira Medium (not shipped in zip) |
| `dev/*.sh`, `dev/seed_demo.php` | Local docker helpers (not shipped in zip) |
| `tests/*_test.php`, `tests/generator/lib.php`, `tests/behat/*.feature` | Tests |
| `README.md`, `LICENSE`, `CHANGES.md`, `.github/workflows/ci.yml` | Release hygiene |

## Data model (`db/install.xml`)

| Table | Fields |
|---|---|
| `peerfeedback` | id, course, name, intro, introformat, timeopen (int, 0 = none), timedue (int, 0 = none), visibility (int 1, default 2), selfeval (int 1, 0), grading (int 1, 0), gradebook (int 1, 1), grade (int 10, 100), completionsubmit (int 1, 0), timecreated, timemodified |
| `peerfeedback_group` | id, peerfeedbackid, name (char 255), sortorder |
| `peerfeedback_member` | id, peerfeedbackid, groupid, userid. Unique (peerfeedbackid, userid) |
| `peerfeedback_question` | id, peerfeedbackid, qtype (char 20), questiontext (text), required (int 1), sortorder, configdata (text, JSON) |
| `peerfeedback_response` | id, peerfeedbackid, giverid, receiverid, groupid, status (int 1), grade (number 10,5, null), teachercomment (text, null), editedaftergrade (int 1, 0), reviewerid (null), timecreated, timemodified, timesubmitted (null), timereviewed (null). Unique (peerfeedbackid, giverid, receiverid) |
| `peerfeedback_answer` | id, responseid, questionid, value (text). Unique (responseid, questionid) |

Per-type `configdata` JSON:

| Type | Config |
|---|---|
| `singlechoice` / `multichoice` | `{"options":["…"]}` |
| `rating` | `{"max":5,"minlabel":"","maxlabel":""}`, max 2..10 |
| `number` | `{"min":null,"max":null}` |
| `grid` | `{"criteria":["…"],"max":5}` |
| `shorttext` / `longtext` / `heading` | `{}` |

Stored `value` per type:

| Type | Stored value |
|---|---|
| `singlechoice` | option index as a string |
| `multichoice` | JSON array of indexes |
| `rating` | integer string |
| `number` | numeric string |
| text types | the text |
| `grid` | JSON object `{criterionindex: int}` |

---

### Task 0: Local Moodle 5.2 dev environment

**Files:**
- Create: `dev/env.sh`, `dev/up.sh`, `dev/phpunit.sh`, `dev/behat.sh`, `dev/local.yml.tpl`, `.gitignore` (add `dev/local.yml`)

**Interfaces:**
- Produces:
  - `dev/phpunit.sh <testfile-relative-to-plugin|--filter X>`: runs PHPUnit inside the container and exits non-zero on failure.
  - `dev/behat.sh <tag>`: runs Behat for the given tag.
  - `dev/up.sh`: starts the site and is idempotent.
  - `$PLUGIN_PATH_IN_MOODLE`, exported by `dev/env.sh`: the mount path, either `public/mod/peerfeedback` or `mod/peerfeedback`.

- [ ] **Step 1:** In `dev/env.sh`, set `DEV_HOME=$HOME/moodle-dev`, `MOODLE_DOCKER_WWWROOT=$DEV_HOME/moodle`, `MOODLE_DOCKER_DB=pgsql`, `MOODLE_DOCKER_PHP_VERSION=8.3` and `MOODLE_DOCKER_BROWSER=chrome`. Then detect the plugin path: if `$MOODLE_DOCKER_WWWROOT/public` exists, use `public/mod/peerfeedback`, else `mod/peerfeedback`.
- [ ] **Step 2:** `dev/up.sh` does the following. It is all outside the repo, under `~/moodle-dev`, with no sudo.
  1. Clone `moodlehq/moodle-docker` into `$DEV_HOME/moodle-docker` if it is missing.
  2. Shallow-clone `moodle/moodle` at branch `MOODLE_502_STABLE` into `$MOODLE_DOCKER_WWWROOT` if it is missing.
  3. Copy `config.docker-template.php` to `config.php`.
  4. Write `local.yml` with a webserver volume `"<repo abs path>:/var/www/html/$PLUGIN_PATH_IN_MOODLE"`.
  5. Run `bin/moodle-docker-compose up -d` and `bin/moodle-docker-wait-for-db`.
  6. If the site is not installed, run `admin/cli/install_database.php --agree-license --fullname="PF dev" --shortname="pfdev" --adminpass="test" --adminemail="admin@example.com"`.
  7. Otherwise run `admin/cli/upgrade.php --non-interactive`.
- [ ] **Step 3:** `dev/phpunit.sh` does three things:
  1. Runs `admin/tool/phpunit/cli/init.php` once, guarded by a stamp file holding a hash of `version.php` + `db/install.xml`.
  2. Runs `vendor/bin/phpunit $PLUGIN_PATH_IN_MOODLE/tests/$1`. With `--filter`, it passes the filter through and adds `--testsuite mod_peerfeedback_testsuite`.
  3. Prints `PHPUNIT: PASS` or `PHPUNIT: FAIL` at the end.

  `dev/behat.sh` is the same pattern, using behat `init.php` and `run.php --tags=$1`.
- [ ] **Step 4: Run** `bash dev/up.sh`, then `curl -s -o /dev/null -w '%{http_code}' http://localhost:8000/login/index.php`. Expected: `200`. The plugin dir is not populated yet, so no plugin install happens yet.
- [ ] **Step 5: Commit** `chore: local moodle-docker dev environment`.

### Task 1: Activity skeleton, settings form, schema

**Files:**
- Create:
  - `version.php`, `lib.php`, `mod_form.php`, `index.php`, `view.php` (stub: heading + intro)
  - `settings.php` (admin checkbox `mod_peerfeedback/showbranding`, default 1)
  - `db/install.xml` (all tables above), `db/access.php`, `db/upgrade.php` (empty upgrade function)
  - `lang/en/peerfeedback.php`, `pix/icon.svg` (simple monochrome two-speech-bubble icon), `LICENSE` (GPL-3.0 text)
  - `tests/generator/lib.php`, `tests/lib_test.php`

**Interfaces:**
- Produces:
  - Class constants on `\mod_peerfeedback\local\constants`:
    - `VIS_NONE=0`, `VIS_GIVEN=1`, `VIS_RECEIVED=2`, `VIS_ALL=3`
    - `STATUS_SAVED=0`, `STATUS_SUBMITTED=1`, `STATUS_REVIEWED=2`
  - `peerfeedback_supports(string $feature): mixed`. True for: MOD_INTRO, BACKUP_MOODLE2, COMPLETION_HAS_RULES, GRADE_HAS_GRADE, SHOW_DESCRIPTION. Purpose: MOD_PURPOSE_COLLABORATION.
  - `peerfeedback_add_instance(stdClass $data, $mform = null): int`, `peerfeedback_update_instance(stdClass $data, $mform = null): bool`, `peerfeedback_delete_instance(int $id): bool`. Delete removes rows in all child tables.
  - Generator `mod_peerfeedback_generator`:
    - `create_instance($record, $options)`
    - `create_question(int $pfid, string $qtype, array $config = [], bool $required = true, string $text = 'Q'): stdClass`
    - `create_group(int $pfid, array $userids, string $name = null): stdClass`
  - Capabilities:

    | Capability | Who gets it |
    |---|---|
    | `mod/peerfeedback:addinstance` | editingteacher, manager |
    | `mod/peerfeedback:view` | guest is excluded; student and up |
    | `mod/peerfeedback:submit` | student |
    | `mod/peerfeedback:managegroups` | teacher, editingteacher, manager |
    | `mod/peerfeedback:managequestions` | editingteacher, manager |
    | `mod/peerfeedback:review` | teacher, editingteacher, manager |
    | `mod/peerfeedback:export` | teacher, editingteacher, manager |

- [ ] **Step 1: Write failing tests** in `tests/lib_test.php` (class `lib_test extends \advanced_testcase`, `@covers` lib functions):
  - `test_add_instance_stores_defaults`: generator instance → `visibility==2`, `selfeval==0`, `grading==0`, `gradebook==1`, `grade==100`.
  - `test_delete_instance_removes_children`: create a question and a group, delete the instance → `peerfeedback_question` and `peerfeedback_group` are empty for that id.
- [ ] **Step 2: Run** `dev/phpunit.sh lib_test.php`. Expected: FAIL. The plugin is not installed, or the function is undefined.
- [ ] **Step 3: Implement** the files listed.
  - `mod_form.php` fields: standard intro; `timeopen` / `timedue` as `date_time_selector` with optional; `visibility` select with 4 strings (`visnone`, `visgiven`, `visreceived`, `visall`); `selfeval` advcheckbox; `grading` advcheckbox; `gradebook` advcheckbox disabledIf grading 0; `grade` text int disabledIf grading 0 (rule: 1..10000); standard coursemodule elements and buttons.
  - `add_completion_rules()` adds a `completionsubmit` checkbox.
  - Validation: `timedue > timeopen` when both are set.
- [ ] **Step 4: Run** `bash dev/up.sh` (it upgrades and installs the plugin), then `dev/phpunit.sh lib_test.php`. Expected: `PHPUNIT: PASS`.
- [ ] **Step 5: Commit** `feat: activity skeleton, schema, settings form`.

### Task 2: Question types

**Files:**
- Create: `classes/local/qtype/base.php` and one class per type (8 types), `classes/local/constants.php` (if it was not made in Task 1)
- Test: `tests/qtype_test.php`

**Interfaces:**
- Produces: abstract `\mod_peerfeedback\local\qtype\base` with these members.
  - `static function get(string $qtype): base`. Factory; throws `coding_exception` on an unknown type.
  - `static function all_types(): array`. Returns `qtype => localised name`, ordered: singlechoice, multichoice, rating, number, shorttext, longtext, grid, heading.
  - `function is_answerable(): bool`. False only for heading.
  - `function normalise(array $config, mixed $raw): ?string`. Takes raw POST input, returns the stored value, or null when empty.
  - `function validate(array $config, ?string $value, bool $required): ?string`. Returns the error string key (`err_required`, `err_invalidoption`, `err_outofrange`, `err_notnumber`) or null.
  - `function export_columns(array $config, string $qlabel): array`. Returns the list of column headers.
  - `function export_values(array $config, ?string $value): array`. Same length as the columns.
  - `function display_value(array $config, ?string $value): string`. Human-readable text for review and student pages.
  - `function config_change_allowed(array $old, array $new): bool`.
  - `function validate_config(array $config): ?string`.

- [ ] **Step 1: Write failing tests** in `tests/qtype_test.php`:
  - `test_required_empty_rejected_for_every_answerable_type`: for each answerable type, `validate($cfg, null, true) === 'err_required'`.
  - `test_singlechoice_rejects_unknown_index`: options `['A','B']`, value `'2'` → `'err_invalidoption'`.
  - `test_rating_range`: max 5, `'0'` and `'6'` → `'err_outofrange'`; `'5'` → null.
  - `test_number_bounds`: min 1, max 10, `'11'` → `'err_outofrange'`; `'abc'` → `'err_notnumber'`.
  - `test_grid_requires_every_criterion_when_required`: 3 criteria, value `{"0":3,"1":4}` → `'err_required'`.
  - `test_multichoice_export_joins_with_semicolon`: options `['Red','Blue, dark','Green']`, value `[0,1]` → `['Red; Blue, dark']`.
  - `test_grid_export_one_column_per_criterion`: criteria `['Participation','Communication']`, label `'Q3'` → columns `['Q3 – Participation','Q3 – Communication']`.
  - `test_config_change_rules`, which asserts each case below:

    | Change | Allowed |
    |---|---|
    | Add a choice option | true |
    | Remove a choice option | false |
    | Raise rating max | true |
    | Lower rating max | false |
    | Add a grid criterion | true |
    | Remove a grid criterion | false |
    | Change grid max upward | true |
    | Change grid max downward | false |

- [ ] **Step 2: Run** `dev/phpunit.sh qtype_test.php`. Expected: FAIL, class not found.
- [ ] **Step 3: Implement** the classes.
- [ ] **Step 4: Run** `dev/phpunit.sh qtype_test.php`. Expected: PASS.
- [ ] **Step 5: Commit** `feat: question types`.

### Task 3: Question manager + question editor page

**Files:**
- Create: `classes/local/question_manager.php`, `classes/form/question_form.php`, `questions.php`, `templates/questions.mustache`
- Test: `tests/question_manager_test.php`

**Interfaces:**
- Consumes: `qtype\base` (Task 2).
- Produces: `\mod_peerfeedback\local\question_manager`, constructed as `new question_manager(stdClass $peerfeedback)`, with these methods:
  - `get_questions(): array`: question records ordered by sortorder, with `configdata` decoded into `->config` (array).
  - `add(string $qtype, string $text, bool $required, array $config): int`.
  - `update(int $questionid, string $text, bool $required, array $config): void`. Throws `moodle_exception('err_configlocked')` if `has_submissions()` and `!config_change_allowed`.
  - `delete(int $questionid): void`. Throws `moodle_exception('err_deletelocked')` if `has_submissions()`.
  - `move(int $questionid, int $direction): void`. Direction is -1 or +1.
  - `has_submissions(): bool`. True if any response has status ≥ 1.
- [ ] **Step 1: Failing tests**:
  - `test_add_and_order`: add 3 questions, move the third up → order matches.
  - `test_delete_blocked_after_submission`: create a response row with status 1 directly → `delete()` throws.
  - `test_update_wording_allowed_after_submission_but_not_option_removal`.
  - `test_type_cannot_change`: `update` never changes `qtype`, because the signature has no type parameter. Assert the stored qtype is unchanged.
- [ ] **Step 2: Run** `dev/phpunit.sh question_manager_test.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the manager, then `questions.php` (capability `managequestions`).
  - The page lists questions in a table: type, text, required, ↑ ↓, edit, delete. When `has_submissions()`, delete is hidden and a notice `questionslocked` is shown.
  - The "Add question" select picks the type and opens `question_form` for that type. The form's type-specific fields are:
    - one option per line for choice types;
    - max plus end labels for rating;
    - min and max for number;
    - one criterion per line plus max for grid.
  - Form validation uses `validate_config`.
- [ ] **Step 4: Run** the tests → PASS. Manually: load `questions.php?id=<cmid>` as admin in a demo course, add one question of each type, and see no PHP notices.
- [ ] **Step 5: Commit** `feat: question manager and editor`.

### Task 4: Group manager + group page (layout A)

**Files:**
- Create: `classes/local/group_manager.php`, `groups.php`, `templates/groups.mustache`, `amd/src/groups.js` (select-all checkbox only), with the built file `amd/build/groups.min.js` produced by the grunt in the docker image
- Test: `tests/group_manager_test.php`

**Interfaces:**
- Produces: `\mod_peerfeedback\local\group_manager`, constructed as `new group_manager(stdClass $peerfeedback, \context_module $context)`, with these methods:
  - `eligible_users(): array`. Returns userid → user record (id, firstname, lastname). These are active enrolled users with `mod/peerfeedback:submit`, sorted by lastname, firstname.
  - `get_groups(): array`. Group records ordered by sortorder, each with `->members` (an array of userids).
  - `get_group_of(int $userid): ?stdClass`.
  - `create_group(?string $name = null): int`. Null name → the next free "Group N", from the string `defaultgroupname`.
  - `rename_group(int $groupid, string $name): void`.
  - `delete_group(int $groupid): void`.
  - `assign(array $userids, int $groupid): void`. Moves the users, then calls `cleanup_orphans()`.
  - `unassign(int $userid): void`. Then calls `cleanup_orphans()`.
  - `random_assign(string $mode, int $value, bool $reshuffle, ?int $seed = null): void`.
    - `$mode` is `'groups'` (value = number of groups) or `'size'` (value = people per group).
    - It creates new groups from the pool: unassigned users, or everyone after deleting all groups when `$reshuffle`.
    - Sizes are balanced: the number of groups is value, or ceil(n/size), and sizes differ by at most 1.
  - `pair_all(?int $seed = null): void`. Deletes all groups, then makes floor(n/2) pairs; with an odd n, one group has 3; with n=1, one group of 1.
  - `cleanup_orphans(): int`. Deletes STATUS_SAVED responses (and their answers) where the receiver is no longer a peer of the giver. Returns the count.
  - The seed is used with `mt_srand($seed)` + `shuffle`. When null, use `random_int`.
- [ ] **Step 1: Failing tests**:
  - `test_assign_moves_user_between_groups`: a user ends up in exactly one group.
  - `test_pair_all_odd_makes_one_triple`: 7 users → 3 groups with sizes sorted `[2,2,3]`.
  - `test_pair_all_single_user`: 1 user → one group of 1.
  - `test_random_by_size_balanced`: 10 users, size 4 → 3 groups with sizes sorted `[3,3,4]`.
  - `test_random_by_groups_only_unassigned_without_reshuffle`: 2 users already in a group, 6 unassigned, mode groups 3 → the original group is untouched and the 6 are spread 2/2/2.
  - `test_eligible_users_excludes_suspended_and_teachers`.
  - `test_regroup_discards_orphan_saved_keeps_submitted` (Review Focus 3):
    1. A and B share a group. Insert a saved response A→B and a submitted response B→A with their answers.
    2. Move B to a new group.
    3. Expect: A→B and its answers are deleted; B→A remains with its original `groupid`.
- [ ] **Step 2: Run** `dev/phpunit.sh group_manager_test.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the manager, then `groups.php` (capability `managegroups`) per spec layout A:
  - The toolbar has, in order:
    - "Assign selected to [select] [Assign]";
    - "New group";
    - "Random…", a small inline form: mode radio, value, and a reshuffle checkbox;
    - "Pair everyone", with a confirmation page via `$OUTPUT->confirm`, because it replaces all groups.
  - The student table has a checkbox, the full name, and a group badge ("Unassigned" in orange).
  - Under the table, each group is a box: name (rename inline), member chips with a ✕ unassign, and a delete group button.
  - Every POST checks `require_sesskey()`.
  - When `cleanup_orphans()` returns more than 0, show the notification `draftsdiscarded` with the count.
- [ ] **Step 4: Run** the tests → PASS. Manually check the page with 7 demo students: pair, random, tick-assign.
- [ ] **Step 5: Commit** `feat: activity groups management`.

### Task 5: Response manager (peers, save/submit, locks)

**Files:**
- Create: `classes/local/response_manager.php`, `classes/event/response_submitted.php`
- Test: `tests/response_manager_test.php`

**Interfaces:**
- Consumes: `group_manager::get_group_of`, `question_manager::get_questions`, `qtype\base`.
- Produces: `\mod_peerfeedback\local\response_manager`, constructed as `new response_manager(stdClass $peerfeedback, \context_module $context)`, with these methods:
  - `get_peers(int $userid): array`. User records of the other members of the user's group, sorted by lastname, then the user themself last if `selfeval`. Empty if the user has no group.
  - `get_response(int $giverid, int $receiverid): ?stdClass`. Includes `->answers` (questionid → value).
  - `is_open(?int $now = null): bool`. Checks `timeopen` ≤ now and (`timedue` == 0 or now ≤ `timedue`).
  - `can_edit(int $giverid, int $receiverid): bool`. Requires is_open, receiver in get_peers(giver), and no existing response with STATUS_REVIEWED.
  - `save(int $giverid, int $receiverid, array $rawanswers, bool $submit): array`.
    - Returns `questionid => error string key` for validation failures. An empty array means it was stored.
    - It throws `moodle_exception('err_notallowed')` when `!can_edit`.
    - It upserts the response.
    - With `$submit`, it sets status 1 and `timesubmitted`, and triggers `response_submitted`.
    - Without `$submit`, it keeps status 1 if the response was already submitted, otherwise it sets 0.
    - If a grade exists, it sets `editedaftergrade=1`.
    - It stores `groupid` = the giver's current group id.
  - `progress(int $userid): array`. Returns `['done' => int submitted, 'total' => count(peers)]`.
- [ ] **Step 1: Failing tests**:
  - `test_peers_exclude_self_unless_selfeval`.
  - `test_save_requires_required_answers`: one required rating, empty raw → returns `[qid => 'err_required']`, and no response row exists.
  - `test_save_then_submit_sets_status_and_time`.
  - `test_save_rejects_receiver_outside_group` (Review Focus 1): it throws, and the row count is 0.
  - `test_save_rejected_when_reviewed_or_closed` (Review Focus 2), three cases:
    1. Status 2 → throws.
    2. `timedue` = now−60 → throws.
    3. `timeopen` = now+3600 → throws.
  - `test_edit_after_grade_flags_response`.
  - `test_heading_questions_are_ignored_on_save`.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement**.
- [ ] **Step 4: Run** → PASS.
- [ ] **Step 5: Commit** `feat: response saving with validation and locks`.

### Task 6: Student answering screen (layout A) + branding footer

**Files:**
- Create:
  - `tools/build_branding.py`, `pix/branding.svg` (built output, committed)
  - `classes/output/renderer.php`, `templates/answer.mustache`, `templates/footer_branding.mustache`
  - `classes/event/course_module_viewed.php`, `styles.css`
- Modify: `view.php`
- Test: `tests/behat/answer.feature`

**Interfaces:**
- Consumes: `response_manager` (Task 5), `question_manager`, `qtype\base::display_value`.
- Produces:
  - `renderer::branding_footer(): string`. Returns '' when `get_config('mod_peerfeedback','showbranding')` is falsy.
  - `view.php?id=<cmid>&peer=<userid>`, which routes like this:
    - users with `review` capability are sent to the teacher landing (counts + links to Questions / Groups / Review);
    - students see the answering screen.
- [ ] **Step 1: Branding asset.** `tools/build_branding.py <Logos.svg> <Saira-Medium.ttf> <out.svg>` does the following:
  1. Crops the sheet to viewBox `186 132 1110 298`. It keeps the whole source SVG content, sets that viewBox, and strips the Inkscape/sodipodi metadata.
  2. Appends the text "designed by Burkay ALGUL", converted to outline paths with fontTools (`TTFont` + `SVGPathPen`), fill `#0a1d3f`, scaled so its advance width = 555 units (half of 1110), centred, baseline at y=362 relative to the crop.
  3. Sets the final viewBox height to 372.

  Get Saira Medium from Google Fonts (OFL), the static TTF via the github.com/google/fonts `ofl/saira` directory. Run the script with `python3 -I`, with the font in its own download directory. Output goes to `pix/branding.svg`.

  Verify: render with `qlmanage -t -s 800` and look at it. Expected: the full EduZ logo with a centred one-line signature half its width beneath. Add a `README.md` section saying the EduZ logo is the owner's trademark and is not covered by the GPL. The font outlines come from Saira (OFL).
- [ ] **Step 2: Failing Behat** `tests/behat/answer.feature` (`@mod @mod_peerfeedback`), scenario "Student answers each peer one at a time":
  1. Background: a course, students s1 s2 s3 in one activity group (generator steps from `tests/generator/behat`, or the `the following "mod_peerfeedback > groups" exist` data generator), and one required rating question "Communication" max 5 plus a long text "Improve".
  2. As s1, open the activity. See tabs "Student 2" and "Student 3".
  3. Press "Save and next" with nothing filled → see "This question is required".
  4. Choose rating 4 and enter "More notes", then press "Save and next" → the tab for Student 2 shows the done tick (`.pf-done`).
  5. Press "Submit" → see "Feedback submitted".

  Add `tests/generator/behat_mod_peerfeedback_generator.php` with entities `questions` (activity idnumber, qtype, text, config JSON, required) and `groups` (activity idnumber, name, members comma-separated usernames).
- [ ] **Step 3: Run** `dev/behat.sh @mod_peerfeedback`. Expected: FAIL.
- [ ] **Step 4: Implement** the view and answer template per spec layout A.
  - Pill tabs for each peer, with the done tick when status ≥ 1, and "Yourself" for the self tab.
  - A progress bar from `progress()`.
  - A person header with initials, name and "N of M".
  - Questions rendered per type:
    - rating as a row of large radio buttons styled like squares;
    - grid as a table with a radio row per criterion;
    - single choice as radios, multi choice as checkboxes;
    - number as `input type=number`, short text as `input`, long text as `textarea`;
    - heading as `h4` plus text.
  - Errors shown inline under each question.
  - Buttons: "Back", "Save and next" (submit=0), "Submit" (submit=1).
  - Locked or closed state: answers are read-only, with the notice `locked` / `notopen` / `closed`.
  - A user with no group sees `nogroup`. A user with a group but no peers sees `nopeers`.
  - When grading is on and the response is reviewed, show the teacher's grade and comment under that peer's answers.
  - Mobile: tabs scroll horizontally, inputs are full-width under 600px.
  - Render the footer on every plugin page via `$OUTPUT->footer()` preceded by `branding_footer()`.
  - Trigger `course_module_viewed`.
- [ ] **Step 5: Run** Behat → PASS. Manual phone check at 400px width in the browser devtools.
- [ ] **Step 6: Commit** `feat: student answering screen and branding footer`.

### Task 7: What students see (visibility modes)

**Files:**
- Create: `classes/local/student_view.php`, `templates/student_results.mustache`
- Modify: `view.php` (results section under the answering area, or a "Results" tab)
- Test: `tests/student_view_test.php`

**Interfaces:**
- Produces: `\mod_peerfeedback\local\student_view`, constructed as `new student_view(stdClass $peerfeedback)`.
  - `for_user(int $userid): array`. Returns a list of items; each item is `['receivername' => ?string, 'givername' => ?string, 'answers' => [['question' => string, 'value' => string]], 'grade' => ?float, 'teachercomment' => ?string]`.
  - Only responses with status ≥ 1 are included. The modes behave like this:

    | Mode | Items included | `givername` | `receivername` |
    |---|---|---|---|
    | `VIS_NONE` | none (`[]`) | — | — |
    | `VIS_GIVEN` | responses with giver = user | the user | set |
    | `VIS_RECEIVED` | responses with receiver = user | null | null (it is "you") |
    | `VIS_ALL` | all responses whose stored groupid = the user's current group id | null | set |

  - `grade` and `teachercomment` are non-null only when the giver = user, grading is on and the status is reviewed.
- [ ] **Step 1: Failing tests**:
  - `test_none_returns_nothing`.
  - `test_given_lists_own_with_receiver`.
  - `test_received_view_has_no_giver_identity` (Review Focus 4): for modes 2 and 3, `json_encode(for_user(...))` contains neither the giver's id-as-field, nor the giver's firstname, nor lastname. Every `givername` is null.
  - `test_all_limited_to_own_group`.
  - `test_teacher_grade_only_on_own_given`.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement** and render under a "Feedback" heading in `view.php` for students.
- [ ] **Step 4: Run** → PASS.
- [ ] **Step 5: Commit** `feat: student visibility modes`.

### Task 8: Teacher review, grading, gradebook

**Files:**
- Create: `review.php`, `templates/review.mustache`, `classes/event/response_reviewed.php`
- Modify: `classes/local/response_manager.php` (review methods), `lib.php` (grade callbacks)
- Test: `tests/review_test.php`, `tests/behat/review.feature`

**Interfaces:**
- Produces, on `response_manager`:
  - `review(int $responseid, ?float $grade, ?string $comment, bool $markreviewed, int $reviewerid): void`.
    - Requires status ≥ 1.
    - A grade must be in 0..`peerfeedback->grade`, otherwise it throws `moodle_exception('err_gradeoutofrange')`.
    - When `$markreviewed`, it sets status 2 and `timereviewed`, and triggers `response_reviewed`.
    - It always calls `peerfeedback_update_grades($pf, $giverid)` when grading and gradebook are on.
  - `reopen(int $responseid): void`. Status 2 → 1.
  - `average_grade(int $giverid): ?float`. The mean of non-null grades over the giver's responses with status ≥ 1, or null.
  - `list_for(int $userid, string $direction, string $filter): array`. `$direction` is `'given'` or `'received'`; `$filter` is `'all'`, `'notreviewed'` or `'reviewed'`. It returns response records with giver and receiver full names and display answers. Saved-not-submitted responses appear with `notsubmitted=true` and cannot be graded.
- Also produces, in `lib.php`:
  - `peerfeedback_grade_item_update(stdClass $pf, $grades = null): int`. GRADE_TYPE_VALUE, grademax = `$pf->grade`. When `!grading || !gradebook`, it deletes or does not create the item.
  - `peerfeedback_update_grades(stdClass $pf, int $userid = 0, bool $nullifnone = true): void`. It uses `average_grade`; users with no graded feedback get a null rawgrade.
  - `peerfeedback_grade_item_delete`.
- [ ] **Step 1: Failing tests** in `tests/review_test.php`:
  - `test_review_marks_and_locks`: after `review(…, 80, 'Good', true, $teacher)`, status = 2 and `can_edit` is false.
  - `test_reopen_unlocks_and_keeps_grade`.
  - `test_average_ignores_ungraded`: grades 80 and null, plus 60 → 70.
  - `test_gradebook_receives_average`: grading=1, gradebook=1 → `grade_get_grades(...)` shows 70 for the giver.
  - `test_gradebook_untouched_when_disabled`: gradebook=0 → no grade item exists.
  - `test_grade_out_of_range_rejected`: 101 with max 100 throws.
  - `test_no_graded_feedback_gives_null_grade`.
  - `test_list_flags_outside_group_after_regroup`.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement** `review.php` (capability `review`) per the approved mock.
  - A toggle for "Feedback given by" / "Feedback received by" (param `dir`).
  - A filter select: all / not reviewed / reviewed.
  - A left list of eligible users with a pill `reviewed/submitted` count or "Not submitted".
  - On the right, a card per response.
    - The header is "Giver → Receiver", with a status pill and an "Edited after grading" pill when that flag is set.
    - Then the answers.
    - Then a review form (POST, sesskey): a grade input only when grading is on, a comment textarea, "Save", "Mark as reviewed", and "Reopen" when reviewed.
  - Below the cards: "Average grade: X" for the selected student as giver.
  - There is no anonymity anywhere.
- [ ] **Step 4: Run** the unit tests → PASS. Add `tests/behat/review.feature`:
  1. A teacher opens Review.
  2. Selects Student 1, grades 85 with a comment, marks it reviewed.
  3. The pill shows "Reviewed".
  4. As Student 1, the answering tab for that peer is read-only and shows "85".

  Run `dev/behat.sh @mod_peerfeedback` → PASS.
- [ ] **Step 5: Commit** `feat: teacher review, grading and gradebook`.

### Task 9: Export

**Files:**
- Create: `classes/local/exporter.php`, `export.php`
- Test: `tests/exporter_test.php`

**Interfaces:**
- Produces: `\mod_peerfeedback\local\exporter`, constructed as `new exporter(stdClass $pf)`.
  - `headers(): array`. The headers are, in order: Giver, Receiver, Group, then each answerable question's `export_columns` (label `Q<n>` + ": " + shortened text, max 40 characters), then Status, Submitted, Teacher grade, Teacher comment.
  - `rows(string $by): \Generator`. `$by` is `'giver'` or `'receiver'`. Rows are sorted by `$by` name, then the other name. Responses with status ≥ 1 only. Each row is an array matching `headers()` in length.
- `export.php?id=&by=giver|receiver&format=excel` (capability `export`) calls `\core\dataformat::download_data("peerfeedback-<by>-<date>", $format, headers, rows)`. The format select offers Moodle's enabled dataformats, with `excel` as the default.
- The review page gets an "Export ▾" with two links, by giver and by receiver.
- [ ] **Step 1: Failing tests**:
  - `test_headers_include_grid_columns`.
  - `test_export_flattens_multi_and_grid` (Review Focus 5): a multi-choice answer with options containing a comma and a quote, a long text with a newline, and a grid answer. Every row has a length equal to `count(headers())`, and the multi cell equals `'A, x; "B"'`.
  - `test_sort_by_receiver`.
  - `test_option_added_after_submission_exports_old_answers_correctly`.
- [ ] **Step 2: Run** → FAIL. **Step 3:** Implement. **Step 4:** Run → PASS. Manually download the xlsx and open it in Numbers or Excel.
- [ ] **Step 5: Commit** `feat: export by giver and by receiver`.

### Task 10: Completion, privacy, backup/restore

**Files:**
- Create:
  - `classes/completion/custom_completion.php`
  - `classes/privacy/provider.php`
  - `backup/moodle2/backup_peerfeedback_activity_task.class.php`, `backup/moodle2/backup_peerfeedback_stepslib.php`
  - `backup/moodle2/restore_peerfeedback_activity_task.class.php`, `backup/moodle2/restore_peerfeedback_stepslib.php`
- Test: `tests/completion_test.php`, `tests/privacy/provider_test.php`, `tests/backup_restore_test.php`

**Interfaces:**
- Completion rule `completionsubmit`: complete when `progress(userid)['total'] > 0` and `done == total`. `response_manager::save` with submit and `group_manager` changes call `completion_info->update_state` for affected users.
- The privacy provider implements:
  - `\core_privacy\local\metadata\provider`
  - `\core_privacy\local\request\plugin\provider`
  - `\core_privacy\local\request\core_userlist_provider`

  It covers the members, responses (giverid, receiverid, reviewerid, grade, teachercomment) and answers. Export writes, under the context, "given" and "received" subcontexts. Deleting for a user removes their memberships, the responses where they are giver or receiver, and the answers.
- Backup includes the instance, groups and questions always. Members, responses and answers are included only with userinfo. User ids are mapped on restore.
- [ ] **Step 1: Failing tests**:
  - `test_completion_complete_after_all_peers_submitted`.
  - `test_completion_incomplete_without_peers`.
  - Privacy:
    - `test_get_contexts_for_userid` covers giver, receiver and reviewer;
    - `test_export_user_data`;
    - `test_delete_data_for_user`;
    - `test_delete_data_for_users`.
  - `test_backup_restore_with_userdata_keeps_responses` and `test_backup_restore_without_userdata_keeps_questions_and_groups_only`. Use `backup_controller` / `restore_controller` the way core module backup tests do.
- [ ] **Step 2: Run** → FAIL. **Step 3:** Implement. **Step 4:** Run the whole suite `dev/phpunit.sh --filter mod_peerfeedback` → PASS.
- [ ] **Step 5: Commit** `feat: completion, privacy and backup/restore`.

### Task 11: Release hygiene + demo site for the owner

**Files:**
- Create: `README.md`, `CHANGES.md`, `.github/workflows/ci.yml` (moodle-plugin-ci 4.x, Moodle 5.2, PHP 8.3, pgsql), `dev/seed_demo.php`, `dev/build_zip.sh`

**Interfaces:**
- `dev/seed_demo.php` is a CLI script run inside the container. It does the following:
  1. Creates course "Peer feedback demo" (shortname `pfdemo`).
  2. Creates teacher `teacher1` / `Test123!` and students `student1..student7` / `Test123!`, enrolled.
  3. Creates one activity "Team project feedback" with grading on and self-evaluation on.
  4. Adds one question of each type.
  5. Leaves the groups empty, so the owner tries the group tools.

  It is idempotent: it skips anything that already exists.
- `dev/build_zip.sh` builds `dist/mod_peerfeedback-<release>.zip` with a top folder `peerfeedback/`. It excludes `dev/`, `tools/`, `docs/`, `.git*`, `.superpowers/`, `dist/`.
- [ ] **Step 1:** Run the code checker inside the container. Install `moodle-local_codechecker` into `$DEV_HOME` (outside the repo), then run `vendor/bin/phpcs --standard=moodle $PLUGIN_PATH_IN_MOODLE`. Expected: 0 errors. Fix any warnings that are cheap to fix.
- [ ] **Step 2:** Run `dev/phpunit.sh --filter mod_peerfeedback` and `dev/behat.sh @mod_peerfeedback`. Expected: both PASS.
- [ ] **Step 3:** Run `seed_demo.php`. Then, logged in as admin, walk through the flow in the browser and take screenshots for the owner:
  - groups → pair everyone;
  - student1 answers;
  - teacher reviews;
  - export.
- [ ] **Step 4:** Run `dev/build_zip.sh` and unzip `-l` it. Expected: a `peerfeedback/version.php` entry, and no `dev/` or `docs/` entries.
- [ ] **Step 5: Commit** `chore: readme, ci, demo seed, zip build`.
