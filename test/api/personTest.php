<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

class personTest extends testcase
{
    public static function setupBeforeClass()
    {
        $ns = uniqid(__CLASS__);
        self::prepare_connection(array(TESTDIR . '__files/membership/'), sys_get_temp_dir(), $ns);

        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $factory = self::$em->getMetadataFactory();
        $classes = $factory->getAllMetadata();
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    public function test_membership()
    {
        $person_class = self::$ns . '\\midgard_person';
        $group_class = self::$ns . '\\midgard_group';
        $member_class = self::$ns . '\\midgard_member';
        $person = new $person_class;
        $this->assertTrue($person->create());
        $grp = new $group_class;
        $this->assertTrue($grp->create());

        $member = new $member_class;
        $member->uid = $person->id;
        $member->gid = $grp->id;
        $this->assertTrue($member->create());

        // disabled because of http://www.doctrine-project.org/jira/browse/DDC-2761
        //self::$em->clear();

        $member = new $member_class($member->id);
        $person = new $person_class($person->id);
        $this->assertEquals($person->id, $member->uid);

        $parent = $member->get_parent();
        $this->assertInternalType('object', $parent);
        $this->assertEquals($grp->guid, $parent->guid);

        $this->assertTrue($grp->has_dependents());

        $this->assertTrue($person->delete());
        $this->assertTrue($person->purge());
        $member = new $member_class($member->id);
        $this->assertTrue($member->delete());
    }
}
