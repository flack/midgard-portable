<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test\schema;

use midgard\portable\mgdschema\manager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class managerTest extends TestCase
{
    private const DIR = TESTDIR . '__output/managerTest/';

    protected function tearDown() : void
    {
        if (file_exists(self::DIR . 'schema.xml')) {
            unlink(self::DIR . 'schema.xml');
        }
    }

    /**
     * @param array $types Format: name => ['table' => table, 'parent' => parent (optional)]
     */
    private function write_schema(array $types)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><Schema xmlns="http://www.midgard-project.org/repligard/1.4">';
        foreach ($types as $name => $type) {
            $parent = $type['parent'] ?? null;
            $xml .= '<type name="' . $name . '" table="' . $type['table'] . '"' . ($parent ? ' parent="' . $parent . '"' : '') . '>';
            $xml .= '<property name="id" type="unsigned integer" primaryfield="id"/>';
            if ($parent) {
                $xml .= '<property name="pid" type="unsigned integer" link="' . $parent . ':id" parentfield="pid"/>';
            }
            $xml .= '</type>';
        }
        if (!is_dir(self::DIR)) {
            mkdir(self::DIR, 0777, true);
        }
        file_put_contents(self::DIR . 'schema.xml', $xml . '</Schema>');
    }

    /**
     * @param array $types Format: name => ['table' => table, 'parent' => parent (optional)]
     * @param array $children Format: parent => [child, ...]
     * @param array $merged Format: name => root type
     */
    #[DataProvider('provider_merged_types')]
    public function test_merged_types(array $types, array $children, array $merged)
    {
        // The result must not depend on the order in the file
        foreach ([$types, array_reverse($types, true)] as $sorted) {
            $this->write_schema($sorted);
            $manager = new manager([self::DIR], 'test');
            $all_types = $manager->get_types();

            $this->assertEquals($merged, $manager->get_inherited_mapping());
            foreach (array_keys($types) as $name) {
                $this->assertSame(!isset($merged[$name]), isset($all_types['test\\' . $name]), $name);
                $expected = array_fill_keys($children[$name] ?? [], 'pid');
                $this->assertEquals($expected, $manager->get_child_classes($name), $name);
                foreach ($expected as $child => $field) {
                    $this->assertSame($name, $all_types['test\\' . $child]->parent);
                    $this->assertSame($field, $all_types['test\\' . $child]->parentfield);
                }
            }
        }
    }

    /**
     * Types sharing a table are merged into the alphabetically first one, so xxx_alias always ends up in xxx
     */
    public static function provider_merged_types()
    {
        return [
            'single child' => [
                'types' => [
                    'project' => ['table' => 'project_table'],
                    'project_alias' => ['table' => 'project_table'],
                    'task' => ['table' => 'task_table', 'parent' => 'project_alias'],
                ],
                'children' => ['project' => ['task']],
                'merged' => ['project_alias' => 'project'],
            ],
            'merged children' => [
                'types' => [
                    'project' => ['table' => 'project_table'],
                    'project_alias' => ['table' => 'project_table'],
                    'task' => ['table' => 'task_table', 'parent' => 'project_alias'],
                    'task_alias' => ['table' => 'task_table', 'parent' => 'project_alias'],
                ],
                'children' => ['project' => ['task']],
                'merged' => ['project_alias' => 'project', 'task_alias' => 'task'],
            ],
            'merged children with different parents from same group' => [
                'types' => [
                    'project' => ['table' => 'project_table'],
                    'project_alias' => ['table' => 'project_table'],
                    'task' => ['table' => 'task_table', 'parent' => 'project'],
                    'task_alias' => ['table' => 'task_table', 'parent' => 'project_alias'],
                ],
                'children' => ['project' => ['task']],
                'merged' => ['project_alias' => 'project', 'task_alias' => 'task'],
            ],
            'only merged child has parent' => [
                'types' => [
                    'project' => ['table' => 'project_table'],
                    'project_alias' => ['table' => 'project_table'],
                    'task' => ['table' => 'task_table'],
                    'task_alias' => ['table' => 'task_table', 'parent' => 'project_alias'],
                ],
                'children' => ['project' => ['task']],
                'merged' => ['project_alias' => 'project', 'task_alias' => 'task'],
            ],
            'merged children, regular parent' => [
                'types' => [
                    'project' => ['table' => 'project_table'],
                    'task' => ['table' => 'task_table', 'parent' => 'project'],
                    'task_alias' => ['table' => 'task_table', 'parent' => 'project'],
                ],
                'children' => ['project' => ['task']],
                'merged' => ['task_alias' => 'task'],
            ],
            'three levels' => [
                'types' => [
                    'subtask' => ['table' => 'subtask_table', 'parent' => 'task_alias'],
                    'subtask_alias' => ['table' => 'subtask_table', 'parent' => 'task_alias'],
                    'task' => ['table' => 'task_table', 'parent' => 'project_alias'],
                    'task_alias' => ['table' => 'task_table', 'parent' => 'project_alias'],
                    'project' => ['table' => 'project_table'],
                    'project_alias' => ['table' => 'project_table'],
                ],
                'children' => ['project' => ['task'], 'task' => ['subtask']],
                'merged' => ['project_alias' => 'project', 'task_alias' => 'task', 'subtask_alias' => 'subtask'],
            ],
            'groups depending on each other' => [
                'types' => [
                    'project' => ['table' => 'project_table', 'parent' => 'task_alias'],
                    'project_alias' => ['table' => 'project_table', 'parent' => 'task_alias'],
                    'task' => ['table' => 'task_table', 'parent' => 'project_alias'],
                    'task_alias' => ['table' => 'task_table', 'parent' => 'project_alias'],
                ],
                'children' => ['project' => ['task'], 'task' => ['project']],
                'merged' => ['project_alias' => 'project', 'task_alias' => 'task'],
            ],
            'child in same group' => [
                'types' => [
                    'project' => ['table' => 'project_table', 'parent' => 'project_alias'],
                    'project_alias' => ['table' => 'project_table'],
                ],
                'children' => ['project' => ['project']],
                'merged' => ['project_alias' => 'project'],
            ],
        ];
    }
}
