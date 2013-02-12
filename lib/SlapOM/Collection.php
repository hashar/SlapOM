<?php
namespace SlapOM;

class Collection implements \Iterator, \Countable
{
    protected $map;
    private   $handler;
    protected $result;
    protected $entry_iterator;
    protected $index;

    public function __construct($handler, $result, $map)
    {
        $this->handler = $handler;
        $this->result = $result;
        $this->map = $map;
    }

    public function rewind()
    {
        $this->entry_iterator = ldap_first_entry($this->handler, $this->result);
        $this->index = 0;
    }

    public function current()
    {
        if (is_null($this->entry_iterator))
        {
            $this->rewind();
        }

        return $this->valid() ? $this->hydrateFromResult($this->entry_iterator) : null;
    }

    public function next()
    {
        $this->entry_iterator = ldap_next_entry($this->handler, $this->entry_iterator);
        $this->index = $this->valid() ? $this->index + 1 : $this->index;
    }

    public function valid()
    {
        return $this->entry_iterator !== false;
    }

    public function key()
    {
        return $this->index;
    }

    public function count()
    {
        return ldap_count_entries($this->handler, $this->result);
    }

    /**
     * I cannot imagine a lib being more crap than PHP LDAP one.
     * Structure information is melt with data, all functions need a 
     * connection handler, there are 367 ways of doing the things but only one 
     * works (at least with binary results) without failures nor error 
     * messages. Result keys change with automatic pagination without notice. 
     * It has been a hell to debug, thanks to the obsolutely non informative 
     * error messages. PURE CRAP !!
     **/
    protected function hydrateFromResult($ldap_entry)
    {
        if ($ldap_entry === false) return false;

        $values = array();
        foreach($this->getAttributes($ldap_entry) as $ldap_attribute)
        {
            $attribute = strpos($ldap_attribute, ';') === false ? $ldap_attribute : substr($ldap_attribute, 0, strpos($ldap_attribute, ';'));

            if ($this->map->getAttributeModifiers($attribute) & EntityMap::FIELD_BINARY)
            {
                $value = @ldap_get_values_len($this->handler, $ldap_entry, sprintf("%s", $ldap_attribute));
            }
            else
            {
                $value = @ldap_get_values($this->handler, $ldap_entry, $ldap_attribute);
            }

            if (is_array($value))
            {
                if ($this->map->getAttributeModifiers($attribute) & EntityMap::FIELD_MULTIVALUED)
                {
                    unset($value['count']);
                    $values[$attribute] = $value;
                }
                else
                {
                    $values[$attribute] = $value[0];
                }
            }
        }
        $values['dn'] = ldap_get_dn($this->handler, $ldap_entry);

        return $this->map->createObject($values);
    }

    private function getAttributes($ldap_entry)
    {
        $fields = ldap_get_attributes($this->handler, $ldap_entry);
        unset($fields['count']);
        $fields = array_filter($fields, function($val) { return (!is_array($val) and $val !== "count"); });

        return $fields;
    }
}
