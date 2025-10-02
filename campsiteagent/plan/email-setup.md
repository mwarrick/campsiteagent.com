# Gmail SMTP Setup (Google App Password / API-based SMTP)

This project sends email via Gmail SMTP using a Google app password (API-based SMTP). Follow these steps for both local and server environments.

## 1) Prepare the Gmail sender
- Use a dedicated sender account (recommended)
- Enable 2-Step Verification on the Gmail account
- Create an App Password for "Mail":
  1. Google Account → Security → App passwords
  2. App: Mail; Device: Other (Campsite Agent)
  3. Copy the 16-character app password

Notes:
- "Less secure app access" is deprecated; use app passwords
- Respect Gmail sending limits and policies

## 2) Environment variables
Configure the following variables on both local and server:

```bash
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your_gmail_account@example.com
SMTP_PASSWORD=your_google_app_password
SMTP_ENCRYPTION=tls
MAIL_FROM=no-reply@campsiteagent.com
MAIL_FROM_NAME=Campsite Agent
```

Server security tips:
- Store secrets in environment variables or a secure secrets manager
- Restrict file permissions; never commit secrets to the repo
- Rotate app passwords periodically

## 3) Server configuration (Ubuntu)
- Allow outbound to smtp.gmail.com:587
- Set environment variables via systemd, shell profile, or a protected .env file (chmod 600)
- Keep server time in sync (chrony/ntp) to prevent TLS issues

## 4) Testing checklist
- Send a test email (once implemented) and confirm delivery
- Verify From address and display name
- Check spam folder and adjust content if needed
- Monitor logs for SMTP errors and retry behavior

## 5) Operational considerations
- Gmail quotas: pace sends; use exponential backoff on transient failures
- Handle 4xx with retries; treat 5xx as hard failures
- Maintain logs for sends and failures

---

# Gmail API (OAuth2) Setup

This project also supports sending mail via the Gmail API (recommended). See Google’s guide: https://developers.google.com/workspace/gmail/api/guides/sending

## Scopes
- `https://www.googleapis.com/auth/gmail.send`

## Steps
1. Create a Google Cloud project and enable the Gmail API
2. Configure OAuth consent screen (Internal or External as appropriate)
3. Create OAuth client credentials (Desktop app for CLI flow)
4. Download the client credentials JSON file
5. Set these env vars in `.env`:

```bash
GOOGLE_CREDENTIALS_JSON=/absolute/path/to/credentials.json
GOOGLE_TOKEN_JSON=/absolute/path/to/token.json
MAIL_FROM=your_sender@example.com
MAIL_FROM_NAME=Campsite Agent
```

6. Run the test sender (first run will prompt for consent):
```bash
php bin/send-test-gmail-api.php your@email
```
A browser URL will be printed; complete consent and paste the code back. The token is saved to `GOOGLE_TOKEN_JSON`.
