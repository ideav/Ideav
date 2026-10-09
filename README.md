### Hi, this is IdeaV 👋

We build **Integram** — a no-code platform that turns a spreadsheet into a real business application:
linked tables, forms, roles and access rights, reports — without writing code.
Or without even clicking through the builder: **ask an AI agent, and it builds the app for you.**

- 🌍 [integram-ai.online](https://integram-ai.online) — Integram in English: upload an Excel file, get a web app, connect Claude or any MCP agent to build on it
- 🇷🇺 [ideav.ru](https://ideav.ru) — Интеграм: российский no-code конструктор приложений и баз данных, в реестре отечественного ПО, облако или on-premise
- 📊 [excel-to-app.ru](https://excel-to-app.ru) — send us your working spreadsheet and get it back as an application, demo in 45 minutes

#### What makes it different

**One storage model for everything.** Integram does not create a SQL table per entity.
Every piece of data — a value, a column, a table, a link between records — is stored the same way,
as a small indexed tuple that knows its own type and its parent. Structures of any shape are added on the fly,
and every field is searchable by default. That is how a single installation keeps
**[half a billion records](https://www.youtube.com/watch?v=l0eg2xuC9Ks)** searchable by any of 50+ attributes.

**Built for AI agents.** Because the schema is data, an agent can read it, extend it and fill it
through the same API as a human. Integram exposes its API over the
[Model Context Protocol](https://modelcontextprotocol.io), so Claude and other MCP clients can design tables,
load data, build reports and dashboards right in your workspace.

**Business analysts change the system, not programmers.** Tables, forms, reports, buttons with actions,
roles and grants are configured, not coded. [See it in action](https://www.youtube.com/watch?v=HRDTs3JPvOc).

#### Projects

| Repository | What it is |
|---|---|
| [crm](https://github.com/ideav/crm) | Front-end of the current Integram: workspaces, the table component, CRM templates, MCP server |
| [backlogram](https://github.com/ideav/backlogram) | Product sites and landing pages: integram-ai.online, excel-to-app.ru and more |
| [gram-mobile](https://github.com/ideav/gram-mobile) | Integram mobile app, React Native |
| [connector](https://github.com/ideav/connector) | Pull data into Integram from MySQL, PostgreSQL, Oracle and MS SQL Server |

---

### This repository: the original prototype

This repo holds the early (2019) prototype the platform grew out of — a PHP core that uploads
**hundreds of millions of records** with tens of attributes each and lets users search them by any attribute.
A live copy for batch upload is [here](https://ideav.pro/sber).

It consists of:
 - the core — a PHP script
 - front-end templates (HTML+CSS+JS)
 - an SQL script to create the DB and table (MySQL)

#### Deployment
1. Create a MySQL db along with one table, using the script named `sber.sql`
2. Grant access to a MySQL user and put the credentials into `/include/connection.php`
3. Copy the rest of the files into your server http root folder and configure your PHP
   - With Apache, the `.htaccess` files secure the folders and redirect the calls
   - Without Apache, make sure that:
     - the folders `include`, `logs`, `templates` are not accessible from outside
     - all 404s are routed to `index.php` to support friendly URLs
4. Go to `yourhost`/`sber` and you'll be logged in as a guest user with admin rights

#### How can you index half a billion records by every one of 50+ fields?

The prototype implements the **Quintet data model**: it stores your data as a list of indexed and linked values.
A quintet is an atomic piece of data that carries its role in the business domain. Quintets can describe any item,
and each of them holds complete information about itself and its relations to other quintets.
Such a description does not depend on the platform used; the goal is to simplify data storage
and make its presentation transparent.

Today's Integram core keeps the same idea in an even leaner form — four fields per value instead of five.
