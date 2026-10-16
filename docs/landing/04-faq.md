---
view: components.home.faq
badge: FAQ
title: "{package.name}"
highlight: Questions
subtitle: "Quick answers about installing and using {package.name}."
---

## What is {package.name}?

{card.description} It's a free, open-source {technology.name} package by Pharaonic.

## How do I install {package.name}?

Run `composer require {package.composer}` in your project's root directory.

## What does {package.name} require?

The latest release requires {package.requiresText}.

## How is it different from array_diff()?

`array_diff()` compares only the top level, loosely, and by value. {package.name} walks nested arrays and objects, compares strictly, aligns lists so one insertion does not shift every later item, matches records by ID, and tells you the exact path, type and old and new value of each change.

## Can I keep passwords and tokens out of the result?

Yes. `DiffOptions::create()->redact('user.password')` still detects that the value changed, but the result stores `[REDACTED]` instead of it, including inside the values of its parent and of added or removed containers.

## Can I apply the text diff as a patch?

Yes. `toUnified()` writes a standard unified diff that `git apply` and `patch` accept, including the "No newline at end of file" marker.

## Does it work with Laravel?

Yes, it is plain PHP with no dependencies, so you can use it anywhere. Convert models with `toArray()` first, or register a normalizer for them.

## Is {package.name} free to use?

Yes. {package.name} is open source under the {package.license} license, so you can use it in personal and commercial projects.

## Where can I find the {package.name} documentation?

Read the [{package.name} documentation]({package.docsUrl}) for setup, configuration, and usage examples.

## How do I report a bug or contribute to {package.name}?

Open an issue or a pull request on [GitHub]({package.githubUrl}), or ask in the [Pharaonic Discord](https://discord.gg/XQG9RhvEvf).
