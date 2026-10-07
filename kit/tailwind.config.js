/** Advisor Field Kit Tailwind config.
 *
 * Colours resolve to `rgb(var(--token) / <alpha-value>)` so the palette can be
 * changed by editing includes/palette.php alone — no Node, no rebuild — and so
 * opacity modifiers (`border-bad/30`, `bg-surface/95`) actually generate rules.
 * The tokens are therefore RGB channel triplets, not hex; palette.php converts. Rebuild only when you add
 * or remove utility *classes*.
 *
 * `content` globs every file that emits markup rather than naming them one by
 * one. Missing a file does not error; the classes just silently never get
 * generated, which is a miserable bug to chase down later.
 */
module.exports = {
  content: [
    './public/**/*.php',
    './includes/**/*.php',
    './templates/**/*.php',
    './public/assets/*.js',
  ],
  theme: {
    extend: {
      colors: {
        paper:       'rgb(var(--paper) / <alpha-value>)',
        surface:     'rgb(var(--surface) / <alpha-value>)',
        sunk:        'rgb(var(--sunk) / <alpha-value>)',
        ink:         'rgb(var(--ink) / <alpha-value>)',
        muted:       'rgb(var(--muted) / <alpha-value>)',
        hairline:    'rgb(var(--hairline) / <alpha-value>)',
        primary:     'rgb(var(--primary) / <alpha-value>)',
        'primary-ink':  'rgb(var(--primary-ink) / <alpha-value>)',
        'primary-soft': 'rgb(var(--primary-soft) / <alpha-value>)',
        good:        'rgb(var(--good) / <alpha-value>)',
        'good-soft': 'rgb(var(--good-soft) / <alpha-value>)',
        warn:        'rgb(var(--warn) / <alpha-value>)',
        'warn-soft': 'rgb(var(--warn-soft) / <alpha-value>)',
        bad:         'rgb(var(--bad) / <alpha-value>)',
        'bad-soft':  'rgb(var(--bad-soft) / <alpha-value>)',
      },
      fontFamily: {
        sans:    ['ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Helvetica Neue', 'Arial', 'sans-serif'],
        display: ['ui-serif', 'Iowan Old Style', 'Palatino Linotype', 'Palatino', 'Georgia', 'serif'],
        mono:    ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Consolas', 'monospace'],
      },
      maxWidth: { readable: '46rem' },
    },
  },
  plugins: [],
};
