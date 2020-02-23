<?php

// define('CLI_SCRIPT', true);

require('config.php');
require('login/lib.php');

$to =  core_user::get_user(3); // brendan
// $to->email = 'brendan.heywood@gmail.com';


$PAGE->set_context(context_system::instance());
$PAGE->set_url('/email.php');

$DB->delete_records('user_password_resets', array('id' => $to->id));
$resetrecord = core_login_generate_password_reset($to);
$sendresult = send_password_change_confirmation_email($to, $resetrecord);

exit;

$support = core_user::get_support_user();
$admin = core_user::get_user(2); // admin
$from = $support;

$email = $PAGE;
e($email);
$email->set_heading('Password reset');
// $email->set_user($to);

$email = new core\email\password_change_confirmation($support, $to, [
    'token' => 123536,
    'resetminutes' => 3,
]);

$format = $email->render_text_and_html();
$text = $format['text'];
$html = $format['html'];

echo $OUTPUT->header();
echo '<pre>';
echo $text;
echo '</pre>';
echo '<hr>';
echo '<hr>';
echo '<hr>';
echo $html;
// email_to_user($to, $from, $email->heading, $text, $html);
// $html = password_reset_email($PAGE->get_renderer('core', 'admin'), $to);
// echo $html;
echo $OUTPUT->footer();



function password_reset_email($output, $to, $from) {
    global $CFG, $SITE;

    $out = '';
    $out .= $output->preview_text("A password reset was requested for your account '$to->username' at $SITE->fullname.");
    $out .= $output->header();
    $out .= $output->salutation($to);
    $out .= $output->markdown("A password reset was requested for your account `$to->username` at $SITE->fullname.

To confirm this request, and set a new password for your account, please reset your password:");

    $url = new moodle_url('/login/forgot_password.php?token=wrhWwrheyjwrw');
    $out .= $output->single_button($url, 'Reset your password');
    $out .= $output->markdown("This link is valid for 5 minutes from the time this reset was first requested.

If this password reset was not requested by you, no action is needed.

If you need help, please contact the site administrator.");

    if (!empty($CFG->passwordpolicy)) {
        $policy = $output->heading(get_string('passwordpolicy', 'admin'), 4);
        $items = get_password_policy_items();
        $items = "\n\n* " . join("\n* ", $items);
        $policy .= $output->markdown(get_string('informpasswordpolicy', 'auth', $items));
        $out .= $output->notification($policy,      \core\output\notification::NOTIFY_INFO);
    }

    $out .= $output->signature_support($to);
    // $out .= $output->signature_from($to, $from);
    $out .= $output->footer();
    return $out;
}


