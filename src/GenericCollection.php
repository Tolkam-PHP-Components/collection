<?php declare(strict_types=1);

namespace Tolkam\Collection;

class GenericCollection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    public static function getItemType(): ?string
    {
        return null;
    }
}
