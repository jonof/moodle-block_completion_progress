# Completion Progress block for Moodle

The Completion Progress block is a time-management tool for students. It visually shows what activities/resources a student is supposed to interact with in a course. It is colour-coded so students can quickly see what they have and have not completed/viewed. The block shows activities with activity completion settings.

To install, please refer to [the Moodle documentation for installing plugins](https://docs.moodle.org/en/Installing_plugins#Installing_a_plugin) appropriate to your Moodle version.

Once the Completion Progress block is installed, you can use it in a course as follows.

1. Turn editing on
2. Create your activities/resources as normal
3. Set completion settings for each activity you want to appear in the bar, including an expected by date
4. Add the Completion Progress block to your page
5. Move your block into a prominent position
6. (Optional) Configure how the block should appear

Hidden items will not appear in the Completion Progress block until they are visible to students. This is useful for a scheduled release of activities.

## New features

- Overview table can show Matric Number and Email columns; these can be toggled per block.
- Reminder emails: lecturers can enable scheduled reminder emails for students below a configured progress threshold.
- Reminder frequency options: daily, weekly, monthly, or yearly.
- Emails include the student's current completion percentage and a link to the course.

### Configuration details

Block settings (per instance):
- Overview columns: toggle Matric Number and Email columns in the Overview table.
- Reminder emails: enable/disable scheduled reminder emails for students.
- Send reminder emails: choose daily, weekly, monthly, or yearly frequency.
- Send when progress is below (%): integer threshold from 0 to 100; students with progress below this value are emailed.

Operational notes:
- Reminder emails are sent by Moodle scheduled tasks (cron).
- Reminders respect block group restrictions and only target users who can view the block (student role).
- If no emails are sent in a run, the last-sent timestamp is not updated, so the next run can try again.
