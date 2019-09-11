<?php declare(strict_types=1);

namespace Tolkam\Collection;

use Countable;
use IteratorAggregate;
use JsonSerializable;

interface CollectionInterface extends IteratorAggregate, Countable, JsonSerializable
{
    /**
     * Adds item
     *
     * @param       $item
     * @param array $indexes
     *
     * @return void
     */
    public function add($item, array $indexes = []): void;
    
    /**
     * Removes item
     *
     * @param $item
     *
     * @return bool
     */
    public function remove($item): bool;
    
    /**
     * Checks if item was added
     *
     * @param $item
     *
     * @return bool
     */
    public function has($item): bool;
    
    /**
     * Gets item by index
     *
     * @param string           $indexName
     * @param string|string[]  $indexValue
     *
     * @return mixed|null
     */
    public function getBy(string $indexName, $indexValue);
    
    /**
     * Reverses items order
     *
     * @return void
     */
    public function reverse(): void;
    
    /**
     * Applies a callback to each item
     *
     * @param  callable $callback
     *
     * @return void
     */
    public function each(callable $callback);
    
    /**
     * Removes all items
     *
     * @return void
     */
    public function clear(): void;
    
    /**
     * @return mixed|null
     */
    public function first();
    
    /**
     * @return mixed|null
     */
    public function last();
    
    /**
     * @return mixed|null
     */
    public function pop();
    
    /**
     * @return mixed|null
     */
    public function shift();
    
    /**
     * @inheritDoc
     */
    public function toArray(): array;
}
