<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard_reflection_property;

class propertyTest extends testcase
{
    public function test_get_midgard_type()
    {
        $ref = new midgard_reflection_property('midgard_topic');
        $this->assertEquals(MGD_TYPE_STRING, $ref->get_midgard_type('title'));
        $this->assertEquals(MGD_TYPE_UINT, $ref->get_midgard_type('up'));
        $this->assertEquals(MGD_TYPE_NONE, $ref->get_midgard_type('xxx'));
    }

    public function test_is_link()
    {
        $ref = new midgard_reflection_property('midgard_topic');
        $this->assertFalse($ref->is_link('title'));
        $this->assertTrue($ref->is_link('up'));
        $this->assertFalse($ref->is_link('xxx'));
    }

    public function test_get_link_name()
    {
        $ref = new midgard_reflection_property('midgard_topic');
        $this->assertEquals(null, $ref->get_link_name('title'));
        $this->assertEquals('midgard_topic', $ref->get_link_name('up'));
        $this->assertEquals(null, $ref->get_link_name('xxx'));
    }

    public function test_get_link_target()
    {
        $ref = new midgard_reflection_property('midgard_topic');
        $this->assertEquals(null, $ref->get_link_target('title'));
        $this->assertEquals('id', $ref->get_link_target('up'));
        $this->assertEquals(null, $ref->get_link_target('xxx'));
    }

    public function test_description()
    {
        $ref = new midgard_reflection_property('midgard_topic');
        $this->assertEquals('Arrangement score of the topic (legacy field)', $ref->description('score'));
        $this->assertEquals(null, $ref->description('xxx'));
    }

    public function test_property_exists()
    {
        $ref = new midgard_reflection_property('midgard_topic');
        $this->assertFalse($ref->property_exists('xxx'));
        $this->assertTrue($ref->property_exists('up'));
        $this->assertTrue($ref->property_exists('title'));
        $this->assertTrue($ref->property_exists('created', true));
    }
}
