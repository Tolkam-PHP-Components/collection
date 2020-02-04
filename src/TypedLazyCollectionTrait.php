<?php

namespace Tolkam\Collection;

use Generator;
use RuntimeException;

trait TypedLazyCollectionTrait
{
    /**
     * Gets the allowed items type
     *
     * @return null|string
     */
    abstract public static function itemType(): ?string;
    
    /**
     * Checks type of each iterator item
     *
     * @return Generator
     */
    public function getIterator()
    {
        $type = static::itemType();
        
        /** @noinspection PhpUndefinedClassInspection */
        foreach (parent::getIterator() as $k => $item) {
            if ($type !== null && !$this->isTypeValid($item, $type)) {
                throw new RuntimeException(sprintf(
                    'Each element of %s must be %s, %s given at "%s" index',
                    addslashes(static::class),
                    $type,
                    is_object($item) ? get_class($item) : gettype($item),
                    $k
                ));
            }
            
            yield $k => $item;
        }
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
        } else {
            if (function_exists($cTypeFunction) && $cTypeFunction($item)) {
                return true;
            } else {
                if ($item instanceof $type) {
                    return true;
                }
            }
        }
        
        return false;
    }
}
