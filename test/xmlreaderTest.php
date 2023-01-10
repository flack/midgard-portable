<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard\portable\xmlreader;
use midgard\portable\mgdschema\type;
use midgard\portable\api\mgdobject;
use PHPUnit\Framework\TestCase;

class xmlreaderTest extends TestCase
{
    public function test_topic()
    {
        $reader = new xmlreader;
        $types = $reader->parse(TESTDIR . '__files/midgard_topic.xml');
        $this->assertTrue(is_array($types));
        $this->assertArrayHasKey('midgard_topic', $types);
        $type = $types['midgard_topic'];
        $this->assertInstanceOf(type::class, $type);
        $this->assertEquals('midgard_topic', $type->name);
        $this->assertEquals('topic', $type->table);
        $this->assertEquals('\\' . mgdobject::class, $type->extends);
        $this->assertEquals('up', $type->upfield);
        $this->assertEquals('id', $type->primaryfield);

        $properties = $type->get_properties();
        $this->assertTrue(is_array($properties));
        $this->assertArrayHasKey('guid', $properties);
        $this->assertArrayHasKey('id', $properties);
        $this->assertEquals('guid', $properties['guid']->type);
        $this->assertEquals('guid', $properties['guid']->dbtype);
        $this->assertEquals(40, count($properties));

        $mixins = $type->get_mixins();
        $this->assertArrayHasKey('metadata', $mixins);
        $this->assertArrayHasKey('metadata_created', $mixins['metadata']->get_properties());
        $this->assertEquals('datetime', $mixins['metadata']->get_property('metadata_created')->type);
        $this->assertEquals(1, count($mixins));
    }
}
