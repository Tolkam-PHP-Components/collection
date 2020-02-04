<?php declare(strict_types=1);

namespace Tolkam\Collection;

use Generator;
use Iterator;
use Traversable;

/**
 * Iterator for wrapping a Traversable and caching its results
 * to allow rewind and count multiple times.
 *
 * Based on doctrine/mongodb-odm CachingIterator
 *
 * @see https://github.com/doctrine/mongodb-odm/blob/master/lib/Doctrine/ODM/MongoDB/Iterator/CachingIterator.php
 */
class CachingIterator implements Iterator
{
    /**
     * @var array
     */
    private $items = [];
    
    /**
     * @var Generator
     */
    private $iterator;
    
    /**
     * @var bool
     */
    private $iteratorAdvanced = false;
    
    /**
     * @var bool
     */
    private $iteratorExhausted = false;
    
    /**
     * Initialize the iterator and stores the first item in the cache. This
     * effectively rewinds the Traversable and the wrapping Generator, which
     * will execute up to its first yield statement. Additionally, this mimics
     * behavior of the SPL iterators and allows users to omit an explicit call
     * to rewind() before using the other methods.
     *
     * @param Traversable $iterator
     */
    public function __construct(Traversable $iterator)
    {
        $this->iterator = $this->wrapTraversable($iterator);
        $this->storeCurrentItem();
    }
    
    /**
     * @return array
     */
    public function toArray(): array
    {
        $this->exhaustIterator();
        
        return $this->items;
    }
    
    /**
     * @inheritDoc
     */
    public function current()
    {
        return current($this->items);
    }
    
    /**
     * @inheritDoc
     */
    public function key()
    {
        return key($this->items);
    }
    
    /**
     * @inheritDoc
     */
    public function next(): void
    {
        if (!$this->iteratorExhausted) {
            $this->iterator->next();
            $this->storeCurrentItem();
        }
        
        next($this->items);
    }
    
    /**
     * @inheritDoc
     */
    public function rewind(): void
    {
        /* If the iterator has advanced, exhaust it now so that future iteration
         * can rely on the cache.
         */
        if ($this->iteratorAdvanced) {
            $this->exhaustIterator();
        }
        
        reset($this->items);
    }
    
    /**
     * @inheritDoc
     */
    public function valid(): bool
    {
        return $this->key() !== null;
    }
    
    /**
     * Ensures that the inner iterator is fully consumed and cached
     *
     * @return void
     */
    private function exhaustIterator(): void
    {
        while (!$this->iteratorExhausted) {
            $this->next();
        }
    }
    
    /**
     * Stores the current item in the cache
     *
     * @return void
     */
    private function storeCurrentItem(): void
    {
        $key = $this->iterator->key();
        
        if ($key === null) {
            return;
        }
        
        $this->items[$key] = $this->iterator->current();
    }
    
    /**
     * @param Traversable $traversable
     *
     * @return Generator
     */
    private function wrapTraversable(Traversable $traversable): Generator
    {
        foreach ($traversable as $key => $value) {
            yield $key => $value;
            $this->iteratorAdvanced = true;
        }
        
        $this->iteratorExhausted = true;
    }
}