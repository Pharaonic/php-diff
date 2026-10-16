---
view: components.packages.quick-look
title: A quick look
subtitle: Compare two versions of a record, ask what changed, and keep secrets out of the result.
file: src/Audit/ProfileAudit.php
language: php
code: |
  use Pharaonic\Diff\Diff;
  use Pharaonic\Diff\DiffOptions;

  $options = DiffOptions::create()
      ->identify('addresses', 'id')
      ->redact('password')
      ->ignore('updated_at');

  $result = Diff::between($before, $after, $options);

  $result->wasModified('email');        // true
  $result->hasChanged('addresses.0');   // true
  $result->toJson();                    // store it in your audit log
---
