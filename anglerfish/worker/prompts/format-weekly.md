# The weekly post

One shape, every week: five movements. When the requested format is `weekly`, ignore the
six rotating formats above; they are an archive, not post types.

The post takes a decision the reader already knows they should make and hasn't. It gives
them a procedure that makes the decision for them, and it names the belief that has been
stopping them. Practical first, with the psychology inside.

Roughly 1,100-1,500 words of body.

## 1. The trap (about 150-250 words)

Cold open on the moment the reader recognizes. No windup, no "in today's economy." "Every
April, around the 10th, you make a promise."

Bring in Jassen's own proof early: one of the stories listed in the voice spec where he
made this decision or failed to, or whatever the operator's angle supplies. If neither
fits, leave `[STORY: ___ suggested: <story title>]` and move on. Never invent one.

Stack two or three specific scenarios in quick phrases. Lead with a tax practice, and make
at least one from another practice (a massage therapist, a counselor, an energy healer, a
coach).

Close with the turn: one short standalone line that flips the reader's assumption. "Every
one of those was a pricing decision. You just made it by not making it."

## 2. The diagnostic (about 200-300 words)

The load-bearing movement. The reader applies something to their own practice right now
and arrives at the diagnosis themselves.

- One number to calculate, what it means, and what to do at each level.
- Five to seven yes/no questions, no answers supplied, closed with a threshold: "If you
  said no to three or more of these, you have your project for the month."

Write both for any practice. A tax example can sit beside a question; the question itself
must work for a counselor.

## 3. The psychology beat (about 150-250 words)

- Name the belief in the reader's own words: the sentence they hear in their head when
  they think about doing this ("I'll lose the regulars," "who am I to charge that").
- Use a named researcher only where the material supplies one.
- Say why the belief is partly true. That's why nobody argues with it, and saying so takes
  the air out of it.
- Don't try to cure it. The procedure routes around it; the deeper work is what coaching
  is for.
- Coaching and education language. No diagnosing, no clinical claims, no promises about
  hypnosis.

## 4. The protocol (about 400-600 words)

Numbered, sequential, literal. A procedure the reader can run this week.

- The steps make the decision for the reader: a formula sets the number, defaults decide
  who and when, a script removes the wording decision.
- Always give exact words: a script, a notice, an email, a line to say when the client
  asks. Drop out of prose straight into the copy-paste.
- Always include a commitment step: say the number out loud ten times; put the date on the
  calendar and tell one person.
- Name the technique ("the November fee reset," "the script swap").
- Written out in full. The PDF is the working copy of what the reader just read, never the
  continuation of it. Anything that renders as a list appears in both places.

## 5. The close (about 120-200 words)

- Recap the job in one line: "One afternoon, ten returns, three hassle fees, and a script."
- Repeat the single most important action, verbatim, two or three times, escalating.
- Mention the download once, plainly.
- Make exactly one invitation, tied to the topic:
  - **Behind the Books**, the free monthly Zoom for practitioners and business owners
    (financialhypnosis.com/biz), with this month's topic named if the angle gives it.
  - **The Steady Ledger**, six one-to-one sessions on business money blocks: the numbers,
    worth and paying yourself, the fee, being seen, invoices and collecting, a one-page
    90-day plan. Use it when the post maps to one of those sessions.
  - **The free first conversation** on financialhypnosis.com.
  - **A referral line** for tax pros: "You already know which client I mean."
- No heading over the close. No comment ask, no tool plug, no goading.

## Shape and rhythm

- Title is a reframe, not a topic: "The Fee You Promised Yourself Last April," not "How to
  Raise Your Fees." Subtitle is one plain line.
- One idea only. A second idea becomes next week's post.
- Sub-section openers double as headers: the punchy line is the first sentence of the
  paragraph, not a separate bold line above it.
- Short paragraphs and white space as rhythm, but prefer paragraphs longer than two
  sentences where the talk still flows.

## The standing risks

- The post degrading into an ad for the download or the invitation. Full trap, diagnostic,
  psychology beat and protocol in the body.
- The post becoming pure psychology. Every post ends with the reader holding a procedure.
- The download or protocol only working for tax pros. A massage therapist must be able to
  use every line.

## The download

Every weekly post ships with a two-page PDF. Write its content after the body, as JSON,
in the container the operator picked (named in the user turn). Rules for every
container:

- **Practice-agnostic.** A counselor, an energy healer and a tax pro must each be able to
  work every line. Say "client," "session," "fee," "the busy season." Tax specifics go in
  a `hint`, never in an item or label.
- **The psychology is inside the download too.** Every container carries the belief or
  behavior pattern the post names, as described per container below. A download that is
  only logistics is wrong.
- **It is the working copy of the post,** not new material: the post's number, questions,
  steps, scripts and commitment, formatted to fill in with a pen.
- Plain text inside the JSON: no markdown, no emoji. Keep it to two printed pages.

Shared shape: `{"container": "<container>", "title": "...", "intro": "...", "sections":
[...]}`. A section has a `title`, an optional `instruction`, and then `items` (a list of
`{"text": "...", "note": "..."}`, note optional), `fields` (a list of `{"label": "...",
"hint": "...", "lines": 1-3}`), and/or `write_lines` (a number of blank lines).

**test** — a scored self-assessment. Three sections of four `items` each, every item a
statement the reader scores 1 (not true at all) to 5 (completely true), written so a
higher score means a bigger leak. The third section scores the belief and the behavior
("How you say it," "What you avoid"), and carries a `note`: "If any one section totals 16
or more, that section is your project this month, whatever your total." Then a fourth
section of `fields`: the post's number, each protocol step, the script, and the
commitment. Add `"scoring": {"max": 60, "bands": [{"range": "12 to 27", "meaning":
"...", "action": "..."}, {"range": "28 to 43", ...}, {"range": "44 to 60", ...}]}`. The
max must equal the scored items times five.

**checklist** — a sequence done repeatedly. Two to four sections of `items`, each item one
action, in order, with a `note` where a step needs a reason. One item names the sentence
the reader will hear in their head at the hard step and their answer to it, and the last
section is the commitment: the date, the person told, and when the next run is on the
calendar.

**worksheet** — a decision written down. Four to seven sections, each a prompt with
`write_lines` or `fields`, in the order the post works through it. One section is the
psychology: fields for "The sentence in my head," "What's true about it," and "My
business answer." Finish with the exact words (script or notice) and the commitment.

**role_document** — a position contract for something being handed off. Sections of
`fields`: the role (name, reports to, hours, one-line purpose), three to four
accountabilities ("Owns"), three results with how each is measured, "What I stop doing"
(`write_lines`), "What I'm afraid happens if I hand this off" with the check that would
settle it, and signatures.
