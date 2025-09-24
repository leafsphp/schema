<!-- markdownlint-disable no-inline-html -->
<p align="center">
    <img src="https://leafphp.dev/logo-circle.png" height="100"/>
    <br><br>
</p>

# Leaf Schema

[![Latest Stable Version](https://poser.pugx.org/leafs/schema/v/stable)](https://packagist.org/packages/leafs/schema)
[![Total Downloads](https://poser.pugx.org/leafs/schema/downloads)](https://packagist.org/packages/leafs/schema)
[![License](https://poser.pugx.org/leafs/schema/license)](https://packagist.org/packages/leafs/schema)

Schema files are a new way to define and manage your database structure in Leaf applications. Instead of writing complex migration files using PHP, and then managing multiple migrations for a single database table, you can now define a simplified structure using yml and let Leaf handle the rest.

This module packages the schema functionality into a standalone package which can be used in any PHP application once the port is complete.

***If you use Leaf MVC, this is already included and configured for you.***

To get started, install the schema module:

```sh
leaf install schema
# or
composer require leafs/schema
```

Once installed, you can start creating your schema files using the `g:schema` command:

```sh
./vendor/bin/leaf g:schema <name>
```

## Schema Files

Schema Files allow you to define your database tables, seed data, and keep track of your database automatically in one simple YAML file. They are clean, readable, and designed to help you move fast without the overhead. Every schema file is tied to a table in your database, for example, if you create a schema file called `flights`, it will be tied to the `flights` table in your database.

We can do this by running the command below:

```sh
./vendor/bin/leaf g:schema flights
```

This will create a new file in the `database` directory called `flights.yml`. You can open this file and start defining your table structure.

```yml
columns:
  to: string
  from: string
  identifier: string

seeds:
  count: 10
```

In this example, we have defined a table with three columns: `to`, `from`, and `identifier`, which are all of type string in this case, but can be any valid column type. You can view a full list of column types in the [documentation](https://leafphp.dev/docs/database/files.html#schema-columns).

## Migrating Schema Files

Once you have defined your schema file, you can create the table in your database by running the migrate command:

```sh
./vendor/bin/leaf db:migrate
```

This command will read all the schema files in the `database` directory and create the corresponding tables in your database. If the table already exists, it will update the table structure to match the schema file. You can also migrate a specific schema file by passing the name of the file as an argument:

```sh
./vendor/bin/leaf db:migrate flights
```

### Syncing Changes

The major difference between schema files and traditional migrations is that you don't have to create a new migration file every time you want to make a change to your table structure. Instead, you can simply update the schema file and run the migrate command again. Leaf will automatically detect the changes and apply them to the database *if it is aware of the previous state of the table*.

### Other commands

You can perform other operations on your schema files like rolling back changes, refreshing the database, and seeding data. We recommend checking out the [documentation](https://leafphp.dev/docs/database/files.html) for the full documentation on schema files and all the available commands.

## 💬 Stay In Touch

- [Twitter](https://twitter.com/leafphp)
- [Join the forum](https://github.com/leafsphp/leaf/discussions/37)
- [Chat on discord](https://discord.com/invite/Pkrm9NJPE3)

## 📓 Learning Leaf 3

- Leaf has a very easy to understand [documentation](https://leafphp.dev) which contains information on all operations in Leaf.
- You can also check out our [youtube channel](https://www.youtube.com/channel/UCllE-GsYy10RkxBUK0HIffw) which has video tutorials on different topics
- We are also working on codelabs which will bring hands-on tutorials you can follow and contribute to.

## 😇 Contributing

We are glad to have you. All contributions are welcome! To get started, familiarize yourself with our [contribution guide](https://leafphp.dev/community/contributing.html) and you'll be ready to make your first pull request 🚀.

To report a security vulnerability, you can reach out to [@mychidarko](https://twitter.com/mychidarko) or [@leafphp](https://twitter.com/leafphp) on twitter. We will coordinate the fix and eventually commit the solution in this project.

## 🤩 Sponsoring Leaf

Your cash contributions go a long way to help us make Leaf even better for you. You can sponsor Leaf and any of our packages on [open collective](https://opencollective.com/leaf) or check the [contribution page](https://leafphp.dev/support/) for a list of ways to contribute.

And to all our existing cash/code contributors, we love you all ❤️

### Cash contributors

<table>
	<tr>
		<td align="center">
			<a href="https://opencollective.com/aaron-smith3">
				<img src="https://images.opencollective.com/aaron-smith3/08ee620/avatar/256.png" width="120px" alt=""/>
				<br />
				<sub><b>Aaron Smith</b></sub>
			</a>
		</td>
		<td align="center">
			<a href="https://opencollective.com/peter-bogner">
				<img src="https://images.opencollective.com/peter-bogner/avatar/256.png" width="120px" alt=""/>
				<br />
				<sub><b>Peter Bogner</b></sub>
			</a>
		</td>
		<td align="center">
			<a href="#">
				<img src="https://images.opencollective.com/guest-32634fda/avatar.png" width="120px" alt=""/>
				<br />
				<sub><b>Vano</b></sub>
			</a>
		</td>
	</tr>
</table>

## 🤯 Links/Projects

- [Aloe CLI](https://leafphp.dev/aloe-cli/)
- [Leaf Docs](https://leafphp.dev)
- [Leaf MVC](https://mvc.leafphp.dev)
- [Leaf API](https://api.leafphp.dev)
- [Leaf CLI](https://cli.leafphp.dev)
