<?php

//TODO: START OF STUFF TO GET THIS TEST FILE WORKING
require_once("../config.php");
require_once($CFG->libdir.'/adminlib.php');

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/user/test.php');
$PAGE->set_title('TODO - testing');
//^^^^TODO: END OF STUFF TO GET THIS TEST FILE WORKING^^^^

$strings = [
    'all' => get_string('all'),
    'any' => get_string('any'),
    'none' => get_String('none'),
];

$tempaltecontext = [
    'matchtypes' => [
        ['value' => strtolower($strings['all']), 'label' => $strings['all'], 'selected' => true],
        ['value' => strtolower($strings['any']), 'label' => $strings['any']],
        ['value' => strtolower($strings['none']), 'label' => $strings['none']],
    ],
    'filtertypes' => [
        
    ],
];

echo $OUTPUT->render_from_template('user/filter_row', $tempaltecontext);
