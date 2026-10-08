# Audiobook script

Turn a book summary into something a narrator can read aloud, start to finish,
without stopping to work out how to say it.

This is a transformation, not a reformat. The input was written to be *read* —
it has headings, bullet lists, page references and bold text, none of which
exist in audio. Everything has to be carried by sentences instead.

## Register

- **Spoken English, not written English.** Shorter sentences. Contractions.
  Say "here's the thing" where the page said "it is important to note that."
- **No visual furniture.** No headings read aloud, no "bullet point one," no
  "as the table shows," no "see page 42." If the summary cites a page, either
  drop it or turn it into "about a third of the way in."
- **Transitions are spoken.** A heading break becomes a sentence: "That covers
  the pricing side. The next part is about who you say it to."
- **Numbered things get counted out loud.** "There are four of these. The first
  one is..." — a listener cannot see how long the list is, so tell them.
- **Nothing unpronounceable.** Expand abbreviations on first use. "ROI" becomes
  "return on investment, R-O-I" the first time and "R-O-I" thereafter. Currency
  and figures get spoken out: "$4,200" becomes "forty-two hundred dollars."

## Structure

1. **Cold open, 2–4 sentences.** What the book argues and who should care. No
   "Welcome to the summary of." Start with the claim.
2. **Attribution up front.** Name the author and the book once, early, plainly:
   "This is a summary of *Title*, by Author." Say it once and move on.
3. **The body**, following the summary's order. Big ideas first, then the
   chapter-by-chapter material, then the limits.
4. **Close, 3–5 sentences.** The one thing to remember and the one thing to do.
   Not a recap of everything.

## Copyright — the hard constraint

The source is an in-copyright book. Anything in the input marked
`**[verbatim — do not republish]**` is transcribed word-for-word from it.

- **Never read verbatim material aloud as-is.** Describe what the tool does and
  how it works, in your own words. "He gives a checklist for this — it walks
  through the documents you gather before the first meeting, starting with the
  last two pay stubs" is fine. Reciting the list is not.
- Quote at most one short line per section, and only where the author's exact
  phrasing *is* the point. Attribute it out loud when you do.
- If a section cannot be conveyed without reproducing it, summarise the
  intent and say the tool is in the book. Losing a detail is the correct
  trade here.

## Output

Plain prose. No markdown, no headings, no list markers, no stage directions,
no `[PAUSE]` markers — a narrator adds those. Paragraph breaks only, one per
spoken beat.

Open with a single line in exactly this form, then a blank line, then the script:

```
TITLE: <the spoken title, e.g. "Values-Based Financial Planning — Summary">
```

Aim for the requested runtime. Narration runs about 150 words per minute, so a
twelve-minute script is roughly 1,800 words. Coming in a little under is better
than padding to hit it.
