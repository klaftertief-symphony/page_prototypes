# Page Prototypes extension

- Version: 0.6
- Author: Jonas Coch
- Build Date: 2011-02-09
- Requirements: Symphony 2.2

A Symphony extension enabling the creation of pages from predefined prototypes.

This extension is heavily inspired by [czheng](http://symphony-cms.com/get-involved/member/czheng/)'s [Page Templates](http://symphony-cms.com/download/extensions/view/22943/) extension and it is meant to be its successor.

## Installation

1. Upload the 'page_prototypes' folder in this archive to your Symphony 'extensions' folder.
2. Go to System > Extensions, select "Page Prototypes", choose "Enable" from the with-selected menu, then click Apply.

The extension adds two tables to the database and adds two columns to the pages table. So please backup your database.

## Usage

### Managing Prototypes

Page Prototypes can be managed at `Blueprints > Page Prototypes`. Creation/editing works almost exactly as with Pages, but with a few notable exceptions:

- There are no fields for "Parent Page" and "URL Handle".
- Template XSLT files are stored in `/workspace/pages`, prefixed with `_page_prototype_`.

### Create Pages From Prototypes

You can create a new Page by either copying a Prototype or by creating a reference to a Prototype. A referenced Page uses the XSLT Template and the URL Parameter, Page Type, Events and Data Sources of the referenced Prototype instead of its own XSLT Template and settings.

There are currently two ways to create new pages using your prototypes:

- When browsing the list of available templates, click the **Copy** or **Reference** link in the "Available Actions" column.
- Immediately after creating a template you will see a **Create Page Copy from Prototype** and **Create Page Reference from Prototype** link in the page alert.

### Edit Pages

The extension adds a new fieldset to the pages editor. You can change the connected prototype there and choose between the copy- and reference mode.
