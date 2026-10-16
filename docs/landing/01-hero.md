---
view: components.packages.package-hero
badges:
  - label: PHP Package
    color: blue
  - label: "{package.latestVersionLabel}"
    color: green
  - label: "{package.license} License"
    color: purple
  - label: "{package.downloadsShort}+ downloads"
    color: blue
eyebrow: "{package.name}"
title: Know exactly
highlight: what changed
buttons:
  - label: View Full Documentation
    href: "{card.docsUrl}"
    style: primary
    icon: arrow-right
  - label: View on GitHub
    href: "{package.githubUrl}"
    style: ghost
    external: true
install: "{card.install}"
labels:
  copy: Copy
  copied: Copied!
---

`array_diff()` misses nested changes and shifts every index after an insertion. Diff compares two PHP values and tells you what was added, removed, modified or moved, at the exact path, with records matched by ID and secrets redacted. For text, it gives you GitHub-style line diffs with hunks, word highlighting, patches that `git apply` accepts, and HTML.
