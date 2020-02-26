<?php

define('CLI_SCRIPT', true);

require('config.php');

$to =  core_user::get_user(3); // brendan
$from =  core_user::get_support_user();

$email = $PAGE;
$email->set_heading('Password reset');
// $email->set_user($to);

$text = password_reset_email(new \core_renderer_textemail($email, RENDERER_TARGET_GENERAL));
$html = password_reset_email(new \core_renderer_htmlemail($email, RENDERER_TARGET_GENERAL));

echo $text;
echo $html;


// email_to_user($to, $from, $email->heading, $text, $html);

function password_reset_email($output) {
    global $CFG;

    $out = '';
    $out .= $output->header();
    $out .= $output->salutation();
    $out .= $output->markdown("A password reset was requested for your account 'brendan' at Monash LMS.

To confirm this request, and set a new password for your account, please reset your password:");

    $url = new moodle_url('/login/forgot_password.php?token=wrhWwrheyjwrw');
    $out .= $output->single_button($url, 'Reset your password');
    $out .= $output->markdown("This link is valid for 5 minutes from the time this reset was first requested.

If this password reset was not requested by you, no action is needed.

If you need help, please contact the site administrator.");



    if (!empty($CFG->passwordpolicy)) {
        $out .= $output->heading(get_string('passwordpolicy', 'admin'), 2);
        $messages = get_password_policy_items();
        $messages = "\n\n* " . join("\n* ", $messages);
        $out .= $output->markdown(get_string('informpasswordpolicy', 'auth', $messages));
    }

    $out .= $output->notification('Your password needs to be reset');
    $out .= $output->signature_support();
    $out .= $output->footer();
    return $out;
}


