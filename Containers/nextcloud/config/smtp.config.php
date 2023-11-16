<?php
if (getenv('SMTP_HOST') && getenv('MAIL_FROM_ADDRESS') && getenv('MAIL_DOMAIN')) {
  $CONFIG = array (
    'mail_smtpmode' => 'smtp',
    'mail_smtphost' => getenv('SMTP_HOST'),
    'mail_smtpport' => getenv('SMTP_PORT') ?: (getenv('SMTP_SECURE') ? 465 : 25),
    'mail_smtpsecure' => getenv('SMTP_SECURE') ?: '',
    'mail_smtpauth' => getenv('SMTP_NAME') && (getenv('SMTP_PASSWORD') || (getenv('SMTP_PASSWORD_FILE') && file_exists(getenv('SMTP_PASSWORD_FILE')))),
    'mail_smtpauthtype' => getenv('SMTP_AUTHTYPE') ?: 'LOGIN',
    'mail_smtpname' => getenv('SMTP_NAME') ?: '',
    'mail_from_address' => getenv('MAIL_FROM_ADDRESS'),
    'mail_domain' => getenv('MAIL_DOMAIN'),
  );

  if (getenv('SMTP_PASSWORD_FILE') && file_exists(getenv('SMTP_PASSWORD_FILE'))) {
      $CONFIG['mail_smtppassword'] = trim(file_get_contents(getenv('SMTP_PASSWORD_FILE')));
  } elseif (getenv('SMTP_PASSWORD')) {
      $CONFIG['mail_smtppassword'] = getenv('SMTP_PASSWORD');
  } else {
      $CONFIG['mail_smtppassword'] = '';
  }
}
