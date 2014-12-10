<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test\schema;

use midgard\portable\mgdschema\property;
use midgard\portable\mgdschema\type;
use SimpleXMLElement;

class propertyTest extends \PHPUnit_Framework_TestCase
{
    public function test_set()
    {
        $type = new type(new SimpleXMLElement('<test />'));
        $property = new property($type, 'test', 'test');
        $this->assertEquals('test', $property->name);
        $this->assertEquals('test', $property->field);
        $this->assertEquals('test', $property->dbtype);
        $property->set('dbtype', 'test2');
        $property->set('type', 'test3');
        $property->set('link', 'midgard_topic:id');
        $this->assertEquals('test2', $property->dbtype);
        $this->assertInternalType('array', $property->link);
        $this->assertArrayHasKey('target', $property->link);
        $this->assertArrayHasKey('field', $property->link);
        $this->assertEquals('midgard_topic', $property->link['target']);
        $this->assertEquals('id', $property->link['field']);
    }

    public function test_index_guid()
    {
        $type = new type(new SimpleXMLElement('<test />'));
        $property = new property($type, 'test', 'guid');
        $this->assertTrue($property->index);

        $type = new type(new SimpleXMLElement('<test />'));
        $property = new property($type, 'guid', 'guid');
        $this->assertFalse($property->index);
    }

    public function test_index_noidlink()
    {
        $type = new type(new SimpleXMLElement('<test />'));
        $property = new property($type, 'test', 'string');
        $property->set('link', 'midgard_person:guid');
        $this->assertTrue($property->index);
    }
}