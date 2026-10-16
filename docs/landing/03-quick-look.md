---
view: components.packages.quick-look
title: A quick look
subtitle: Give it the old value and the new value, and it tells you what changed.
file: examples.php
language: php
code: |
  use Pharaonic\Diff\Diff;

  // Compare two values
  $before = ['name' => 'Ali', 'age' => 30, 'phone' => '0100'];
  $after  = ['name' => 'Ali', 'age' => 31, 'email' => 'ali@example.com'];

  $result = Diff::between($before, $after);

  $result->wasModified('age');   // true
  $result->wasAdded('email');    // true
  $result->wasRemoved('phone');  // true
  $result->hasChanged('name');   // false

  // Compare two texts
  echo Diff::text("apple\nbanana\ncherry\n", "apple\nblueberry\ncherry\n")->toUnified();
  // --- before
  // +++ after
  // @@ -1,3 +1,3 @@
  //  apple
  // -banana
  // +blueberry
  //  cherry
---
