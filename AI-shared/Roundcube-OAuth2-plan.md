# Roundcube with Microsoft Exchange: OAuth2 (Modern Auth) plan

Status: **not implemented**. This file explains why an Exchange Online / Microsoft 365 mailbox
can't be used in epesi today, and how to add it.

Microsoft's server settings:
<https://support.microsoft.com/en-us/outlook/pop-imap-and-smtp-settings-for-outlook-com>

## What Microsoft requires

- **Exchange Online / Microsoft 365** (`outlook.office365.com:993` for IMAP,
  `smtp.office365.com:587` for SMTP) turned off password ("basic auth") logins for IMAP.
  Password logins for SMTP are being turned off too. Clients have to use **XOAUTH2**: they
  send an OAuth2 access token from Microsoft Entra ID (formerly Azure AD) instead of a
  password.
- The token comes from an **app registration** in Entra ID: a client ID and secret, a
  redirect URI, and the scopes `https://outlook.office.com/IMAP.AccessAsUser.All`,
  `https://outlook.office.com/SMTP.Send` and `offline_access`, which gives a refresh token.
- An access token lasts about an hour. The refresh token renews it, for roughly 90 days of
  inactivity or until an admin revokes it.

## What Roundcube supports

Roundcube 1.5 and later supports OAuth2 out of the box (`oauth_provider`, `oauth_client_id`,
`oauth_auth_uri`, `oauth_token_uri`, `oauth_scope`, …), and it works with Microsoft 365.
However, that built-in flow replaces Roundcube's **own login form** with a "Login with
Microsoft" button. epesi never shows that form: users only get into Roundcube through the
Mailbox page's ticket (see [Epesi-Laravel-Roundcube.md](Epesi-Laravel-Roundcube.md)).

## Why it doesn't fit epesi today

- [epesi_sso.php](../modules/Epesi/Roundcube/roundcube-plugins/epesi_sso/epesi_sso.php)
  (`authenticate`) logs in with a one-time ticket. The ticket carries the IMAP user and
  **password** from `MailAccount`, which
  [TicketIssuer.php](../modules/Epesi/Roundcube/src/Services/TicketIssuer.php) fills in.
- `smtp_connect` in the same plugin passes an SMTP password the same way.
- The rest of the Mail module is password-based as well:
  [WebklexMailbox.php](../modules/Epesi/Mail/src/Services/Imap/WebklexMailbox.php) for IMAP
  and [SmtpTransportFactory.php](../modules/Epesi/Mail/src/Services/SmtpTransportFactory.php)
  for MailSender.

So an Exchange account added today would be refused at login.

## How to make it work

Epesi, not Roundcube, should own the OAuth2 tokens:

1. **Mail account setting:** add an authentication method (password / Microsoft OAuth2) to
   `MailAccount`, and store the encrypted refresh token, the access token and its expiry.
2. **Connect flow:** add a "Connect Microsoft account" button on the mail account form. It
   redirects to Microsoft, and a callback route exchanges the code for tokens. Laravel
   Socialite with the `socialiteproviders/microsoft` package, or plain HTTP calls, can do
   this. The tenant ID, client ID and secret go in the Administration panel.
3. **Token refresh:** add a service that returns a valid access token and refreshes it when
   it's close to expiring.
4. **Roundcube:** `TicketIssuer` puts the access token in the ticket. `epesi_sso` passes it
   as the password and tells Roundcube to use XOAUTH2 (`imap_auth_type` / `smtp_auth_type` =
   `XOAUTH2`). The token is only valid for about an hour, so long Roundcube sessions also
   need a refresh hook. The `oauth` code in Roundcube core shows how it does this.
5. **Epesi's own mail code:**
   - webklex/php-imap supports `'authentication' => 'oauth'` with the token as the password.
   - Symfony Mailer has an `XOAuth2Authenticator` for SMTP.

   Both are small changes once the token service exists.

**Effort:** moderate. Most of the work is the token storage, the Connect/callback flow and
the refresh logic. The Roundcube and IMAP/SMTP changes are small. Google Workspace and Gmail
use the same XOAUTH2 mechanism, so the same work would cover them with a second provider.

**What the customer has to do:** the Microsoft 365 admin must register the app (or approve
ours) in Entra ID, grant the IMAP/SMTP permissions, and leave IMAP and SMTP AUTH enabled for
the mailboxes.

**Stopgap without code:** some tenants still allow app passwords, or an older on-premises
Exchange server may still accept passwords. In that case the current password login works.
Exchange Online / Microsoft 365 won't accept passwords, so it needs the OAuth2 work above.
