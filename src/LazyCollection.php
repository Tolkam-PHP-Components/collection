<?php declare(strict_types=1);

namespace Tolkam\Collection;

use Closure;
use Countable;
use Generator;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;
use stdClass;

/**
 * Generators-based collection with low memory usage
 *
 * Inspired by Illuminate\Support\LazyCollection
 * @package Tolkam\Collection
 */
class LazyCollection implements IteratorAggregate, Countable
{
    /**
     * @var mixed
     */
    protected mixed $source;
    
    /**
     * @var CachingIterator|null
     */
    protected ?CachingIterator $cached = null;
    
    /**
     * @var bool
     */
    protected bool $useCache = false;
    
    /**
     * @var self|null
     */
    protected ?self $previous = null;
    
    /**
     * @param mixed     $source
     * @param bool      $useCache Whether to load items into memory on first iteration
     * @param self|null $previous
     */
    protected function __construct(mixed $source, bool $useCache, self &$previous = null)
    {
        $this->source = $source;
        $this->useCache = $useCache;
        $this->previous = $previous;
    }
    
    /**
     * Creates new collection
     *
     * @param       $source
     * @param bool  $useCache
     *
     * @return static
     */
    public static function create($source, bool $useCache = false): self
    {
        return new static($source, $useCache);
    }
    
    /**
     * Creates empty collection
     *
     * @return static
     */
    public static function empty(): self
    {
        return static::create([]);
    }
    
    /**
     * Checks if collection is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return !$this->getIterator()->valid();
    }
    
    /**
     * Filters items by callback
     *
     * @param callable|null $callback
     *
     * @return static
     * @noinspection PhpUnusedParameterInspection
     */
    public function filter(callable $callback = null): self
    {
        if (is_null($callback)) {
            $callback = function ($value, $key) {
                return (bool) $value;
            };
        }
        
        return new static(function () use ($callback) {
            foreach ($this as $key => $value) {
                if ($callback($value, $key)) {
                    yield $key => $value;
                }
            }
        }, $this->useCache, $this);
    }
    
    /**
     * Execute a callback over each item
     *
     * @param callable $callback Return false to stop
     *
     * @return static
     */
    public function each(callable $callback): self
    {
        foreach ($this as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }
        
        return $this;
    }
    
    /**
     * Apply a callback over each of the items and use return as value
     *
     * @param callable $callback
     * @param bool     $recursive
     *
     * @return static
     */
    public function map(callable $callback, bool $recursive = false): self
    {
        return new static(function () use ($callback, $recursive) {
            foreach ($this as $key => $value) {
                if ($recursive && $value instanceof self) {
                    $value = $value->map($callback);
                }
                else {
                    $value = $callback($value, $key);
                }
                
                yield $key => $value;
            }
        }, $this->useCache, $this);
    }
    
    /**
     * Reverses the item order
     *
     * @return static
     */
    public function reverse(): self
    {
        return new static(array_reverse($this->toArray(), true), $this->useCache, $this);
    }
    
    /**
     * Update keys by callback returning key value
     *
     * @param callable $callback
     *
     * @return static
     */
    public function keyBy(callable $callback): self
    {
        return new static(function () use ($callback) {
            foreach ($this as $k => $v) {
                $resolvedKey = $callback($v, $k);
                
                if (is_object($resolvedKey)) {
                    $resolvedKey = (string) $resolvedKey;
                }
                
                yield $resolvedKey => $v;
            }
        }, $this->useCache, $this);
    }
    
    /**
     * Groups items by value
     *
     * @param callable $keyRetriever
     *
     * @return static
     */
    public function groupBy(callable $keyRetriever): self
    {
        $useCache = $this->useCache;
        
        $groups = new static(function () use ($keyRetriever, $useCache) {
            
            $grouped = [];
            foreach ($this as $k => $item) {
                $key = $keyRetriever($item);
                if (is_string($key) || is_int($key)) {
                    $grouped[$key][$k] = $item;
                }
            }
            
            foreach ($grouped as $key => $group) {
                yield $key => new static($group, $useCache, $this);
            }
        }, $useCache, $this);
        
        return new static($groups, $useCache, $this);
    }
    
    /**
     * Gets item keys
     *
     * @return static
     */
    public function keys(): self
    {
        return new static(function () {
            foreach ($this as $key => $value) {
                yield $key;
            }
        }, $this->useCache, $this);
    }
    
