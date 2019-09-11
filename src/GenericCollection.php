<?php declare(strict_types=1);

namespace Tolkam\Collection;

class GenericCollection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    public static function getType(): ?string
    {
        return null;
    }
}
