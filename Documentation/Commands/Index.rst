.. include:: /Includes.rst.txt

.. _command-reference:

========
Commands
========

autofix:generateSlugs
======================

Generate slugs for any field with type slug.

Since these will also be deduplicated, the same goes for support of slug types
as already explained in next command "autofix:fixDuplicateSlugs".

Examples:
---------

Generate slugs for sys_category. Use interactive mode where each conversion
must be confirmed.

.. code-block:: shell

   php vendor/bin/typo3 autofix:generateSlugs -i sys_category

autofix:fixDuplicateSlugs
=========================

.. attention::

   It may be necessary to run this command more than once.

Fix duplicate slugs (slugs with same language or slugs with one entry with
language -1). This was due to bug https://forge.typo3.org/issues/99529 which
is now fixed. The core bug is fixed, but records with duplicate slugs might
previously have been created.

In order for this script to work, a TYPO3 version with the fixed bug must be
installed, because core functionality is used!

The slug type is evaluated from
:php:`$GLOBALS['TCA'][$table]['columns'][$field]['config']['eval']`. Not all
slug types are currently supported for this command:

* unique: must be unique within entire installation
* uniqueInSite: must be unique within a site
* uniqueInPid: must be unique with all records with same pid

Currently, only unique and uniqueInPid are supported!

Examples
--------

Fix slugs in table sys_category (dry-run, do not fix):

.. code-block:: shell

   php vendor/bin/typo3 fixduplicateslugs:fix -d sys_category

Fix slugs in table sys_category:

.. code-block:: shell

   php vendor/bin/typo3 fixduplicateslugs:fix sys_category
