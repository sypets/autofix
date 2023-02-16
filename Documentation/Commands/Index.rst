.. include:: /Includes.rst.txt

.. _command-reference:

========
Commands
========


autofix:fixDuplicateSlugs
=========================

Fix duplicate slugs (slugs with same language or slugs with one entry with
language -1). This was due to bug https://forge.typo3.org/issues/99529 which
is now fixed. The core bug is fixed, but records with duplicate slugs might
previously have been created.

In order for this script to work, a TYPO3 version with the fixed bug must be
installed, because core functionality is used!

Examples
--------

Fix slugs in table sys_category (dry-run, do not fix):

.. code-block:: shell

   php vendor/bin/typo3 fixduplicateslugs:fix -d sys_category

Fix slugs in table sys_category:

.. code-block:: shell

   php vendor/bin/typo3 fixduplicateslugs:fix sys_category
