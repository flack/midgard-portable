midgard-portable [![Code Coverage](https://scrutinizer-ci.com/g/flack/midgard-portable/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/flack/midgard-portable/?branch=master)
================

This library provides an ActiveRecord ORM built on top of Doctrine 2 and is modeled after the [Midgard](http://www.midgard-project.org) API.

In a Nutshell
-------------

You can define your entities in XML (usually referred to MgdSchema):

```xml
<type name="my_person" table="person">
    <property name="id" type="unsigned integer" primaryfield="id">
        <description>Local database identifier</description>
    </property>
    <property name="firstname" type="string" index="yes">
        <description>First name of the person</description>
    </property>
    <property name="lastname" type="string" index="yes">
        <description>Last name of the person</description>
    </property>
</type>
```

Running `midgard-portable schema` will create a corresponding database table and a PHP class (usually referred to as the MgdSchema class). You can use this to read from and write to the DB:

```php
// create a new person
$person = new my_person();
$person->firstname = 'Alice';
if ($person->create()) {
    echo 'Created person #' . $person->id;
}
// load a new copy of the same person
$loaded = new my_person($person->id);
$loaded->firstname = 'Bob';
if ($loaded->update()) {
    echo 'Renamed from ' . $person->firstname . ' to ' . $loaded->firstname;
}
```

midgard-portable automatically adds metadata to the record:

```php
$person = new my_person();
$person->firstname = 'Alice';
$person->create();
sleep(1);
$person->lastname = 'Cooper';
$person->update();
echo 'Person was created on ' . $person->metadata->created->format('Y-m-d H:i:s');
echo  ' and last updated on ' . $person->metadata->updated->format('Y-m-d H:i:s');
```

It also supports soft-delete:

```php
$person = new my_person();
$person->firstname = 'Alice';
$person->create();
$person->delete();
try {
    $loaded = new my_person($person->id);
} catch (midgard_error_exception $e) {
    echo $e->getMessage(); // prints "Object does not exist."
}
// Revert the deletion
my_person::undelete($person->guid);
// or remove the entry completely
$person->purge();
```

You can query entries like this:

```php
$qb = new midgard_query_builder('my_person');
$qb->add_constraint('metadata.created', '>', '2012-12-10 10:00:00');
$qb->add_order('firstname');
foreach ($qb->execute() as $result) {
    echo $result->lastname . "\n";
}
```
Or, you simply use Doctrine's builtin `QueryBuilder`.

Then, there's object trees, links, working with files, import/export of data and lots more, but until there is time to document all that, you'll have to read the source to find out (the unit tests might also be a good starting point).

Usage
--------

To include `midgard-portable` in your application, simply `require` it in your `composer.json`. You can bootstrap the adapter like this:

```php
<?php
use midgard\portable\driver;
use midgard\portable\storage\connection;

$db_config = [
    'driver' => 'pdo_sqlite',
    'memory' => true
];
$schema_dirs = ['/path/to/my/schemas/'];
$var_dir = '/path/to/vardir';
$entity_namespace = '';
$dev_mode = false;

$driver = new driver($schema_dirs, $var_dir, $entity_namespace);
connection::initialize($driver, $db_config, $dev_mode);
```

Change the parameters as required. After calling `connection::initialize()`, you can interact with the database through Midgard API as outlined above.

### CLI tools

`midgard-portable` needs to generate entity classes as well as `ClassMetadata` and `Proxy` classes for Doctrine. In development setups, this is done automatically on each request. For production installations, you can run the following CLI command:

```
./bin/vendor/midgard-portable schema
```

It works very much like the `midgard-schema` tool of old, i.e. it will generate `midgard_object` classes based on MgdSchema XML files, the accompanying mapping data and proxy classes, and also create/update the corresponding database tables. You will need to run this once during initial installation, and then again each time the MgdSchemas change.

You can also use Doctrine's CLI runner and all the functionality it provides if you create a file under the name `cli-config.php`, with this content:

```php
<?php
use midgard\portable\storage\connection;
require 'my_settings_file.php'; //This needs to contain the code shown above
$entityManager = connection::get_em();
```
