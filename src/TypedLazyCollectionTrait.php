<?php

namespace Tolkam\Collection;

use InvalidArgumentException;
use RuntimeException;

/**
 * @method __construct($source, $useCache)
 * @method static resolveGenerator($source) Generator
 */
trait TypedLazyCollectionTrait
{
    /**
     * Gets the allowed items type
     *
     * @return string
     */
    abstract public static function itemType(): string;
    
    /**
     * @inheritDoc
     */
    public static function create($source, bool $useCache = false)
    {
        $source = static function () use ($source) {
            if (!$type = static::itemType()) {
                throw new RuntimeException('Item type must not be empty');
            }
            
            foreach (self::resolveGenerator($source) as $k => $v) {
                if (!self::isTypeValid($v, $type)) {
                    throw new InvalidArgumentException(sprintf(
                        'Each element of %s must be %s, %s given at "%s" index',
                        addslashes(static::class),
                        $type,
                        is_object($v) ? get_class($v) : gettype($v),
                        $k
                    ));
                }
                
                yield $k => $v;
            }
        };
        
        return new static($source, $useCache);
    }
    
    /**
     * Checks if item is of valid type
     *
     * @param        $item
     * @param string $type
     *
     * @return bool
     */
    protected static function isTypeValid($item, string $type): bool
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
