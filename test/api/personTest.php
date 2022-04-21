<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

class personTest extends testcase
{
    public static function setupBeforeClass() : void
    {
        self::prepare_connection('membership/');

        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $factory = self::$em->getMetadataFactory();
        $classes = $factory->getAllMetadata();
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    public function test_membership()
    {
        $person = $this->make_object('midgard_person');
        $this->assertTrue($person->create());
        $grp = $this->make_object('midgard_group');
        $this->assertTrue($grp->create());

        $member = $this->make_object('midgard_member');
        $member->uid = $person->id;
        $member->gid = $grp->id;
        $this->assertTrue($member->create());

        self::$em->clear();

        $member = $this->make_object('midgard_member', $member->id);
        $person = $this->make_object('midgard_person', $person->id);
        $this->assertEquals($person->id, $member->uid);

        $parent = $member->get_parent();
        $this->assertIsObject($parent);
        $this->assertEquals($grp->guid, $parent->guid);

        $this->assertTrue($grp->has_dependents());

        $this->assertTrue($person->delete());
        $this->assertTrue($person->purge());
        $member = $this->make_object('midgard_member', $member->id);
        $this->assert_api('delete', $member);
    }
}
