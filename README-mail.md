SMTP email setup for Trendify (Gmail)

Overview
- This project uses PHPMailer for SMTP email sending when available.
- By default, `forgot_password.php` will attempt to use `vendor/autoload.php` (Composer + PHPMailer).
- If no PHPMailer is installed, the script falls back to `mail()` (which usually doesn't work on local XAMPP without extra setup).

Steps to enable Gmail SMTP (recommended):

1) Install PHPMailer via Composer

   If you have Composer installed, run from project root:

```bash
composer require phpmailer/phpmailer
```

This will create `vendor/autoload.php` used by the script.

2) Configure `mail_config.php`

- Open `mail_config.php` and update the following constants:
  - `SMTP_USER` = your Gmail address (e.g. you@gmail.com)
  - `SMTP_PASS` = an App Password generated for your account (see below)
  - `MAIL_FROM` and `MAIL_FROM_NAME` as desired

3) Generate a Gmail App Password (recommended)

- Go to https://myaccount.google.com/
- Under "Security", ensure 2-Step Verification is ON
- Click "App passwords"
- Create a new App Password for "Mail" and "Other (Custom)" (name it Trendify)
- Copy the generated 16-character password and paste it into `SMTP_PASS`

4) Test

- Start Apache (XAMPP)
- Load the forgot password page: http://localhost/trendify/forgot_password.html
- Enter a registered user email and click NEXT
- If everything is configured, you should receive the verification code in your Gmail inbox

Security notes
- Do NOT commit `mail_config.php` with real credentials to version control.
- In production, store credentials in environment variables or a secure vault.

If you want, I can attempt to install PHPMailer files into the repo directly, or I can walk you through running Composer locally. Which do you prefer?