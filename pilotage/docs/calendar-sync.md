# Two-way calendar sync — setup and behaviour

Sessions appear in a coach's own calendar, and moving one there moves it here.
Google Calendar and Microsoft 365, over their REST APIs — no SDK, because zero
Composer dependencies is what keeps deploy at "rsync the files".

Nothing here is offered to a client-side user. They get the `.ics` attached to
the session, which is what they already expect from every other professional
they deal with; asking a client's bookkeeper to grant OAuth access to their work
calendar is a conversation nobody wants to have.

---

## What has to be in place

Three things, and the feature hides itself entirely until all three are true.

**1. An encryption key.** Refresh tokens are bearer credentials for someone's
entire calendar, valid until revoked. They are encrypted at rest with
`APP_SECRET_KEY`, so a database leak without the environment is useless.

```
php -r 'echo base64_encode(random_bytes(32));'
```

Put it in the server `.env` as `APP_SECRET_KEY`. Losing or rotating it does not
lose data — it invalidates the calendar connections, which have to be
reauthorised.

**2. OAuth credentials** for at least one provider. A provider with no client id
is not shown at all, because a Connect button that leads to a Google error page
is worse than no button.

**3. The redirect URI registered**, character for character. There is exactly
one per provider and it is at the bare domain — see below.

---

## Google

console.cloud.google.com → APIs & Services

- Enable the **Google Calendar API**.
- Credentials → Create credentials → OAuth client ID → Web application.
- Authorised redirect URI: `https://pilotagehq.com/calendar/callback/google`
  (the bare domain, not a tenant subdomain — see below for why)
- Scopes: `calendar.events` and `userinfo.email`.

`calendar.events`, not `calendar`. We write and read events; we have no business
creating or deleting someone's calendars, and asking for less is both correct
and easier to get through a consent screen review.

Two parameters in the authorisation URL are load-bearing and easy to lose:
`access_type=offline` and `prompt=consent`. Without both, Google withholds the
refresh token on re-authorisation and the connection dies silently about an hour
later.

```
GOOGLE_CLIENT_ID=...apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=...
```

While the OAuth consent screen is in "Testing", only accounts listed as test
users can connect. Publishing it requires Google's verification for these
scopes — budget real time for that before promising the feature to customers.

---

## Microsoft

portal.azure.com → App registrations → New registration

- Supported account types: accounts in any organisational directory and personal
  Microsoft accounts.
- Redirect URI (Web): `https://pilotagehq.com/calendar/callback/microsoft`
  (the bare domain, not a tenant subdomain — see below for why)
- API permissions → Microsoft Graph → Delegated: `Calendars.ReadWrite`,
  `offline_access`, `openid`, `email`.
- Certificates & secrets → New client secret.

`offline_access` is the one that returns a refresh token. Omitting it gets you an
hour of working sync and then silence.

```
MICROSOFT_CLIENT_ID=...
MICROSOFT_CLIENT_SECRET=...
```

---

---

## One redirect URI, for every firm

Register exactly one URI per provider, at the bare domain:

```
https://pilotagehq.com/calendar/callback/google
https://pilotagehq.com/calendar/callback/microsoft
```

Not `acme.pilotagehq.com/...`. Providers match redirect URIs character for
character and every firm lives on its own subdomain, so per-tenant callbacks
would mean registering one URI per customer — bookkeeping at ten firms and
impossible at a thousand.

The callback therefore lands on the apex with no tenant in scope, and does not
need one. The `state` nonce was minted against a tenant and a user; looking it
up recovers both, the token exchange happens there, and the user is bounced to
their own subdomain afterwards.

This is also why `state` is a database row rather than a session value. Session
cookies here are scoped to the exact tenant host (see the tenancy notes), so a
cookie-based state would be unreadable at the apex — precisely where it is
needed.

The state is single-use, expires in fifteen minutes, and is consumed by a
conditional UPDATE rather than a read followed by a write, so a replayed
callback loses the race instead of being honoured twice.

## How it behaves

The rules are in `CalendarSync`, tested individually in
`tests/CalendarSyncTest.php`, and worth stating because each one is a decision
rather than an accident.

**Only future sessions are pushed**, from a day ago to `HORIZON_DAYS` (180)
ahead. Nobody wants nine months of finished coaching sessions appearing in their
diary the first time they connect.

**Last writer wins, and the loser is told.** A session moved in both places
between two syncs is a real conflict with no correct answer. The more recent
change wins; the overwrite is recorded on the timeline and notified. What
actually hurts a coach is not "the wrong time won" — it is "the time changed and
nobody can say why".

**Only the time comes back.** The title and description are generated from the
engagement and are not read back from the calendar. Letting a calendar edit
rename an engagement's session is a surprising amount of blast radius for a typo.

**A delete over there is not a delete over here.** Removing an event from your
calendar usually means "off my view", not "cancel this engagement's session". So
a remote delete unlinks, tells the coach, and — importantly — the event is *not*
recreated on the next tick. It returns only if the session genuinely changes.
Getting that wrong means a coach deletes a meeting, watches it reappear five
minutes later, and switches the whole feature off.

The reverse is not symmetrical: cancelling a session **here** does remove the
event there, because that one is unambiguous.

**Echoes are suppressed.** Each push stores a digest of what it sent. An
unchanged session costs no API call at all, and a change pulled from the
provider is re-digested so it is not immediately pushed back.

**An expired sync token is an instruction, not an error.** Both providers expire
their incremental-sync tokens and signal it with a 410. The only correct
response is to discard the token and read again in full. Treating it as a
failure means the sync silently stops seeing changes while continuing to report
success.

---

## Operating it

Sync runs on the five-minute tick — pull first, then push. There is no daemon
and no cron: the tick is triggered by page loads (see `Core\Heartbeat`), with
`/_tick/{token}/five-minute` available for an uptime monitor to keep a quiet
site moving.

To run it by hand:

```
php cron/tick.php five-minute
```

A connection whose refresh is refused is marked `needs_reauth` and skipped from
then on, rather than hammering a revoked grant every five minutes forever. The
coach sees "Needs reconnecting" on `/calendar` with the reason.
