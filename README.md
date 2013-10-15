midgard-portable
================

This library aims to provide a simulation of the Midgard API for Doctrine. 
It is very much in a prototype state and currently provides implements the following:

 - Creating Doctrine ClassMetadata from MgdSchema XML files
 - Creating Doctrine Entity classes that provide access to some of the midgard_db_object API
 - Partial support for midgard_query_builder
 - Metadata + Soft Delete
 - Repligard table + midgard_datetime
 
Goals
-----

For the moment, the goal is to implement enough of the Midgard API to run openpsa on. This means that both older
features (like MultiLang or Sitegroups) and newer features (like Workspaces) are out of scope. But Pull Requests 
are of course welcome, so if anyone feels motivated to work on those areas, go right ahead!
 
Known Issues & Limitations
--------------------------

 - Entities in Doctrine can only share the same table if there is a discriminator column which tells them apart.
   Currently, midgard-portable works around this by only registering one of the colliding classes which contains
   all properties of all affected classes. The others are then converted into empty child classes. This means that 
   if you have e.g. midgard_person and org_openpsa_person schemas, you only get one entity class (which one it is 
   exactly is random right now), containing the properties of both classes.
   
 - Links to non-ID fields are not supported in Doctrine. So any GUID-based links are currently not working, but a
   workaround for this will get implemented eventually 
   
 - Currently, it is not possible to run midgard-portable when the original Midgard extenstion is loaded. This is
   also a temporary problem that will get addressed

 - the MySQL SET column type used in some MgdSchemas is not yet implmented. midgard-portable will fall back to 
   the "type" value from the property definition. Implmenting SET/ENUM support in Doctrine is not too hard to do,
   but it is not a priority right now
   
 - Doctrine does not support setting collation by column, so the BINARY keyword used in one or two MgdSchemas is 
   ignored and a message is printed
   
 - Doctrine does not support value objects currently, so Metadata simulation is somewhat imperfect in the sense 
   that the metadata columns are accessible through the object itself (e.g. ``$topic->metadata_deleted``). The 
   next planned Doctrine release (2.5) may contain support for embedded objects, so this issue can be revisited
   once this is released
