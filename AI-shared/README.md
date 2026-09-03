# AI-shared/

Developer notes for working on Epesi — written so someone starting from scratch, on any
machine, does not have to rediscover what has already been learned. Git-tracked, so they
travel with `git clone`/`git pull`.

**What belongs here:** how Epesi works and how to build a module on it. Concepts,
conventions, recipes, and the traps that cost real time.

Read `CLAUDE.md` at the repo root first — it carries the architecture, commands and
environment quirks. This folder is the lower-ceremony layer underneath it.

## Start here

- **[Dev-Tutorial.md](Dev-Tutorial.md)** — how to write a module. Part A builds a working
  one from scratch; Part B is the full reference. Paired with `modules/Custom/Tutorial/`.
- [design-philosophy.md](design-philosophy.md) — why Epesi is built the way it is, and the
  test to apply to any change that touches how declared data becomes a screen.
- [environment-and-setup.md](environment-and-setup.md) — getting a working dev install,
  seeding it with demo data, and the environment traps that look like application bugs.

## Building on the framework

- [recordbrowser-recipes.md](recordbrowser-recipes.md) — changing a recordset that already
  holds data, column overrides, addon tabs, tooltips, and the callback rules behind them.
- [theming-and-frontend.md](theming-and-frontend.md) — icons, where a module's CSS goes,
  and the JavaScript conventions.
- [help-tutorials.md](help-tutorials.md) — adding a guided Help tutorial to a module.

## When something is wrong

- [bug-patterns.md](bug-patterns.md) — recurring failure shapes, indexed by symptom. Check
  here when something misbehaves in a way that feels familiar.
- [dont-reintroduce.md](dont-reintroduce.md) — features and APIs removed on purpose. Read
  before "restoring" anything.
- [performance.md](performance.md) — profiling a slow page, and the caching rules.

## Framework internals

- [framework-internals.md](framework-internals.md) — how the framework itself is built:
  grid column sizing, code that must not be tidied away, the standalone entry points, the
  menu render paths. You need this only when changing the framework's own machinery.

## Conventions

**Never grep `vendor/` looking for code to fix.** Third-party code is out of scope for
patches — if a bug's root cause traces into vendor code, look for where *our own* code
calls into it or configures it. Searching vendor to *understand* behaviour is fine.

**Never write screenshots or scratch output into the repo.** Always target a scratch
directory, with an absolute path — a bare filename resolves to the repo root and lands next
to the codebase.

## Keeping this folder useful

If you land on a fact that would have saved real time had you known it up front, add it
here rather than only in your own private memory.

- **Keep it short, and keep it present-tense.** These are notes for someone who needs to
  act. If it describes work already finished, it does not belong here.
- **Update the file that already covers it** rather than adding a second one that
  disagrees. One fact, one home.
- **Never name a specific install, host, account, credential or customer.**
