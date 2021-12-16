### IdeaV

This is a Low-code tool, where you may configure and use your back-end mostly [with no programming](https://www.youtube.com/watch?v=HRDTs3JPvOc).

In this particular case we upload **hundreds of millions of records**, which consist of tens of attributes.
The objective is to provide users with a tool to create any number of structures of any complexity and then quickly search the data by any attribute (i.e. field, property).
You may find a live prototype [here](https://ideav.pro/sber) for batch upload
a similar app for record-by-record inserts [there](https://dev.forthcrm.ru/my)
or see **half a billion records** uploaded [in Youtube](https://www.youtube.com/watch?v=l0eg2xuC9Ks).

The application consists of the following parts:
 - the core - a PHP-script
 - front-end templates (HTML+CSS+JS)
 - An SQL-script to create a DB and table (MySQL)

#### Deployment
1. Create a MySQL db along with one table, using the script named `sber.sql`
2. Grant access to a MySQL user and put the credentials into `/include/connection.php`
3. Copy the rest of the files into you server http root folder and configure your PHP
   - I use Apache, so there are `.htaccess` files to secure the folders and redirect the calls
   - In case you don't use Apache, make sure you got this done:
     - Secure the folders `include`, `logs`, `templates` to restrict access from outside
     - Rout all 404 to `index.php` to support friendly URLs
4. Go to `yourhost`/`sber` and you'll be logged in as guest user though with admin's rights

#### How could you index half-billion of data by every fields out of 50+?

The project implements the **Quintet data model approach**: it stores your data as a list of indexed and linked values.

#### What the heck is Quintet?
Quintet is a way to present atomic pieces of data indicating their role in the business area. Quintets can describe any item, while each of them contains complete information about itself and its relations to other quintets. Such description does not depend on the platform used. Its objective is to simplify the storage of data and to improve the visibility of their presentation.