    /**
     * Gets an item by key
     *
     * @param      $key
     * @param null $default
     *
     * @return mixed|null
     */
    public function get($key, $default = null): mixed
    {
        if (is_null($key)) {
            return $default;
        }
        
        foreach ($this->getIterator() as $outerKey => $outerValue) {
            if ($outerKey == $key) {
                return $outerValue;
            }
        }
        
        return $default;
    }
    
    /**
     * Get the first item passing an optional truth test
     *
     * @param callable|null $callback
     * @param null          $default
     *
     * @return mixed|null
     */
    public function first(callable $callback = null, $default = null): mixed
    {
        $iterator = $this->getIterator();
        
        if (is_null($callback)) {
            if (!$iterator->valid()) {
                return $default;
            }
            
            return $iterator->current();
        }
        
        foreach ($iterator as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }
        
        return $default;
    }
    
    /**
     * Get the last item passing an optional truth test
     *
     * @param callable|null $callback
     * @param null          $default
     *
     * @return mixed
     */
    public function last(callable $callback = null, $default = null): mixed
    {
        $found = $nothing = new stdClass();
        
        foreach ($this as $key => $value) {
            if (is_null($callback) || $callback($value, $key)) {
                $found = $value;
            }
        }
        
        return $found === $nothing ? $default : $found;
    }
    
    /**
     * Skip the first N items
     *
     * @param int $count
     *
     * @return static
     */
    public function skip(int $count): self
    {
        return new static(function () use ($count) {
            $iterator = $this->getIterator();
            
            while ($iterator->valid() && $count--) {
                $iterator->next();
            }
            
            while ($iterator->valid()) {
                yield $iterator->key() => $iterator->current();
                
                $iterator->next();
            }
        }, $this->useCache, $this);
    }
    
    /**
     * Defers loading until value is requested
     *
     * @param Closure $deferred
     *
     * @return static
     */
    public function defer(Closure $deferred): self
    {
        return new static(function () use ($deferred) {
            yield from $deferred($this) ?? static::empty();
        }, $this->useCache, $this);
    }
    
    /**
     * Loads all items to memory and returns a new collection
     *
     * @return static
     */
    public function load(): self
    {
        return new static($this->toArray(), $this->useCache, $this);
    }
    
    /**
     * Gets items count
     *
     * @return int
     */
    public function count(): int
    {
        $iterator = $this->getIterator();
        $count = $iterator->valid() ? iterator_count($iterator) : 0;
        $count && $iterator->rewind();
        
        return $count;
    }
    
    /**
     * @param callable|null $callback
     *
     * @return array
     */
    public function toArray(callable $callback = null): array
    {
        $arr = iterator_to_array($this->getIterator());
        
        foreach ($arr as $k => $v) {
            if ($v instanceof self) {
                $v = $v->toArray($callback);
            }
            
            $arr[$k] = $callback ? $callback($v) : $v;
        }
        
        return $arr;
    }
    
    /**
     * @inheritDoc
     */
    public function getIterator(): Iterator
    {
        if ($this->cached) {
            $this->cached->rewind();
            
            return $this->cached;
        }
        
        $resolved = static::resolveGenerator($this->source, $this);
        
        if ($this->useCache) {
            $resolved = $this->cached = new CachingIterator($resolved);
        }
        
        return $resolved;
    }
    
    /**
     * Creates generator from mixed source
     *
     * @param                $source
     * @param LazyCollection $self
     *
     * @return Generator
     */
    protected static function resolveGenerator($source, self $self): Generator
    {
        $resolved = null;
        
        if ($source instanceof Generator) {
            $resolved = $source;
        }
        
        if ($source instanceof Closure) {
            $resolved = $source($self);
            if (!($resolved instanceof Generator)) {
                throw new InvalidArgumentException(sprintf(
                    'Closure must return a %s instance, %s given',
                    Generator::class,
                    gettype($resolved)
                ));
            }
        }
        
        if (is_iterable($source)) {
            $resolved = static::generatorFromIterable($source);
        }
        
        if (!$resolved) {
            throw new InvalidArgumentException(
                'Collection source is not of the supported types'
            );
        }
        
        return $resolved;
    }
    
    /**
     * @param iterable $iterable
     *
     * @return Generator
     */
    protected static function generatorFromIterable(iterable $iterable): Generator
    {
        yield from $iterable;
    }
}
