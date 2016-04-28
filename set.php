<?php
/**
 * Kodekit - http://timble.net/kodekit
 *
 * @copyright   Copyright (C) 2007 - 2016 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     MPL v2.0 <https://www.mozilla.org/en-US/MPL/2.0>
 * @link        https://github.com/timble/kodekit for the canonical source repository
 */

namespace Kodekit\Library;

/**
 * Object Set
 *
 * A set is a data structure that can store objects, without any particular order, and no repeated values.  Unlike most
 * other collection types, rather than retrieving a specific element from a set, one typically tests if a object is
 * contained in the set.
 *
 * ObjectSet implements an associative container that stores objects, and in which the object themselves are the keys.
 * Objects are stored in the set in FIFO order.
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Kodekit\Library\Object
 * @see     http://www.php.net/manual/en/class.splobjectstorage.php
 */
class ObjectSet extends Object implements \IteratorAggregate, \ArrayAccess, \Countable, \Serializable
{
    /**
     * The objects
     *
     * @var array
     */
    private $__data = array();

    /**
     * Inserts an object in the set
     *
     * @param   ObjectHandlable $object
     * @return  boolean TRUE on success FALSE on failure
     */
    public function insert(ObjectHandlable $object)
    {
        $result = false;

        if ($handle = $object->getHandle())
        {
            $this->offsetSet(null, $object);
            $result = true;
        }

        return $result;
    }

    /**
     * Removes an object from the set
     *
     * All numerical array keys will be modified to start counting from zero while literal keys won't be touched.
     *
     * @param   ObjectHandlable $object
     * @return  ObjectSet
     */
    public function remove(ObjectHandlable $object)
    {
        if ($this->offsetExists($object)) {
            $this->offsetUnset($object);
        }

        return $this;
    }

    /**
     * Checks if the set contains a specific object
     *
     * @param   ObjectHandlable $object
     * @return  bool Returns TRUE if the object is in the set, FALSE otherwise
     */
    public function contains(ObjectHandlable $object)
    {
        return $this->offsetExists($object);
    }

    /**
     * Merge-in another object set
     *
     * @param   ObjectSet  $set
     * @return  ObjectSet
     */
    public function merge(ObjectSet $set)
    {
        foreach ($set as $object) {
            $this->insert($object);
        }

        return $this;
    }

    /**
     * Filter the set using a callback
     *
     * If the callback returns FALSE the object will not be included in the resulting object set.
     *
     * @param   Callable $filter A callback that will handle the filtering
     * @return  ObjectSet Returns an object subset
     */
    public function filter(/*Callable*/ $filter)
    {
        $result = clone $this;
        $result->clear();

        foreach ($this as $object)
        {
            if (call_user_func($filter, $object) !== false) {
                $result->insert($object);
            }
        }

        return $result;
    }

    /**
     * Check if the object exists in the queue
     *
     * Required by interface ArrayAccess
     *
     * @param   ObjectHandlable $object
     * @return  bool Returns TRUE if the object exists in the storage, and FALSE otherwise
     * @throws  \InvalidArgumentException if the object doesn't implement ObjectHandlable
     */
    public function offsetExists($object)
    {
        if (!$object instanceof ObjectHandlable) {
            throw new \InvalidArgumentException('Object needs to implement ObjectHandlable');
        }

        return isset($this->__data[$object->getHandle()]);
    }

    /**
     * Returns the object from the set
     *
     * Required by interface ArrayAccess
     *
     * @param   ObjectHandlable $object
     * @return  ObjectHandlable
     * @throws  \InvalidArgumentException if the object doesn't implement ObjectHandlable
     */
    public function offsetGet($object)
    {
        if (!$object instanceof ObjectHandlable) {
            throw new \InvalidArgumentException('Object needs to implement ObjectHandlable');
        }

        return $this->__data[$object->getHandle()];
    }

    /**
     * Store an object in the set
     *
     * Required by interface ArrayAccess
     *
     * @param   mixed             $offset The array offset [unused]
     * @param   ObjectHandlable  $object
     * @return  ObjectSet
     */
    public function offsetSet($offset, $object)
    {
        if (!$object instanceof ObjectHandlable) {
            throw new \InvalidArgumentException('Object needs to implement ObjectHandlable');
        }

        $this->__data[$object->getHandle()] = $object;
        return $this;
    }

    /**
     * Removes an object from the set
     *
     * Required by interface ArrayAccess
     *
     * @param   ObjectHandlable  $object
     * @return  ObjectSet
     * @throws  \InvalidArgumentException if the object doesn't implement the ObjectHandlable interface
     */
    public function offsetUnset($object)
    {
        if (!$object instanceof ObjectHandlable) {
            throw new \InvalidArgumentException('Object needs to implement ObjectHandlable');
        }

        unset($this->__data[$object->getHandle()]);
        return $this;
    }

    /**
     * Return a string representation of the set
     *
     * Required by interface \Serializable
     *
     * @return  string  A serialized object
     */
    public function serialize()
    {
        return serialize($this->__data);
    }

    /**
     * Unserializes a set from its string representation
     *
     * Required by interface \Serializable
     *
     * @param   string  $serialized The serialized data
     */
    public function unserialize($serialized)
    {
        $this->__data = unserialize($serialized);
    }

    /**
     * Returns the number of elements in the collection.
     *
     * Required by the Countable interface
     *
     * @return int
     */
    public function count()
    {
        return count($this->__data);
    }

    /**
     * Remove all objects from the set
     *
     * @return ObjectSet
     */
    public function clear()
    {
        $this->__data = array();
        return $this;
    }

    /**
     * Return the first object in the set
     *
     * @return ObjectHandlable or NULL is queue is empty
     */
    public function top()
    {
        $objects = array_values($this->__data);

        $object = null;
        if (isset($objects[0])) {
            $object = $objects[0];
        }

        return $object;
    }

    /**
     * Defined by IteratorAggregate
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->__data);
    }

    /**
     * Return an associative array of the data.
     *
     * @return array
     */
    public function toArray()
    {
        return iterator_to_array($this);
    }
}
