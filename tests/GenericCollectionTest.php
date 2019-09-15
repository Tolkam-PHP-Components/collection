<?php

use Tolkam\Collection\AbstractCollection;
use Tolkam\Collection\CollectionInterface;
use Tolkam\Collection\GenericCollection;

class GenericCollectionTest extends PHPUnit\Framework\TestCase
{
    public function testInterface()
    {
        $collection = new GenericCollection();
        $this->assertInstanceOf(CollectionInterface::class, $collection);
    }
    
    public function testCount()
    {
        $collection = new GenericCollection();
        
        $collection->add(10);
        $this->assertEquals(1, $collection->count());
        
        $collection->add(20);
        $this->assertEquals(2, $collection->count());
    
        // remove non-existent
        $collection->remove(15);
        $this->assertEquals(2, $collection->count());
    
        $collection->remove(10);
        $this->assertEquals(1, $collection->count());
        
        $collection->clear();
        $this->assertEquals(0, $collection->count());
    
        $collection->add(10);
        $this->assertEquals(1, $collection->count());
    
        $collection->add(20);
        $this->assertEquals(2, $collection->count());
    }
    
    public function testItemType()
    {
        $stringCollection = new class extends AbstractCollection {
            public static function getItemType(): ?string
            {
                return 'string';
            }
        };
        
        // valid type
        $stringCollection->add('str');
        $this->assertTrue(true);
    
        $this->expectException(InvalidArgumentException::class);
        $stringCollection->add(10);
    }
}
