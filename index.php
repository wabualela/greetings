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
 * Plugin Libraries.
 *
 * @package     local_greetings
 * @copyright   2023 Wail Abualela <wailabualela@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/greetings/lib.php');

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/greetings/index.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title($SITE->fullname);
$PAGE->set_heading(get_string('pluginname', 'local_greetings'));

require_login();

if (isguestuser()) {
    throw new moodle_exception('noguest');
}

$allowpost = has_capability('local/greetings:postmessages', $context);
$deletepost = has_capability('local/greetings:deleteownmessage', $context);
$deleteanypost = has_capability('local/greetings:deleteanymessage', $context);

$action = optional_param('action', '', PARAM_TEXT);

if ($action == 'del') {
    $id = required_param('id', PARAM_TEXT);

    if ($deleteanypost || $deletepost) {
        // TODO: confirm before delete.
        $DB->delete_records('local_greetings_messages', ['id' => $id]);
        redirect($PAGE->url);
    }
}

$messageform = new \local_greetings\form\message_form();

if ($data = $messageform->get_data()) {
    require_capability('local/greetings:postmessages', $context);

    $message = required_param('message', PARAM_TEXT);

    if (!empty($message)) {
        $record = new stdClass;
        $record->message = $message;
        $record->userid = $USER->id;
        $record->timecreated = time();

        $DB->insert_record('local_greetings_messages', $record);
        redirect($PAGE->url);
    }
}

echo $OUTPUT->header();

echo isloggedin()
    ? local_greetings_get_greeting($USER)
    : get_string('greetinguser', 'local_greetings');

if ($allowpost) {
    $messageform->display();
}
if (has_capability('local/greetings:viewmessages', $context)) {
    $userfields = \core_user\fields::for_name()->with_identity($context);
    $userfieldssql = $userfields->get_sql('u');

    $sql = "SELECT m.id, m.message, m.timecreated, m.userid {$userfieldssql->selects}
          FROM {local_greetings_messages} m
     LEFT JOIN {user} u ON u.id = m.userid
      ORDER BY timecreated DESC";

    $messages = $DB->get_records_sql($sql);

    echo $OUTPUT->box_start('card-columns');

    foreach ($messages as $m) {
        echo html_writer::start_div('card');
        echo html_writer::start_div('card-body');
        echo html_writer::start_div('d-flex justify-content-between');
        echo html_writer::tag('p', format_text($m->message, FORMAT_PLAIN), ['class' => 'card-text']);
        if ($deleteanypost || ($deletepost && $m->userid == $USER->id)) {
            echo html_writer::link(
                new moodle_url(
                    '/local/greetings/index.php',
                    array('action' => 'del', 'id' => $m->id)
                ),
                $OUTPUT->pix_icon('t/delete', ''),
                ['class' => 'text-danger']
            );
        }
        echo html_writer::end_div();
        echo html_writer::tag('p', get_string('postedby', 'local_greetings', $m->firstname), array('class' => 'card-text'));
        echo html_writer::start_tag('p', ['class' => 'card-text']);
        echo html_writer::tag('small', userdate($m->timecreated), ['class' => 'text-muted']);
        echo html_writer::end_tag('p');
        echo html_writer::end_div();
        echo html_writer::end_div();
    }


    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();
