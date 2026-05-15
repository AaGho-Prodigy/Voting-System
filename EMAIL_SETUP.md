# Email Setup Instructions

To enable OTP emails, you need to configure Gmail SMTP settings.

## Step 1: Enable 2-Factor Authentication
1. Go to your Google Account settings
2. Enable 2-factor authentication if not already enabled

## Step 2: Generate App Password
1. In Google Account settings, go to "Security"
2. Under "Signing in to Google", click "App passwords"
3. Select "Mail" and "Other (custom name)"
4. Enter "Voting System" as the name
5. Copy the 16-character password generated

## Step 3: Configure Email Settings
1. Open `includes/email_config.php`
2. Replace `'your-email@gmail.com'` with your actual Gmail address
3. Replace `'your-app-password'` with the App Password you generated

## Step 4: Enable Less Secure Apps (Alternative)
If you don't want to use App Passwords:
1. Go to Google Account settings
2. Under "Security", enable "Less secure app access"
3. Use your regular Gmail password in the config

## Troubleshooting
- Make sure your Gmail account has 2FA enabled for App Passwords
- Check that "Less secure app access" is enabled if using regular password
- Verify your internet connection allows SMTP (port 587)
- Check PHP error logs for detailed error messages

## Alternative Email Providers
For other providers, update the SMTP settings in `email_config.php`:

- **Outlook/Hotmail**: smtp-mail.outlook.com, port 587
- **Yahoo**: smtp.mail.yahoo.com, port 587
- **Custom SMTP**: Adjust HOST, PORT, and credentials accordingly