# Review: Minimal Diff

Goal: the smallest honest solution to the problem, reviewable in a few minutes.

## Check

1. **Could this have been a parameter?**
   For every new method, class, interface or file: name the existing place it could have
   lived and say why it does not fit. A new file without that justification is a finding.

2. **Single-caller abstraction.**
   Any interface, factory, strategy, event, subscriber, config option or feature flag with
   exactly one caller and no second caller in sight. Finding.

3. **Duplication.**
   Search `shopware/` for code that already does this — a service, a struct, a helper, a
   calculation. Grep for the domain nouns in the diff, not just for exact strings. If a
   near-duplicate exists, that is a finding even if the new code is nicer.

4. **Unrelated changes.**
   Renames, reformatting, import reordering, comment edits, whitespace, or fixes to code
   the task did not ask about. Each is a finding — it belongs in a separate change.

5. **Spread.**
   Many files touched for a small behavioural effect: would one change at a lower level have
   done it? Say it concretely — "these 6 call-site edits collapse into one default in X".

6. **Speculative scope.**
   Backwards-compatibility shims, deprecation paths, extra config, null-safety for cases
   that cannot occur, handling of inputs that no caller produces. Finding.

7. **Dead weight.**
   Code, constants, parameters or struct fields added but never read. Finding.

## Do not report

- Style, formatting, naming — CS Fixer and the conventions reviewer cover those.
- Missing tests — tests come after this review, by design.
- "Consider extracting…" suggestions that add code. This review only removes.

Order findings by how much code the fix removes.
