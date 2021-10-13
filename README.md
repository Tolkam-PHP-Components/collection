# tolkam/collection

Wrappers for iterable data.

## Documentation

The code is rather self-explanatory and API is intended to be as simple as possible. Please, read the sources/Docblock if you have any questions. See [Usage](#usage) for quick start.

## Usage

````php
use Tolkam\Collection\LazyCollection;

$collection = LazyCollection::create(function() {
    // do some database query or something heavy
    echo 'fetched!' . PHP_EOL;
    yield from range(0, 9);
}, true);

// manipulate collection before data if fetched
$collection = $collection
    ->reverse()
    ->keyBy(fn($item) => 'key' . $item);

// get the manipulation result
print_r($collection->toArray());
````

## License

Proprietary / Unlicensed 🤷
