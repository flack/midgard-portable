midgard-portable [![Build Status](https://travis-ci.org/flack/midgard-portable.png?branch=master)](https://travis-ci.org/flack/midgard-portable)
================

This library aims to provide a simulation of the Midgard API for Doctrine. 
It is very much in a prototype state and currently provides the following:

 - Creating Doctrine ClassMetadata from MgdSchema XML files
 - Creating Doctrine Entity classes that provide access to some of the ``midgard_db_object`` API
 - Partial support for ``midgard_query_builder`` and ``midgard_collector``
 - Metadata + Soft Delete
 - Repligard table + midgard_datetime

Structure
--------

Basically, the adapter consists of three parts: The XML reader, which transforms MgdSchema files into an intermediate 
representation, the class generator, which converts it into PHP classes that correspond to Midgard DB object classes 
(and that are used by Doctrine as entity classes) and lastly, the Metadata driver, which builds the ClassMetadata 
information Doctrine uses for querying and hydrating data.

Apart from that, there is a bunch of helper classes that provide special Midgard behaviors for Doctrine in the form
of a Query Filter, an Event Subscriber and one special Type currently. And of course there are versions of (most of) 
Midgard's PHP classes, which provide the actual API emulation.

Goals
-----

For the moment, the goal is to implement enough of the Midgard API to run openpsa on. This means that both older
features (like MultiLang or Sitegroups) and newer features (like Workspaces) are out of scope. But Pull Requests 
are of course welcome, so if anyone feels motivated to work on those areas, go right ahead!

Known Issues & Limitations
--------------------------

 - Entities in Doctrine can only share the same table if there is a discriminator column which tells them apart.
   Currently, midgard-portable works around this by only registering one of the colliding classes which contains
   all properties of all affected classes. The others are then converted into aliases. This means that 
   if you have e.g. ``midgard_person`` and ``org_openpsa_person`` schemas, you only get one entity class containing 
   the properties of both classes, and an a class alias for the second name. Which class becomes the actual class 
   depends on the order the files are read, so for all practical purposes, it's random right now
   
 - Links to non-ID fields are not supported in Doctrine. So any GUID-based links are currently not working, but a
   workaround for this will get implemented eventually 
   
 - Currently, it is not possible to run midgard-portable when the original Midgard extension is loaded. This is
   also a temporary problem that will get addressed

 - the MySQL ``SET`` column type used in some MgdSchemas is not yet implemented. the XML reader will fall back to 
   the ``type`` value from the property definition. Implementing ``SET``/``ENUM`` support in Doctrine is not too hard to do,
   but it is not a priority right now
   
 - Doctrine does not support setting collation by column, so the ``BINARY`` keyword used in one or two MgdSchemas is 
   ignored and a message is printed
   
 - Doctrine does not support value objects currently, so Metadata simulation is somewhat imperfect in the sense 
   that the metadata columns are accessible through the object itself (e.g. ``$topic->metadata_deleted``). The 
   next planned Doctrine release (2.5) may contain support for embedded objects, so this issue can be revisited
   once that is released

...and of course, much of the API provided still only consists of stubs. It is a prototype, after all :)
