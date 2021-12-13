### IdeaV

This is a Low-code tool, where you may configure and use your back-end mostly [with no programming](https://www.youtube.com/watch?v=HRDTs3JPvOc).

In this particular case we upload **hundreds of millions of records**, which consist of tens of attributes.
The objective is to provide users with a tool to create any number of structures of any complexity and then quickly search the data by any attribute (i.e. field, property).
You may find a live prototype [here](https://ideav.pro/sber) or see **half a billion records** uploaded [there](https://www.youtube.com/watch?v=l0eg2xuC9Ks).

The application consists of the following parts:
 - the core - a PHP-script
 - front-end templates (HTML+CSS+JS)

The project implements the **Quintet data model approach**: it stores your data as a list of indexed and linked values.

#### What the heck is Quintet?
Quintet is a way to present atomic pieces of data indicating their role in the business area. Quintets can describe any item, while each of them contains complete information about itself and its relations to other quintets. Such description does not depend on the platform used. Its objective is to simplify the storage of data and to improve the visibility of their presentation.

