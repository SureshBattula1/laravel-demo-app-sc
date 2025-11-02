# Email Configuration Guide

## Current Setup

✅ **Email functionality is now implemented!**

By default, emails are written to log files for development/testing:
- **Location:** `storage/logs/laravel.log`
- **Mailer:** `log` (configured in `config/mail.php`)

## Testing Password Reset

### In Development Mode:
1. Request password reset from the frontend
2. Check the API response - it includes the `reset_token` (only in development)
3. **OR** Check `storage/logs/laravel.log` to see the email content and reset URL

### Email Content in Log:
Look for entries like:
```
Password reset email sent
```

The reset URL will be in the format:
```
http://localhost:4200/auth/reset-password?token=YOUR_TOKEN_HERE
```

## Production Email Setup

To actually send emails in production, add these to your `.env` file:

### Option 1: SMTP (Gmail, Outlook, etc.)

```env
# Frontend URL for reset links
FRONTEND_URL=https://your-frontend-domain.com

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

**For Gmail:**
- Use App Password (not your regular password)
- Enable 2-Factor Authentication
- Generate App Password at: https://myaccount.google.com/apppasswords

### Option 2: Mailtrap (Testing)

```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-mailtrap-username
MAIL_PASSWORD=your-mailtrap-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@schoolsystem.com
MAIL_FROM_NAME="School Management System"
```

### Option 3: SendGrid

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@schoolsystem.com
MAIL_FROM_NAME="School Management System"
```

### Option 4: AWS SES

```env
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
MAIL_FROM_ADDRESS=noreply@schoolsystem.com
MAIL_FROM_NAME="School Management System"
```

## Email Template Features

✅ **Professional Design** with school branding
✅ **Responsive** - looks good on all devices
✅ **Security Notice** - warns if user didn't request reset
✅ **Expiration Notice** - mentions 60-minute validity
✅ **Fallback URL** - includes plain text link if button doesn't work
✅ **Dynamic Year** - copyright year updates automatically

## Testing Email Sending

After configuring mail in `.env`, test it:

```bash
# Send test email
php artisan tinker
>>> Mail::raw('Test email', function($msg) { $msg->to('test@example.com')->subject('Test'); });
```

## Troubleshooting

### Emails not sending?

1. **Check logs:** `storage/logs/laravel.log`
2. **Verify .env:** Make sure MAIL_* variables are set
3. **Clear config cache:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```
4. **Check firewall:** Port 587 or 465 must be open
5. **Check credentials:** Ensure MAIL_USERNAME and MAIL_PASSWORD are correct

### Common Issues:

**Gmail "Less secure app" error:**
- Use App Password instead of regular password
- Enable 2FA first

**Connection timeout:**
- Check if port is blocked by firewall
- Try different port (587, 465, 2525)

**Authentication failed:**
- Double-check credentials
- Some providers require API keys instead of passwords

## Current Implementation Status

✅ **Backend:**
- Forgot password endpoint (`/api/forgot-password`)
- Reset password endpoint (`/api/reset-password`)
- Email template created
- Mail class created
- Token generation and storage
- Error handling

✅ **Frontend:**
- Forgot password page (`/auth/forgot-password`)
- Reset password page (`/auth/reset-password`)
- Form validation
- Success/error messages
- Auto-redirect after success

✅ **Security:**
- Token stored in `remember_token` field
- 60-minute expiration (recommended to implement)
- All sessions invalidated after reset
- Strong password requirements

## Recommended: Add Token Expiration

Currently, tokens don't expire. To add expiration, create a migration:

```php
php artisan make:migration add_password_reset_expires_at_to_users_table
```

Then add a `password_reset_expires_at` timestamp column and check it during reset.

