<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\mgdschema;


class property implements node
{
    public $link;

    public $noidlink;

    /**
     * DB column name (defaults to $this->name)
     *
     * @var string
     */
    public $field;

    /**
     * Does this field point to a parent
     *
     * @var string
     */
    public $parentfield;

    /**
     * Field name for MdgSchema object
     *
     * @var string
     */
    public $name;

    /**
     * Helpttext
     *
     * @var string
     */
    public $description;

    /**
     * Should an index be created for the column
     *
     * @var boolean
     */
    public $index = false;

    /**
     * Field type as written in XML
     *
     * @var string
     */
    public $type;

    /**
     * DB field type (defaults to $this->type)
     *
     * @var string
     */
    public $dbtype;

    /**
     * Are values unique?
     *
     * @var boolean
     */
    public $unique = false;

    /**
     * The field's default value
     *
     * @var mixed
     */
    public $default;

    /**
     * Parent type
     *
     * @var type
     */
    private $mgdschematype;

    public function __construct(type $parent, string $name, string $type)
    {
        $this->mgdschematype = $parent;
        $this->name = $name;
        $this->field = $name;
        $this->type = $type;
        $this->dbtype = $type;
        // type guid always gets an index (but the guid field itself already has unique)
        $this->index = ($type == 'guid' && $name != 'guid');
    }

    public function get_parent() : type
    {
        return $this->mgdschematype;
    }

    public function set(string $name, $value)
    {
        switch ($name) {
            case 'unique':
            case 'index':
                $value = ($value === 'yes');
                break;
            case 'link':
                if (strpos($value, ':') < 1) {
                    throw new \InvalidArgumentException('link target "' . $value . '" in ' . $this->mgdschematype->name . '::' . $this->name . ' is invalid ');
                }
                $tmp = explode(':', $value);
                $value = [];
                $value['target'] = $tmp[0];
                $value['field'] = $tmp[1];
                if ($value['field'] !== 'id') {
                    $this->noidlink = $value;
                    $value = null;
                    // Doctrine can't figure this out automatically, so we do it here
                    $this->index = true;
                }
                break;
        }
        $this->$name = $value;
    }

    public function set_multiple(array $attributes)
    {
        foreach ($attributes as $name => $value) {
            $this->set($name, $value);
        }
    }
}
