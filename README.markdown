# Page Prototypes extension

Enables the creation/management of page prototypes from which Symphony Pages can be spawned.

This extension is heavily inspired by [czheng](http://symphony-cms.com/get-involved/member/czheng/)'s [Page Templates](http://symphony-cms.com/download/extensions/view/22943/) extension and it is meant to be its successor.

## Installation

1. Upload the 'page_prototypes' folder in this archive to your Symphony 'extensions' folder.
2. Go to System > Extensions, select "Page Prototypes", choose "Enable" from the with-selected menu, then click Apply.

## Usage

Pages with the type `prototype` can act as prototypes for other pages. The extension adds a select box on top of the pages editor where the prototype of a page can be selected. In the frontend pages automatically inherit all the properties and the template of the referenced prototype.

Prototypes can't have the special page types `index`, `404`, `403` or `maintenance`, because there can only exist one aof those pages in the system. Logged in developers can visit prototype pages in the front end, but normal visitors can't.