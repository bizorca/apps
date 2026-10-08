# Phase 0 — Infrastructure checklist

The code half of Phase 0 is done and tested. This is the half that has to be
done by hand, in a browser, against a registrar and a host. Nothing in Phase 1
can be tested honestly until these are green.

Work top to bottom — later steps depend on earlier ones.

---

## 0. What is already true (checked 2026-08-07)

- `pilotagehq.com` is registered at **Cloudflare** (created 2026-08-06) and its
  nameservers are `josh.ns.cloudflare.com` / `tegan.ns.cloudflare.com`.
- The apex resolves to Cloudflare proxy IPs, so traffic is **orange-clouded**.
- **The wildcard certificate problem is already solved.** Cloudflare Universal
  SSL is live and the edge cert's SAN list is
  `DNS:pilotagehq.com, DNS:*.pilotagehq.com`. This was flagged as the likeliest
  snag in the whole phase; it is not a snag. There is no need to move DNS to
  SiteGround or buy a wildcard cert.
- **No wildcard DNS record exists yet** — `acme.pilotagehq.com` and friends do
  not resolve. This is now the actual blocker.
- **Mail works.** Verified 2026-08-07 by a live send from production to Gmail:
  processed by SMTP2GO and delivered to a Google MX four seconds later, with
  `dkim=pass` in the recipient's `Authentication-Results`.

## 1. DNS for pilotagehq.com

Wildcard DNS is what makes `firmname.pilotagehq.com` resolve without touching
DNS per tenant. Without it, every new firm is a manual DNS edit, which is not
a business.

In the Cloudflare dashboard for pilotagehq.com:

| Type | Name | Content | Proxy |
|---|---|---|---|
| A | `@` | SiteGround origin IP | Proxied (orange) |
| A | `*` | same IP | Proxied (orange) — **this is the wildcard** |
| A | `www` | same IP | Proxied |
| A | `ssh` | same IP | **DNS only (grey)** — SSH cannot traverse the proxy |

The `ssh` row matters: the credentials name `ssh.pilotagehq.com` as the SSH
host, but it has no record, and even with one it must be grey-clouded because
Cloudflare's proxy only forwards HTTP(S) ports.

Verify before moving on:

```bash
dig +short anything.pilotagehq.com     # must return the SiteGround IP
dig +short pilotagehq.com
```

If `anything.pilotagehq.com` does not resolve, tenancy does not work. Stop here.

---

## 2. SSL — mostly done

Cloudflare Universal SSL already covers `*.pilotagehq.com` at the edge, so
browsers are satisfied the moment the wildcard DNS record exists. What remains
is the **origin** leg, Cloudflare to SiteGround:

- Set the Cloudflare SSL mode to **Full (strict)**. "Flexible" would leave the
  Cloudflare-to-origin hop in plaintext, which for a product carrying client
  financial documents is not acceptable.
- Install a **Cloudflare Origin Certificate** on SiteGround (free, 15-year,
  issued from the Cloudflare dashboard). It is only ever presented to
  Cloudflare, so it does not need to be publicly trusted.

