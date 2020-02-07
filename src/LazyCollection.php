<?php declare(strict_types=1);

namespace Tolkam\Collection;

use Closure;
use Countable;
use Generator;
use InvalidArgumentException;
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
    protected $source;
    
    /**
     * @var CachingIterator
     */
    protected $cached;
    
    /**
     * @var bool
     */
    protected $useCache;
    
    /**
     * @param mixed $source
     * @param bool  $useCache Whether to load items into memory on first iteration
     */
    protected function __construct($source, bool $useCache)
    {
        $this->source = $source;
        $this->useCache = $useCache;
    }
    
    /**
     * Creates new collection
     *
     * @param      $source
     * @param bool $useCache
     *
     * @return static
     */
    public static function create($source, bool $useCache = false)
    {
        return new static($source, $useCache);
    }
    
    /**
     * Creates empty collection
     *
     * @return static
     */
    public static function empty()
    {
        return static::create([]);
    }
    
    /**
     * Checks if collection is empty
     *
     * @return bool
     */
    public function isEmpty()
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
    public function filter(callable $callback = null)
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
        }, $this->useCache);
    }
    
    /**
     * Execute a callback over each item
     *
     * @param callable $callback Return false to stop
     *
     * @return static
     */
    public function each(callable $callback)
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
     *
     * @return static
     */
    public function map(callable $callback)
    {
        return new static(function () use ($callback) {
            foreach ($this as $key => $value) {
                yield $key => $callback($value, $key);
            }
        }, $this->useCache);
    }
    
    /**
     * Reverses the item order
     *
     * @return static
     */
    public function reverse()
    {
        return new static(array_reverse($this->toArray(), true), $this->useCache);
    }
    
    /**
     * Update keys by callback returning key value
     *
     * @param callable $callback
     *
     * @return static
     */
    public function keyBy(callable $callback)
    {
        return new static(function () use ($callback) {
            foreach ($this as $k => $v) {
                $resolvedKey = $callback($v, $k);
                
                if (is_object($resolvedKey)) {
                    $resolvedKey = (string) $resolvedKey;
                }
                
                yield $resolvedKey => $v;
            }
        }, $this->useCache);
    }
    
    /**
     * Groups items by value
     *
     * @param callable $keyRetriever
     *
     * @return static
     */
    public function groupBy(callable $keyRetriever)
    {
        $useCache = $this->useCache;
        
        $grouped = [];
        foreach ($this as $k => $item) {
            $key = $keyRetriever($item);
            if (is_string($key) || is_int($key)) {
                $grouped[$key][$k] = $item;
            }
        }
        
        $groups = new static(static function () use ($grouped, $useCache) {
            foreach ($grouped as $key => $group) {
                yield $key => new static($group, $useCache);
            }
        }, $useCache);
        
        return new static($groups, $useCache);
    }
    
    /**
     * Gets item keys
     *
     * @return static
     */
    public function keys()
    {
        return new static(function () {
            foreach ($this as $key => $value) {
                yield $key;
            }
        }, $this->useCache);
    }
    
    /**
     * Gets an item by key
     *
     * @param      $key
     * @param null $default
     *
     * @return mixed|null
     */
    public function get($key, $default = null)
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
    public function first(callable $callback = null, $default = null)
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
    public function last(callable $callback = null, $default = null)
    {
        $found = $nothing = new stdClass;
        
        foreach ($this as $key => $value) {
            if (is_null($callback) || $callback($value, $key)) {
                $found = $value;
            }
        }
        
        return $found === $nothing ? $default : $found;
    }
    
    /**
     * Loads all items to memory and returns a new collection
     *
     * @return static
     */
    public function load()
    {
        return new static($this->toArray(), $this->useCache);
    }
    
    /**
     * Gets items count
     *
     * @return int
     */
    public function count()
    {
        return $this->isEmpty() ? 0 : iterator_count($this->getIterator());
    }
    
    /**
     * @return array
     */
    public function toArray(): array
    {
        return iterator_to_array($this->getIterator());
    }
    
    /**
     * @inheritDoc
     */
    public function getIterator()
    {
        if ($this->cached) {
            return $this->cached;
        }
        
        $resolved = static::resolveGenerator($this->source);
        
        if ($this->useCache) {
            $resolved = $this->cached = new CachingIterator($resolved);
        }
        
        return $resolved;
    }
    
    /**
     * Creates generator from mixed source
     *
     * @param $source
     *
     * @return Generator
     */
    protected static function resolveGenerator($source)
    {
        $resolved = null;
        
        if ($source instanceof Generator) {
            $resolved = $source;
        }
        
        if ($source instanceof Closure) {
            $resolved = $source();
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
    protected static function generatorFromIterable(iterable $iterable)
    {
        yield from $iterable;
    }
}
