midgard-portable [![Build Status](https://travis-ci.org/flack/midgard-portable.png?branch=master)](https://travis-ci.org/flack/midgard-portable)
================

This library aims to provide a simulation of the Midgard API for Doctrine.
It is in a prototype state and provides the following:

 - Creating Doctrine ClassMetadata and `midgard_dbobject` based Entity classes from MgdSchema XML files
 - Support for most of the `midgard_object` API (CRUD, parameters, attachments, parent/up relations, softdelete, etc.)
 - Query Support for `midgard_query_builder`, `midgard_collector` and `midgard_object_class`
 - Metadata support, Repligard, `midgard_blob`. `midgard_user`
 - Partial support for database creation/update (`midgard_storage`) and reflection (`midgard_reflection_property`)

Usage
--------

To include `midgard-portable` in your application, simply `require` it in your `composer.json`. You can bootstrap
the adapter like this:

```php
<?php
use midgard\portable\driver;
use midgard\portable\storage\connection;

$db_config = array
(
    'driver' => 'pdo_sqlite',
    'memory' => true
);
$schema_dirs = array('/path/to/my/schemas/');
$var_dir = '/path/to/vardir';
$entity_namespace = '';
$dev_mode = false;

$driver = new driver($schema_dirs, $var_dir, $entity_namespace);
connection::initialize($driver, $db_config, $dev_mode);
$entityManager = connection::get_em();
```

Change the parameters as required. If you save this file under the name `cli-config.php`, it will be used by Doctrine's
CLI runner. After calling `connection::initialize()`, you can interact with the database through Midgard API as
outlined above.

Goals
-----

For the moment, the goal is to implement enough of the Midgard API to run [openpsa](https://github.com/flack/openpsa) 
on. This means that both older features (like MultiLang or Sitegroups) and newer features (like Workspaces) are out of
scope. But Pull Requests are of course welcome, so if anyone feels motivated to work on those areas, go right ahead!

Structure
--------

Basically, the adapter consists of three parts: The XML reader, which transforms MgdSchema files into an intermediate
representation, the class generator, which converts it into PHP classes that correspond to Midgard DB object classes
(and that are used by Doctrine as entity classes) and lastly, the Metadata driver, which builds the ClassMetadata
information Doctrine uses for querying and hydrating data.

Apart from that, there is a bunch of helper classes that provide special Midgard behaviors for Doctrine in the form
of a Query Filter, an Event Subscriber and one special Type currently. And of course there are versions of (most of)
Midgard's PHP classes, which provide the actual API emulation.

Known Issues & Limitations
--------------------------

 - Entities in Doctrine can only share the same table if there is a discriminator column which tells them apart.
   Currently, midgard-portable works around this by only registering one of the colliding classes which collects
   all properties of all affected classes. The others are then converted into aliases. This means that
   if you have e.g. `midgard_person`` and `org_openpsa_person` schemas, you only get one entity class containing
   the properties of both classes, and an a class alias for the second name. Which class becomes the actual class
   depends on the order the files are read, so for all practical purposes, it's random right now.

   For the collected properties, some limitations apply: For example, if two MgdSchema classes using the same DB table
   both define a property with the PHP name `myfield`, but they both point to a different `dbfield`, one of them
   will become unreachable. Also, if two MgdSchema classes define different field types for the same field (e.g. 
   `string` vs. `text` on a field named `extra`), only one of these definitions will be used. The latter case 
   may be implementable in the adapter, but it really is not a solid configuration to begin with (as it could 
   theoretically lead to data loss), so this is not in the cards for now

 - Links to non-PK fields are not supported in Doctrine. So GUID-based link functionality is implemented in the adapter,
   which entails a performance penalty. Also, some cases (like parent GUID links) are not supported yet

 - It is not possible to run midgard-portable when the original Midgard (or Midgard2) extension is loaded. This is
   problem could get addressed at some point, but it mostl likely wouldn't do any good, since the extension registers
   all its classes on PHP startup, so that the adapter's classes would never get loaded. 

 - Doctrine does not support value objects currently, so Metadata simulation is somewhat imperfect in the sense
   that the metadata columns are accessible through the object itself (e.g. `$topic->metadata_deleted`). The
   next planned Doctrine ORM release (2.5) will support for embedded objects (`Embeddable`), so this issue can be revisited
   once that is released.

 - Doctrine is somewhat stricter when it comes to referential integrity. So some of the more quirky behaviors of
   Midgard (like being able to purge parents while deleted children are still in the database) are more or less
   impossible to implement with reasonable effort. Unfortunately, the exception thrown in those cases is rather
   cryptic, and normally says something like

   ```
   A new entity was found through the relationship 'classname#link_property' that was not configured
   to cascade persist operations for entity
   ```
   
 - Doctrine does not support public properties on entity classes, so using `get_object_vars()` will always return
   an empty result. Obviously, `ReflectionExtension('midgard2')` will also fail, so you can't use this to get a list
   of all registered MgdSchema classes. As a workaround, you can use [midgard-introspection](https://github.com/flack/midgard-introspection),
   which abstracts these differences away.

 - Do not try to call `print_r()`, `var_dump()` or similar functions on entities. This applies to all Doctrine entities
   and is not specific to midgard-portable, but it is worth remembering if you try to run code originally written for
   the Midgard extension. midgard-introspection contains a `print_r()` method you can use instead.

 - Using `midgard_storage::update_class_storage()` can lead to data loss: If you run this command, all table columns
   that are not listed in the MgdSchema will be dropped, so you shouldn't use this on converted Midgard1 databases e.g.
