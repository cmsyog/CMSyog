<?php
require_once('Core\Serializer\YogSerializer.php');
class SerializerXML implements YogSerializer
{
    private $xml_root_tag = 'response';
    private $array_item_name = 'item';
    private $array_item_attribute = 'ID';
    private $content_type = 'text/xml;charset=utf-8';
    public function serialize($data)
    {
        if (!is_array($data)) $data = array($data);
        $XML_Element = new \SimpleXMLElement("<?xml version=\"1.0\"?><".$this->xml_root_tag."></".$this->xml_root_tag.">");
        $this->arrayToXML($data, $XML_Element);
        return $XML_Element->asXML();
    }
    protected function arrayToXML($data_array, \SimpleXMLElement $XML)
    {
        $consecutive_counter = 0;
        foreach ($data_array as $key => $value)
        {
            if (is_array($value))
            {
                if (!is_numeric($key))
                {
                    $subnode = $XML->addChild($key);
                    $this->arrayToXML($value, $subnode);
                }
                else
                {
                    $subnode = $XML->addChild($XML->getName().'_'.$this->array_item_name);
                    if ($key!=$consecutive_counter)
                    {
                        $subnode->addAttribute($this->array_item_attribute, $key);
                    }
                    $this->arrayToXML($value, $subnode);
                }
            }
            else
            {
                if (!is_numeric($key))
                {
                    $XML->addAttribute($key, $value);
                }
                else
                {
                    $subnode = $XML->addChild($this->array_item_name, str_replace('&', '&amp;', $value));
                    if ($key!=$consecutive_counter)
                    {
                        $subnode->addAttribute($this->array_item_attribute, $key);
                    }
                }
            }
            if (is_numeric($key))
            {
                $consecutive_counter++;
            }
        }
    }
    public function unserialize($data)
    {
        $internal_errors_prev_setting  = libxml_use_internal_errors(true);
        $disable_entities_prev_setting = libxml_disable_entity_loader(true);
        libxml_clear_errors();
        $xml = simplexml_load_string($data);
        libxml_use_internal_errors($internal_errors_prev_setting);
        libxml_disable_entity_loader($disable_entities_prev_setting);
        $json   = json_encode($xml);
        $result = json_decode($json, TRUE);
        if (empty($result)) return false;
        if (key($result)==$this->xml_root_tag)
        {
            $result = $result[$this->xml_root_tag];
        }
        $this->fixJSONArray($result, $this->xml_root_tag);
        return $result;
    }
    private function fixJSONArray(&$xmlarr, $parent_name = '', $pre_parent_name = '')
    {
        foreach ($xmlarr as $key => &$value)
        {
            if (is_array($value))
            {
                if ($key==='@attributes')
                {
                    unset($xmlarr[$key]);
                    $xmlarr = array_merge($xmlarr, $value);
                }
                elseif ($key===$parent_name)
                {
                    unset($xmlarr[$key]);
                    $xmlarr = array_merge($xmlarr, $value);
                }
                elseif ($key===$parent_name.'_'.$this->array_item_name)
                {
                    unset($xmlarr[$key]);
                    $xmlarr = array_merge($xmlarr, $value);
                }
                elseif ($key===$pre_parent_name.'_'.$this->array_item_name)
                {
                    unset($xmlarr[$key]);
                    $xmlarr = array_merge($xmlarr, $value);
                }
                elseif ($key===$this->array_item_name)
                {
                    unset($xmlarr[$key]);
                    $xmlarr = array_merge($xmlarr, $value);
                }
                else
                {
                    $this->fixJSONArray($value, $key, $parent_name);
                }
            }
            else
            {
                if ($key===$parent_name)
                {
                    unset($xmlarr[$key]);
                    $index          = $this->getNextFreeIndexOfArray($xmlarr);
                    $xmlarr[$index] = $value;
                    array_multisort($xmlarr);
                }
            }
        }
    }
    private function getNextFreeIndexOfArray($array)
    {
        foreach (array_keys($array) as $key)
        {
            if (is_numeric($key))
            {
                if (!isset($array[$key+1]))
                {
                    return $key+1;
                }
            }
        }
        return 0;
    }
    public function getMethodName()
    {
        return 'XML';
    }
    public function getXmlRootTag()
    {
        return $this->xml_root_tag;
    }
    public function setXmlRootTag($xml_root_tag)
    {
        $this->xml_root_tag = $xml_root_tag;
    }
    public function getContentType()
    {
        return $this->content_type;
    }
    public function setContentType($content_type)
    {
        $this->content_type = $content_type;
    }
}