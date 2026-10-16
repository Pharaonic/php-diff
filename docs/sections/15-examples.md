## Examples

### An Audit Log of Model Changes

Record what changed on every update, without timestamps or secrets.

- ===Observer

  ```php title="app/Observers/UserObserver.php"
  namespace App\Observers;

  use App\Models\User;
  use Pharaonic\Diff\Diff;
  use Pharaonic\Diff\DiffOptions;

  class UserObserver
  {
      public function updated(User $user): void
      {
          $options = DiffOptions::create()
              ->ignore(['updated_at', 'remember_token'])
              ->redact(['password', 'two_factor_secret']);

          $result = Diff::between($user->getRawOriginal(), $user->getAttributes(), $options);

          if ($result->hasChanges()) {
              $user->audits()->create(['changes' => $result->toArray()]);
          }
      }
  }
  ```

- ===Blade View

  ```blade title="resources/views/users/audits.blade.php"
  @foreach ($audit->changes as $change)
      <li>
          <strong>{{ $change['path'] }}</strong> {{ $change['type'] }}:
          {{ json_encode($change['old']) }} → {{ json_encode($change['new']) }}
      </li>
  @endforeach
  ```

Each stored change keeps its type, path, and old and new values, with `[REDACTED]` in place of the password.

### Syncing Records From an Import

Compare the current catalog with an imported one and act on each product by SKU.

```php title="src/Catalog/ImportSync.php"
use Pharaonic\Diff\Diff;
use Pharaonic\Diff\DiffOptions;

$options = DiffOptions::create()->identify('', 'sku')->ignore('*.synced_at');

$result = Diff::between($currentProducts, $importedProducts, $options);

foreach ($result->added() as $change) {
    $catalog->create($change->newValue());
}

foreach ($result->removed() as $change) {
    $catalog->archive($change->oldValue()['sku']);
}

foreach ($result->modified() as $change) {
    if ($change->isAggregate() && count($change->path()->segments()) === 1) {
        $catalog->update($change->newValue()['sku'], $change->newValue());
    }
}
```

Each product is matched by its SKU, wherever it is in either list, so a reordered import changes nothing.

### Showing a Revision Like GitHub

Render the difference between two versions of a document.

- ===Controller

  ```php title="app/Http/Controllers/RevisionController.php"
  use Pharaonic\Diff\Diff;
  use Pharaonic\Diff\TextDiffOptions;

  public function show(Revision $revision)
  {
      $diff = Diff::text(
          $revision->previous->body,
          $revision->body,
          TextDiffOptions::create()->ignoreTrailingWhitespace(),
      );

      return view('revisions.show', ['diff' => $diff]);
  }
  ```

- ===Blade View

  ```blade title="resources/views/revisions/show.blade.php"
  <link rel="stylesheet" href="{{ asset('css/diff.css') }}">

  <p>+{{ $diff->stats()['added'] }} −{{ $diff->stats()['removed'] }}</p>

  {!! $diff->toHtml() !!}
  ```

`toHtml()` escapes every piece of text, so printing it raw with `{!! !!}` is safe. Copy `resources/diff.css` to `public/css/diff.css` first.

### Downloading a Patch

Let users download the change as a patch they can apply with Git.

```php title="app/Http/Controllers/PatchController.php"
use Pharaonic\Diff\Diff;

public function download(Revision $revision)
{
    $patch = Diff::text($revision->previous->body, $revision->body)
        ->toUnified('a/' . $revision->path, 'b/' . $revision->path);

    return response($patch, 200, [
        'Content-Type' => 'text/x-diff',
        'Content-Disposition' => 'attachment; filename="revision-' . $revision->id . '.patch"',
    ]);
}
```

### Clear Failure Messages in Tests

Show exactly which fields of an API response changed, instead of a wall of JSON.

```php title="tests/Feature/OrderApiTest.php"
use Pharaonic\Diff\Diff;
use Pharaonic\Diff\DiffOptions;

it('returns the order unchanged', function () {
    $expected = json_decode(file_get_contents(__DIR__ . '/fixtures/order.json'), true);
    $actual = $this->getJson('/api/orders/1')->json();

    $result = Diff::between($expected, $actual, DiffOptions::create()->ignore(['*.created_at', '*.updated_at']));

    expect($result->hasChanges())->toBeFalse(json_encode($result->stats()) . PHP_EOL . $result->toJson(JSON_PRETTY_PRINT));
});
```