Note the consequence of proxying: SiteGround sees Cloudflare's IPs, not the
visitor's. `RateLimiter` keys on `REMOTE_ADDR`, so the per-IP budget would
collapse into a handful of Cloudflare addresses and stop discriminating.
Read `CF-Connecting-IP` (validated against Cloudflare's published ranges)
before the rate limiter goes live in production.

Check the edge cert:

```bash
echo | openssl s_client -servername test.pilotagehq.com -connect pilotagehq.com:443 2>/dev/null \
  | openssl x509 -noout -subject -ext subjectAltName
```

The SAN list must contain `*.pilotagehq.com`. As of 2026-08-07 it does —
issuer Google Trust Services, via Cloudflare Universal SSL.

---

## 3. Mail authentication — working, with one thing left to confirm

**Verified working 2026-08-07.** A live send from production reached Gmail in
four seconds and shows `dkim=pass`. Outbound mail is not a blocker.

**Do NOT add an SPF include for SMTP2GO.** An earlier draft of this document
said to add `include:spf.smtp2go.com`; that was wrong for this provider.
SMTP2GO rewrites the envelope return-path to a domain of their own, so SPF is
evaluated against **them** and passes without any record on `pilotagehq.com`.
Adding the include would be harmless but pointless, and editing the existing
Cloudflare SPF record for no reason risks breaking inbound Email Routing.

The current record is Cloudflare's own, and should stay as it is:

```
v=spf1 include:_spf.mx.cloudflare.net ~all
```

**The consequence of that return-path rewrite** is the thing to understand
before touching DMARC. DMARC passes only if SPF *or* DKIM both passes **and is
aligned** with the From domain. Because the return-path belongs to SMTP2GO,
SPF can never be aligned here — which means **DKIM alignment is the only route
to a DMARC pass**.

`dkim=pass` alone does not settle it. The question is what `d=` says in the
DKIM-Signature header:

- `d=pilotagehq.com` — aligned. DMARC passes. Safe to tighten.
- `d=smtp2go.net` or similar — signed by the relay, **not** aligned. Mail is
  delivering fine today only because DMARC is at `p=none`.

Check it before changing anything:

```bash
dig +short TXT _dmarc.pilotagehq.com
dig +short CNAME <selector>._domainkey.pilotagehq.com
```

If no `_domainkey` CNAMEs exist on pilotagehq.com, the signature is almost
certainly SMTP2GO's own domain rather than yours. Add the CNAMEs from the
SMTP2GO dashboard (Sending → DKIM) — never invent selector names.

**DMARC** is currently:

```
v=DMARC1; p=none; rua=mailto:...@dmarc-reports.cloudflare.net
```

`p=none` is the correct posture until DKIM alignment is confirmed. **Do not
move to `p=quarantine` or `p=reject` until a report or a raw header shows
`dmarc=pass`** — tightening while nothing is aligned would silently destroy
Pilotage's own magic-link and reminder mail, and the failure looks like
"users say they never got the email" rather than like an error.

**Non-sending domains** — `getpilotage.com` and `pilotage.cc` should each get
`v=spf1 -all` on `@` plus a reject DMARC. They redirect and never send; saying
so explicitly stops anyone spoofing them.

**Non-sending domains** — TXT on `@` for both `getpilotage.com` and
`pilotage.cc`:

```
v=spf1 -all
```

Plus DMARC `p=reject` on each. They redirect; they never send. Saying so
explicitly stops anyone spoofing them.

Verify:

```bash
dig +short TXT pilotagehq.com
dig +short TXT _dmarc.pilotagehq.com
dig +short CNAME <selector>._domainkey.pilotagehq.com
```

Then send a test through SMTP2GO to a Gmail address and check the raw headers
for `spf=pass`, `dkim=pass`, and `dmarc=pass`.

---

## 4. The other two domains

- **getpilotage.com** — 301 to `https://pilotagehq.com/`. Either point it at
  the same SiteGround docroot (the `.htaccess` already handles it) or use a
  registrar-level forward. Registrar forwarding is simpler and one less thing
  in the app's path.
- **pilotage.cc** — needs to reach the app, because `/t/<token>` is a real
  route handled in Phase 2 (FR-8.7). Point it at the same docroot. Everything
  outside `/t/` is redirected to the product domain by `.htaccess`.

---

## 5. Local development

Tenant subdomains need to resolve locally too. `.test` does not resolve by
default on macOS.

Simplest option — add the hosts you actually use:

```
127.0.0.1  pilotage.test
127.0.0.1  acme.pilotage.test
127.0.0.1  northstar.pilotage.test
```

Better option, if you'll be creating tenants often — `dnsmasq` gives you a
real wildcard:

```bash
brew install dnsmasq
echo 'address=/pilotage.test/127.0.0.1' >> $(brew --prefix)/etc/dnsmasq.conf
sudo brew services start dnsmasq
sudo mkdir -p /etc/resolver
echo 'nameserver 127.0.0.1' | sudo tee /etc/resolver/test
```

Then run:

```bash
php -S 127.0.0.1:8080 -t public
```

Without a hosts entry you can still test everything by sending the Host header
directly, which is how the smoke tests were run:

```bash
curl -H "Host: acme.pilotage.test" http://127.0.0.1:8080/_health
```

---

## 6. Sign-off

Phase 0 is done when all of these are true:

- [ ] `dig +short anything.pilotagehq.com` returns an address
- [x] The TLS SAN list includes `*.pilotagehq.com` (Cloudflare Universal SSL)
- [ ] Cloudflare SSL mode is Full (strict) with an Origin Certificate installed
- [ ] `ssh.pilotagehq.com` exists as a DNS-only record, or the SiteGround
      hostname is recorded instead
- [ ] `CF-Connecting-IP` is honoured so rate limiting still discriminates
- [ ] `getpilotage.com` 301s to `pilotagehq.com`
- [ ] `pilotage.cc/t/test` reaches the app; everything else on it redirects
- [x] A live send reaches Gmail (verified 2026-08-07, `dkim=pass`)
- [ ] DKIM `d=` confirmed as `pilotagehq.com`, not SMTP2GO's domain
- [ ] `dmarc=pass` observed before tightening DMARC past `p=none`
- [ ] `php migrate.php --status` shows all migrations applied on the server
- [ ] `php tests/run.php` passes against the server's test schema
- [ ] `https://<a-real-tenant>.pilotagehq.com/_health` renders with `database: ok`
