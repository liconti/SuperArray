<?php
//namespace p_RC\Config;

use \ArrayAccess;
use \IteratorAggregate;
use \ArrayIterator;
use \InvalidArgumentException;
use \Countable;

class SuperArray implements ArrayAccess, IteratorAggregate, Countable
{
    private $data = array();
    private $key_map = array();
    
    private $case_sensitive = true;
    private $ignore_not_exists = false;
    private $case_transform = null;
    private $keys_case_transform = null;

    // necessary for deep copies
    public function __clone()
    {
        foreach ($this->data as $key => $value) {
            if ($value instanceof self) {
                $this[$key] = clone $value;
            }
        }
    }

    public function __construct($data = array())
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $this[$key] = $value;
            }
        } else {
            //$this[] = $data;
            $this->data = $data;
        }
    }

    public function offsetSet($offset, $data)
    {
        $data = new self($data);
        if ($offset === null) { // don't forget this!
            $this->data[] = $data;
        } else {
            $this->data[$offset] = $data;
        }
        $this->createKeyMap($offset);
    }

    public function value()
    {
        $data = $this->data;
        if (is_array($data)) {
            $data = $this->toArray();
        } else {
            $data = $this->transformData($data);
        }
        return $data;
    }
    public function toArray()
    {
        $data = $this->data;
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if ($value instanceof self) {
                    $data[$key] = $value->toArray();
                }
            }
            $data = $this->transformKeys($data);
        } else {
            $data = array($data);
        }
        $data = $this->transformData($data);
        return $data;
    }

    // as normal array
    //public function offsetGet($offset) { return isset($this->data[$offset]) ? $this->data[$offset] : null; }
    public function offsetGet($offset)
    {
        //return $this->data[$offset];
        if (!$this->offsetExists($offset)) {
            if (false == $this->ignore_not_exists) {
                throw new InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $offset));
            } else {
                //return null;
                return new self(null);
            }
        } else {
            $data = ($this->case_sensitive) ? $this->data[$offset] : $this->data[$this->key_map[strtolower($offset)]];
            $data = $this->transformData($data);
            $data = $this->transformKeys($data);
            return $data;
        }
    }

    public function offsetExists($offset)
    {
        //return isset($this->data[$offset]);
        return $this->exists($offset);
    }

    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    //as properties
    public function __get($offset)
    {
        return $this->offsetGet($offset);
    }
    
    public function __set($offset, $data)
    {
        return $this->offsetSet($offset, $data);
    }

    public function __isset($offset)
    {
        return $this->offsetExists($offset);
    }

    public function __unset($offset)
    {
        return $this->offsetUnset($offset);
    }

    /**
     * Countable: Count all elements in
     * data property array
     * 
     * @return int
     */
    public function count()
    {
        return count($this->data);
    }

    /**
     * IteratorAggregate: return a external Iterator 
     * based on data property array
     * 
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator((array)$this->data);
    }

    private function createKeyMap($key = null)
    {
        if (null === $key) {
            $this->key_map[] = $key;
        } else {
            $this->key_map[strtolower($key)] = $key;
        }
    }

    private function exists($offset)
    {
        if ($this->case_sensitive) {
            return isset($this->data[$offset]);
        } else {
            return isset($this->key_map[strtolower($offset)]);
        }
    }

    private function setPropertyRecursive($property, $value)
    {
        $this->{$property} = $value;
        if (is_array($this->data)) {
            foreach ($this->data as $data) {
                if ($data instanceof self) {
                    $data->setPropertyRecursive($property, $value);
                }
            }
        }
    }

    /**
     * Set $this->ignore_not_exists
     */
    public function setIgnoreUndefined($ignore_not_exists = false)
    {
        $this->setPropertyRecursive('ignore_not_exists', (bool)$ignore_not_exists);
        return $this;
    }

    /**
     * Set $this->case_sensitive
     */
    public function setCaseSensitivity($case_sensitive = true)
    {
        $this->setPropertyRecursive('case_sensitive', (bool)$case_sensitive);
        return $this;
    }

    /**
     * Set $this->keys_case_transform
     * 
     * @param mixed $case_transform CASE_LOWER CASE_UPPER or null to not perform any transformation
     */
    public function setKeysCaseTransform($keys_case_transform = null)
    {
        if (CASE_LOWER === $keys_case_transform) {
            $this->setPropertyRecursive('keys_case_transform', CASE_LOWER);
        } else if (CASE_UPPER === $keys_case_transform) {
            $this->setPropertyRecursive('keys_case_transform', CASE_UPPER);
        } else {
            $this->setPropertyRecursive('keys_case_transform', null);
        }
        return $this;
    }


    /**
     * Set $this->case_transform
     * 
     * @param mixed $case_transform CASE_LOWER CASE_UPPER or null to not perform any transformation
     */
    public function setCaseTransform($case_transform = null)
    {
        if (CASE_LOWER === $case_transform) {
            $this->setPropertyRecursive('case_transform', CASE_LOWER);
        } else if (CASE_UPPER === $case_transform) {
            $this->setPropertyRecursive('case_transform', CASE_UPPER);
        } else {
            $this->setPropertyRecursive('case_transform', null);
        }
        return $this;
    }

    private function transformData($data)
    {
        if (null !== $this->case_transform) {
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    $result[$key] = $this->transformData($value);
                }
                $data = $result;
            }
            if (CASE_LOWER == $this->case_transform) {
                if (is_string($data)) {
                    $data = strtolower($data);
                }
            } elseif (CASE_UPPER == $this->case_transform) {
                if (is_string($data)) {
                    $data = strtoupper($data);
                }
            }
        }
        return $data;
    }

    private function transformKeys($data)
    {
        if (null !== $this->keys_case_transform) {
            if (is_array($data)) {
                $result = array_change_key_case($data, $this->keys_case_transform);
                foreach ($result as $key => $value) {
                    if (is_array($value)) {
                        $result[$key] = $this->transformKeys($value);
                    }
                }
                $data = $result;
            }
        }
        return $data;
    }

    
    public function toUpper()
    {
        $this->setCaseTransform(CASE_UPPER);
        $this->setKeysCaseTransform(CASE_UPPER);
        return $this;
    }

    public function toLower()
    {
        $this->setCaseTransform(CASE_LOWER);
        $this->setKeysCaseTransform(CASE_LOWER);
        return $this;
    }

    public function original()
    {
        $this->setCaseTransform(null);
        $this->setKeysCaseTransform(null);
        return $this;
    }

    public function path($path = '', $separator = '/')
    {
        if (!is_array($path)) {
            $path = explode($separator, $path);
        }
        $return = $this;
        foreach ($path as $value) {
            $return = $return[$value];
        }
        return $return;
    }
}
