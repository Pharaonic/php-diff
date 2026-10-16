---
view: components.packages.features
variant: compact
badge: Key Features
title: Everything you need to see what changed
subtitle: One call for the default, immutable options when you need rules.
items:
  - icon: code-brackets
    title: Changes at Exact Paths
    text: "Every change has a type and a path such as `user.email`, with its old and new values, and `wasModified()` or `hasChanged()` answer questions directly."
  - icon: users
    title: Records Matched by ID
    text: "`identify('users', 'id')` compares lists of records by key, and `detectMoves()` reports the ones that changed order."
  - icon: lock
    title: Redaction Built In
    text: "`redact('user.password')` still detects the change but stores `[REDACTED]`, even inside parent and removed values."
  - icon: switch
    title: Strict and Predictable
    text: "`1`, `'1'` and `1.0` differ, a missing key is not `null`, map key order is ignored, and dates compare by instant."
  - icon: lines
    title: GitHub-Style Text Diffs
    text: "Line diffs with hunks, word or character highlighting, and unified patches that `git apply` and `patch` accept."
  - icon: browser
    title: HTML Ready
    text: "`toHtml()` returns an escaped table, styled by the optional `resources/diff.css` in light and dark mode."
---
