<?php declare(strict_types=1);

namespace Tolkam\Collection;

use ArrayIterator;
use InvalidArgumentException;
use RuntimeException;

abstract class AbstractCollection implements CollectionInterface
{
    /**
     * @var int
     */
    protected $itemsCount = 0;
    
    /**
     * @var array
     */
    protected $items = [];
    
    /**
     * @var array
     */
    protected $indexes = [];
    
    /**
     * @inheritDoc
     */
    public function add($item, array $indexes = []): void
    {
        // check item type
        $type = static::getItemType();
        if ($type !== null && !$this->isTypeValid($item, $type)) {
            throw new InvalidArgumentException(sprintf(
                'Collection item must be of type %s, %s given',
                $type,
                gettype($item)
            ));
        }
        
        // add item
        $this->items[] = $item;
        
        // add indexes
        $position = $this->count() - 1;
        foreach ($indexes as $k => $v) {
            $this->addIndex($k, $v, $position);
        }
    
        // update count
        $this->itemsCount++;
    }
    
    /**
     * Gets the allowed items type
     *
     * @return null|string
     */
    abstract public static function getItemType(): ?string;
    
    /**
     * @inheritDoc
     */
    public function count(): int
    {
        // return count($this->items);
        return $this->itemsCount;
    }
    
    /**
     * @inheritDoc
     */
    public function remove($item): bool
    {
        foreach ($this->items as $offset => $value) {
            if ($item === $value) {
                unset($this->items[$offset]);
                $this->itemsCount--;
                $this->removeIndices($offset);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * @inheritDoc
     */
    public function getBy(string $indexName, $indexValue, $default = null)
    {
        $indexes = $this->indexes[$indexName] ?? null;
        
        if (empty($indexes)) {
            return $default;
        }
        
        // array of index values
        if (is_array($indexValue)) {
            // search unique values only
            $indexValue = array_unique($indexValue, SORT_REGULAR);
            
            $items = [];
            foreach ($indexValue as $value) {
                $this->assertIndexValueValid($value);
                if (isset($indexes[$value])) {
                    $items[] = $this->items[$indexes[$value]];
                }
            }
            
            return $items;
            
        // single index value
        } else if (is_string($indexValue)) {
            return $this->items[$indexes[$indexValue] ?? null] ?? null;
        }
        
        throw new InvalidArgumentException('Index value must be string or array of strings');
    }
    
    /**
     * @inheritDoc
     */
    public function reverse(): void
    {
        $this->items = array_reverse($this->items, true);
    }
    
    /**
     * @inheritDoc
     */
    public function each(callable $callback)
    {
        foreach ($this->items as $position => $item) {
            if ($callback($item, $position) === false) {
                break;
            }
        }
    }
    
    /**
     * @inheritDoc
     */
    public function has($item): bool
    {
        return in_array($item, $this->items);
    }
    
    /**
     * @inheritDoc
     */
    public function first()
    {
        foreach ($this->items as $item) {
            return $item;
        }
        
        return null;
    }
    
    /**
     * @inheritDoc
     */
    public function last()
    {
        return array_slice($this->items, -1)[0] ?? null;
    }
    
    /**
     * @inheritDoc
     */
    public function pop()
    {
        return array_pop($this->items);
    }
    
    /**
     * @inheritDoc
     */
    public function shift()
    {
        return array_shift($this->items);
    }
    
    /**
     * @inheritDoc
     */
    public function clear(): void
    {
        $this->itemsCount = 0;
        $this->items = [];
        $this->indexes = [];
    }
    
    /**
     * @inheritDoc
     */
    public function getIterator()
    {
        return new ArrayIterator($this->items);
    }
    
    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }
    
    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return array_values($this->items);
    }
    
    /**
     * Checks if item is of valid type
     *
     * @param        $item
     * @param string $type
     *
     * @return bool
     */
    protected function isTypeValid($item, string $type): bool
    {
        $type = 'boolean' == $type ? 'bool' : $type;
        
        $isFunction = 'is_' . mb_strtolower($type);
        $cTypeFunction = 'ctype_' . mb_strtolower($type);
        
        if (function_exists($isFunction) && $isFunction($item)) {
            return true;
        } else if (function_exists($cTypeFunction) && $cTypeFunction($item)) {
            return true;
        } else if ($item instanceof $type) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Adds index for item position
     *
     * @param string $key
     * @param        $value
     * @param        $position
     */
    protected function addIndex(string $key, $value, $position)
    {
        $this->assertIndexValueValid($value);
        
        if (isset($this->indexes[$key][$value])) {
            throw new RuntimeException(sprintf('Index "%s:%s" is already exists', $key, $value));
        }
    
        $this->indexes[$key][$value] = $position;
    }
    
    /**
     * Removes item indexes by item position
     *
     * @param int $itemOffset
     */
    protected function removeIndices(int $itemOffset)
    {
        foreach ($this->indexes as $k => $v) {
            foreach ($v as $kk => $vv) {
                if ($vv !== $itemOffset) {
                    continue;
                }
                
                unset($this->indexes[$k][$kk]);
                if (empty($this->indexes[$k])) {
                    unset($this->indexes[$k]);
                }
            }
        }
    }
    
    /**
     * Validates index value
     *
     * @param             $value
     * @param string|null $message
     */
    protected function assertIndexValueValid($value, string $message = null)
    {
        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentException($message ?? 'Index value must be string or integer');
        }
    }
}
