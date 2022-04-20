<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard_reflector_object;
use midgard\portable\storage\connection;

class reflector_objectTest extends testcase
{
    public function test_get_property_primary()
    {
        $this->assertEquals('id', midgard_reflector_object::get_property_primary(connection::get_fqcn('midgard_topic')));
    }

    public function test_get_property_up()
    {
        $this->assertEquals('up', midgard_reflector_object::get_property_up(connection::get_fqcn('midgard_topic')));
        $this->assertNull(midgard_reflector_object::get_property_up(connection::get_fqcn('midgard_user')));
    }

    public function test_get_property_parent()
    {
        $this->assertEquals('topic', midgard_reflector_object::get_property_parent(connection::get_fqcn('midgard_article')));
        $this->assertNull(midgard_reflector_object::get_property_parent(connection::get_fqcn('midgard_topic')));
    }

    public function test_get_property_unique()
    {
        $this->assertEquals('name', midgard_reflector_object::get_property_unique(connection::get_fqcn('midgard_language')));
        $this->assertNull(midgard_reflector_object::get_property_unique(connection::get_fqcn('midgard_topic')));
    }

    public function test_has_metadata_class()
    {
        $this->assertTrue(midgard_reflector_object::has_metadata_class(connection::get_fqcn('midgard_topic')));
        $this->assertFalse(midgard_reflector_object::has_metadata_class(connection::get_fqcn('midgard_user')));
    }

    public function test_get_metadata_class()
    {
        $this->assertEquals('midgard_metadata', midgard_reflector_object::get_metadata_class(connection::get_fqcn('midgard_topic')));
        $this->assertNull(midgard_reflector_object::get_metadata_class(connection::get_fqcn('midgard_user')));
    }

    public function test_list_children()
    {
        $this->assertEquals(['midgard_article' => ''], midgard_reflector_object::list_children(connection::get_fqcn('midgard_topic')));
        $this->assertEquals([], midgard_reflector_object::list_children(connection::get_fqcn('midgard_user')));
    }
}
